<?php

namespace App\Services;

use App\Enums\BuyerType;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Mail\OrderPlaced;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\BuyerDetails;
use App\Support\Payments\PaymentMode;
use App\Support\Payments\PaymentReference;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Creating and cancelling orders for paid features. Payment is a bank transfer; the admin
 * marks an order paid through OrderFulfillment, which applies the effect.
 */
class OrderService
{
    /** Products bought for one listing. */
    public const LISTING_PRODUCTS = [Product::BUMP, Product::FEATURED_7, Product::FEATURED_30];

    /** Products bought for the account (firm). */
    public const ACCOUNT_PRODUCTS = [Product::BIZNIS_12M];

    public static function isOrderable(string $code): bool
    {
        return in_array($code, [...self::LISTING_PRODUCTS, ...self::ACCOUNT_PRODUCTS], true);
    }

    public static function needsListing(Product|string $product): bool
    {
        return in_array($product instanceof Product ? $product->code : $product, self::LISTING_PRODUCTS, true);
    }

    /** Active orderable product by code. */
    public function product(string $code): ?Product
    {
        if (! self::isOrderable($code)) {
            return null;
        }

        $product = Product::byCode($code);

        return $product && $product->is_active && $product->price_rsd > 0 ? $product : null;
    }

    /**
     * New pending order. If the same order is already waiting for payment (double submit,
     * second tab), that order is returned instead of a new one.
     *
     * @throws OrderException
     */
    public function create(User $user, string $code, ?Listing $listing, BuyerDetails $buyer): Order
    {
        $product = $this->product($code);
        if ($product === null) {
            throw new OrderException('Ova usluga trenutno nije dostupna.', 'product_unavailable');
        }
        if (! self::needsListing($product)) {
            $listing = null;
        }
        $buyer->validate();
        if ($buyer->type === BuyerType::Private && ! self::privateBuyersEnabled()) {
            throw new OrderException('Plaćanje kao fizičko lice trenutno nije moguće. Izaberite plaćanje kao firma ili nam pišite preko strane Kontakt.', 'private_disabled');
        }

        $order = DB::transaction(function () use ($user, $product, $listing, $buyer) {
            // Serialises order creation per user: the duplicate and pending-limit checks stay correct.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $existing = $this->pendingDuplicate($user, $product, $listing);
            if ($existing !== null) {
                return $existing;
            }

            $reason = $this->unavailableReason($user, $product, $listing);
            if ($reason !== null) {
                throw new OrderException($reason, 'not_allowed');
            }

            $pending = Order::query()->where('user_id', $user->getKey())->where('status', OrderStatus::Pending)->count();
            if ($pending >= (int) config('payments.max_pending_per_user', 10)) {
                throw new OrderException('Imate previše neplaćenih narudžbina. Platite ili otkažite neku od njih, pa pokušajte ponovo.', 'too_many_pending');
            }

            return $this->insert($user, $product, $listing, $buyer);
        });

        if ($order->wasRecentlyCreated) {
            Mail::to($user)->queue(new OrderPlaced($order));
        }

        return $order;
    }

    /**
     * Why this user cannot order this product now, or null when the order is allowed.
     * The text is shown to the user.
     */
    public function unavailableReason(User $user, Product $product, ?Listing $listing = null): ?string
    {
        if ($user->isBlocked()) {
            return 'Vaš nalog je blokiran.';
        }
        if (! self::isOrderable($product->code) || ! $product->is_active || $product->price_rsd <= 0) {
            return 'Ova usluga trenutno nije dostupna.';
        }

        if ($product->code === Product::BIZNIS_12M) {
            return $this->canBuyBiznis($user)
                ? null
                : 'Biznis paket je za firme. Nalog firme otvara administrator: pošaljite prijavu firme preko strane Kontakt.';
        }

        if ($listing === null) {
            return 'Izaberite oglas.';
        }
        if ((int) $listing->user_id !== (int) $user->getKey()) {
            return 'Uslugu možete da naručite samo za svoj oglas.';
        }
        if ($listing->trashed() || $listing->status !== ListingStatus::Published
            || ($listing->expires_at !== null && $listing->expires_at->isPast())) {
            return 'Usluga je dostupna samo za aktivne oglase.';
        }

        if ($product->code === Product::BUMP) {
            $next = $this->bumpAvailableAt($listing);
            if ($next !== null) {
                return 'Oglas je nedavno podignut. Ponovo može da se podigne od '.$next->format('j. n. Y. \u H:i').'.';
            }
        }

        return null;
    }

