<?php

namespace App\Services;

use App\Enums\BuyerType;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Mail\OrderPlaced;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\BumpCredits;
use App\Services\Payments\BuyerDetails;
use App\Services\Payments\Effects;
use App\Services\Payments\ProductCatalog;
use App\Support\Firms\FirmCredits;
use App\Support\Firms\FirmPackage;
use App\Support\Payments\PaymentMode;
use App\Support\Payments\PaymentReference;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Orders for the paid features of the price list (config('payments.catalog')), paid by bank
 * transfer; the admin marks an order paid through OrderFulfillment, which applies the effect.
 * A firm's monthly included credits and bought bump packs are used before anything is sold.
 */
class OrderService
{
    /** Active sellable product by code. */
    public function product(string $code): ?Product
    {
        if (! ProductCatalog::isSellable($code)) {
            return null;
        }
        $product = Product::byCode($code);

        return $product && $product->is_active && ! $product->display_only && $product->price_rsd > 0 ? $product : null;
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
        if (! ProductCatalog::needsListing($product)) {
            $listing = null;
        }
        $buyer->validate();
        if ($buyer->type === BuyerType::Private && ! PaymentMode::privatePaymentsEnabled()) {
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
            if ($this->availableCredit($user, $product) !== null) {
                throw new OrderException('Imate uključenu uslugu koju još niste iskoristili. Iskoristite nju, bez plaćanja.', 'credit_available');
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
     * Why this user cannot get this product now, or null when it is allowed. The text is shown to the user.
     */
    public function unavailableReason(User $user, Product $product, ?Listing $listing = null): ?string
    {
        if ($user->isBlocked()) {
            return 'Vaš nalog je blokiran.';
        }
        if (! ProductCatalog::isSellable($product) || ! $product->is_active || $product->display_only || $product->price_rsd <= 0) {
            return 'Ova usluga trenutno nije dostupna.';
        }

        return $this->eligibilityReason($user, $product, $listing);
    }

    /** Rules of the product itself (who, which listing, bump interval), whether it is sold or included. */
    public function eligibilityReason(User $user, Product $product, ?Listing $listing = null): ?string
    {
        if ($user->isBlocked()) {
            return 'Vaš nalog je blokiran.';
        }
        $type = ProductCatalog::type($product);
        if ($type === ProductCatalog::FIRM_PACKAGE) {
            return $this->canBuyFirmPackage($user)
                ? null
                : 'Paketi su za firme. Nalog firme otvara administrator: pošaljite prijavu firme preko strane Kontakt.';
        }
        if ($type === ProductCatalog::SLOTS) {
            return $user->isFirm() && FirmPackage::isActive($user->firm)
                ? 'Vaš paket za firme već određuje broj aktivnih oglasa.'
                : null;
        }
        if ($type === ProductCatalog::BUMP_PACK) {
            return null;
        }

        if ($listing === null) {
            return 'Izaberite oglas.';
        }
        if ((int) $listing->user_id !== (int) $user->getKey()) {
            return 'Uslugu možete da naručite samo za svoj oglas.';
        }
        if (! $listing->isActive()) {
            return 'Usluga je dostupna samo za aktivne oglase.';
        }
        if ($type === ProductCatalog::BUMP) {
            $next = $this->bumpAvailableAt($listing);
            if ($next !== null) {
                return 'Oglas je nedavno podignut. Ponovo može da se podigne od '.$next->format('j. n. Y. \u H:i').'.';
            }
        }

        return null;
    }

    /** Firm packages are for firm accounts, or for a firm profile the admin has set up. */
    public function canBuyFirmPackage(User $user): bool
    {
        return $user->isFirm() || $user->firm()->exists();
    }

    /**
     * Credit that covers this product without paying: the firm's monthly included credit
     * ("firm:bumps", "firm:highlights", "firm:premium_xl") or a bought bump pack ("pack:bumps").
     */
    public function availableCredit(User $user, Product $product): ?string
    {
        $credit = ProductCatalog::credit($product);
        if ($credit === null) {
            return null;
        }
        $firm = $user->isFirm() ? $user->firm : null;
        if ($firm !== null && FirmPackage::isActive($firm) && (FirmCredits::remaining($firm)[$credit] ?? 0) > 0) {
            return 'firm:'.$credit;
        }
        if ($credit === 'bumps' && BumpCredits::remaining($user) > 0) {
            return 'pack:bumps';
        }

        return null;
    }

    /**
     * Uses an included credit for a listing promotion (no order, no payment). Returns the credit
     * source that was used.
     *
     * @throws OrderException when the promotion is not allowed or no credit is left
     */
    public function useCredit(User $user, string $code, Listing $listing): string
    {
        $product = Product::byCode($code);
        if ($product === null || ProductCatalog::credit($product) === null) {
            throw new OrderException('Ova usluga nije uključena u vaš paket.', 'no_credit');
        }

        return DB::transaction(function () use ($user, $product, $listing) {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $reason = $this->eligibilityReason($user, $product, $listing);
            if ($reason !== null) {
                throw new OrderException($reason, 'not_allowed');
            }

            $source = $this->availableCredit($user, $product);
            $used = match ($source) {
                'pack:bumps' => BumpCredits::consume($user),
                null => false,
                default => FirmCredits::consume($user->firm, substr($source, 5)),
            };
            if (! $used) {
                throw new OrderException('Nema više uključenih usluga ovog meseca.', 'no_credit');
            }

            $applied = Effects::apply($product, $user, (int) $listing->getKey());
            AuditLog::create([
                'user_id' => $user->getKey(),
                'action' => 'promotion.credit_used',
                'subject_type' => $listing->getMorphClass(),
                'subject_id' => $listing->getKey(),
                'changes' => ['product' => $product->code, 'credit' => $source, 'effect' => $applied['effect']],
                'ip' => app()->runningInConsole() ? null : request()->ip(),
            ]);

            return $source;
        });
    }

    /** When the listing can be bumped again, or null when it can be bumped now. */
    public function bumpAvailableAt(Listing $listing, ?CarbonInterface $now = null): ?Carbon
    {
        $now = Carbon::instance($now ?? now());
        if ($listing->bumped_at === null) {
            return null;
        }

        $next = $listing->bumped_at->copy()->addHours(max(1, (int) config('payments.bump_cooldown_hours', 24)));

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

    /** Cancels pending orders older than payments.pending_days (7). Returns how many were cancelled. */
    public function cancelStale(?CarbonInterface $now = null): int
    {
        $cutoff = Carbon::instance($now ?? now())->subDays((int) config('payments.pending_days', 7));
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
        return $order->created_at?->copy()->addDays((int) config('payments.pending_days', 7));
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
            'is_test' => PaymentMode::testMode(),
            ...$buyer->attributes(),
        ]);
        $order->forceFill(['reference' => PaymentReference::forOrderId((int) $order->getKey())])->save();

        return $order;
    }
}
