<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Stranice\Cenovnik;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Bank transfer orders: marking an order paid and turning on what was bought.
 * Owned by the payments module; this is the admin branch's copy with the agreed
 * signature markPaid(Order, User): void. The admin panel and API call it through
 * App\Services\Admin\OrderAdministration (status checks, note, audit log).
 *
 * Effects:
 *  - featured_7 / featured_30: listing featured_until extended by the product duration
 *    (from now, or from the current end if it is still featured);
 *  - bump: listing bumped_at = now;
 *  - biznis_12m: firm biznis_until extended by the duration (365 days by default).
 */
class OrderFulfillment
{
    public const DEFAULT_DAYS = [
        Product::FEATURED_7 => 7,
        Product::FEATURED_30 => 30,
        Product::BIZNIS_12M => 365,
    ];

    /**
     * Marks the order paid and applies its effect, in one transaction.
     * Idempotent: an order that is already paid is left as it is.
     *
     * @throws DomainException when the order is cancelled or has no listing for a listing product
     */
    public function markPaid(Order $order, User $by): void
    {
        DB::transaction(function () use ($order, $by) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderStatus::Paid) {
                $order->setRawAttributes($locked->getAttributes(), true);

                return;
            }
            if ($locked->status === OrderStatus::Cancelled) {
                throw new DomainException('Otkazana narudžbina ne može da se označi kao plaćena.');
            }

            $locked->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'marked_paid_by' => $by->getKey(),
            ])->save();

            $this->apply($locked);

            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * paid → activated. Stub until the payments module lands (it owns the status flow);
     * here it only moves the status when the enum knows "activated".
     */
    public function activate(Order $order, User $by): void
    {
        $activated = OrderStatus::tryFrom('activated');
        if ($activated === null) {
            throw new DomainException('Aktiviranje radi modul plaćanja.');
        }
        if ($order->status !== OrderStatus::Paid) {
            throw new DomainException('Aktivirati se može samo plaćena narudžbina.');
        }

        $order->forceFill(['status' => $activated])->save();
    }

    /**
     * Pro-rata refund with a reason. Stub: the payments module computes the amount,
     * issues the refund receipt and sets "refunded".
     */
    public function refund(Order $order, User $by, string $reason): void
    {
        throw new DomainException('Povraćaj novca radi modul plaćanja.');
    }

    /**
     * Applies the effect of a paid order. Returns what changed (for the audit log).
     *
     * @return array<string, mixed>
     */
    public function apply(Order $order): array
    {
        $product = $order->product;
        $code = $product?->code;

        return match ($code) {
            Product::FEATURED_7, Product::FEATURED_30 => $this->feature($order, $this->days($product)),
            Product::BUMP => $this->bump($order),
            Product::BIZNIS_12M => $this->biznis($order, $this->days($product)),
            default => ['effect' => 'none'],
        };
    }

    /** Extends a date by N days: from now, or from the current end when it is still in the future. */
    public static function extend(?CarbonInterface $current, int $days): CarbonInterface
    {
        $from = $current !== null && $current->isFuture() ? $current->copy() : now();

        return $from->addDays($days);
    }

    private function days(Product $product): int
    {
        $days = (int) $product->duration_days;

        return $days > 0 ? $days : (self::DEFAULT_DAYS[$product->code] ?? 0);
    }

    /** @return array<string, mixed> */
    private function feature(Order $order, int $days): array
    {
        $listing = $this->listing($order);
        $until = self::extend($listing->featured_until, $days);
        $listing->forceFill(['featured_until' => $until])->save();

        return ['effect' => 'featured', 'listing_id' => $listing->id, 'featured_until' => $until->toIso8601String()];
    }

    /** @return array<string, mixed> */
    private function bump(Order $order): array
    {
        $listing = $this->listing($order);
        $listing->forceFill(['bumped_at' => now()])->save();

        return ['effect' => 'bump', 'listing_id' => $listing->id];
    }

    /** @return array<string, mixed> */
    private function biznis(Order $order, int $days): array
    {
        $user = $order->user()->firstOrFail();

        // Biznis is a firm package: an order turns the account into a firm if it is not one yet.
        $firm = $user->firm ?? $this->createFirm($user);
        if ($user->account_type !== AccountType::Firm) {
            $user->forceFill(['account_type' => AccountType::Firm])->save();
        }

        $until = self::extend($firm->biznis_until, $days);
        $firm->forceFill(['biznis_until' => $until])->save();
        Cenovnik::forgetFounding();

        return ['effect' => 'biznis', 'firm_id' => $firm->id, 'biznis_until' => $until->toIso8601String()];
    }

    private function listing(Order $order): Listing
    {
        $listing = $order->listing_id ? Listing::withTrashed()->find($order->listing_id) : null;

        if ($listing === null) {
            throw new DomainException('Narudžbina nije vezana za oglas.');
        }

        return $listing;
    }

    private function createFirm(User $user): Firm
    {
        $firm = new Firm(['name' => $user->name]);
        $firm->user()->associate($user);
        $firm->save();
        $user->setRelation('firm', $firm);

        return $firm;
    }
}