    /** Biznis is for firm accounts, or for a firm profile the admin has set up. */
    public function canBuyBiznis(User $user): bool
    {
        return $user->isFirm() || $user->firm()->exists();
    }

    /** When the listing can be bumped again, or null when it can be bumped now. */
    public function bumpAvailableAt(Listing $listing, ?CarbonInterface $now = null): ?Carbon
    {
        $now = Carbon::instance($now ?? now());
        if ($listing->bumped_at === null) {
            return null;
        }

        $next = $listing->bumped_at->copy()->addHours($this->cooldownHours());

        return $next->greaterThan($now) ? $next : null;
    }

    /**
     * Cancels a pending order. Returns false when the order was not pending (already paid or
     * cancelled), so calling it twice is harmless.
     */
    public function cancel(Order $order, ?User $actor = null, string $action = 'order.cancelled'): bool
    {
        $cancelled = DB::transaction(function () use ($order, $actor, $action) {
            /** @var Order|null $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->status !== OrderStatus::Pending) {
                return false;
            }

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor?->getKey(),
            ])->save();

            AuditLog::create([
                'user_id' => $actor?->getKey(),
                'action' => $action,
                'subject_type' => $locked->getMorphClass(),
                'subject_id' => $locked->getKey(),
                'changes' => ['status' => [OrderStatus::Pending->value, OrderStatus::Cancelled->value], 'reference' => $locked->reference],
                'ip' => app()->runningInConsole() ? null : request()->ip(),
            ]);

            return true;
        });

        $order->refresh();

        return $cancelled;
    }

    /** Cancels pending orders older than payments.pending_days. Returns how many were cancelled. */
    public function cancelStale(?CarbonInterface $now = null): int
    {
        $cutoff = Carbon::instance($now ?? now())->subDays((int) config('payments.pending_days', 14));
        $count = 0;

        Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<', $cutoff)
            ->select('id')
            ->chunkById(200, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    if ($this->cancel($order, null, 'order.auto_cancelled')) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** Date after which a pending order is cancelled automatically. */
    public static function expiresAt(Order $order): ?Carbon
    {
        return $order->created_at?->copy()->addDays((int) config('payments.pending_days', 14));
    }

    private function pendingDuplicate(User $user, Product $product, ?Listing $listing): ?Order
    {
        return Order::query()
            ->where('user_id', $user->getKey())
            ->where('product_id', $product->getKey())
            ->where('listing_id', $listing?->getKey())
            ->where('status', OrderStatus::Pending)
            ->latest('id')
            ->first();
    }

    /** The reference ("poziv na broj") is built from the order ID, so it is set right after the insert. */
    private function insert(User $user, Product $product, ?Listing $listing, BuyerDetails $buyer): Order
    {
        $order = Order::create([
            'user_id' => $user->getKey(),
            'product_id' => $product->getKey(),
            'listing_id' => $listing?->getKey(),
            'amount_rsd' => $product->price_rsd,
            'reference' => 'tmp-'.Str::random(24),
            'status' => OrderStatus::Pending,
            ...$buyer->attributes(),
        ]);
        $order->forceFill(['reference' => PaymentReference::forOrderId((int) $order->getKey())])->save();

        return $order;
    }

    /** Private buyers: always in test mode, for real only once the fiscal register works. */
    public static function privateBuyersEnabled(): bool
    {
        return PaymentMode::privatePaymentsEnabled();
    }

    private function cooldownHours(): int
    {
        return max(1, (int) config('payments.bump_cooldown_hours', 24));
    }
}
