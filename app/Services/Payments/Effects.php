<?php

namespace App\Services\Payments;

use App\Enums\AccountType;
use App\Exceptions\OrderException;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Firms\FirmPackage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Applies and takes back what a product does. Callers hold a transaction; rows are locked here.
 *
 * apply() returns the audit data plus the period this purchase added (starts/ends), which a
 * pro-rata refund later uses. revert() takes back the unused part of that period.
 */
final class Effects
{
    /**
     * @return array{effect: array<string, mixed>, starts: ?CarbonInterface, ends: ?CarbonInterface}
     *
     * @throws OrderException when the effect cannot be applied
     */
    public static function apply(Product $product, User $user, ?int $listingId, ?int $orderId = null): array
    {
        return match (ProductCatalog::type($product)) {
            ProductCatalog::BUMP => self::bump(self::lockListing($listingId)),
            ProductCatalog::PROMOTION => self::promote($product, self::lockListing($listingId), $user, $orderId),
            ProductCatalog::BUMP_PACK => self::bumpPack($product, $user, $orderId),
            ProductCatalog::SLOTS => self::slots($product, $user, $orderId),
            ProductCatalog::FIRM_PACKAGE => self::firmPackage($product, $user),
            ProductCatalog::PLUS_SELLER => self::plusSeller($product, $user, $orderId),
            default => throw new OrderException('Za ovu uslugu ne postoji automatska aktivacija.', 'unknown_product'),
        };
    }

    /**
     * Takes back the unused part of an activated order (refund) and returns the share left unused:
     * 0.0 (nothing to take back, e.g. a bump) to 1.0 (nothing was used).
     */
    public static function revert(Order $order, Product $product): float
    {
        $type = ProductCatalog::type($product);

        if ($type === ProductCatalog::BUMP_PACK) {
            $pack = BumpCredits::ofOrder((int) $order->getKey());
            $unused = BumpCredits::revoke((int) $order->getKey());

            return $pack && $pack['granted'] > 0 ? $unused / $pack['granted'] : 0.0;
        }
        if ($type === ProductCatalog::BUMP || $order->service_starts_at === null || $order->service_ends_at === null) {
            return 0.0;
        }

        $share = self::unusedShare($order->service_starts_at, $order->service_ends_at);
        if ($share <= 0) {
            return 0.0;
        }
        // Bumps that came with the product (Brza prodaja, Plus prodavac) and were not used go back too.
        if (! empty(ProductCatalog::definition($product)['bumps']) || $type === ProductCatalog::PLUS_SELLER) {
            BumpCredits::revoke((int) $order->getKey());
        }
        $remaining = (int) max(0, $order->service_ends_at->getTimestamp() - max(now()->getTimestamp(), $order->service_starts_at->getTimestamp()));

        if ($type === ProductCatalog::PROMOTION && $order->listing_id !== null) {
            $listing = Listing::withTrashed()->whereKey($order->listing_id)->lockForUpdate()->first();
            if ($listing !== null) {
                foreach ((array) (ProductCatalog::definition($product)['columns'] ?? []) as $column) {
                    if ($listing->{$column} !== null) {
                        $listing->{$column} = self::shorten($listing->{$column}, $remaining);
                    }
                }
                $listing->save();
            }
        } elseif ($type === ProductCatalog::SLOTS) {
            ListingSlots::endForOrder((int) $order->getKey());
        } elseif ($type === ProductCatalog::PLUS_SELLER) {
            PlusSeller::endForOrder((int) $order->getKey(), self::shorten($order->service_ends_at, $remaining));
        } elseif ($type === ProductCatalog::FIRM_PACKAGE) {
            $firm = Firm::query()->where('user_id', $order->user_id)->lockForUpdate()->first();
            if ($firm?->package_ends_at !== null) {
                $firm->forceFill(['package_ends_at' => self::shorten($firm->package_ends_at, $remaining)])->save();
            }
        }

        return $share;
    }

    /** Whole unused days / days of the period (0..1). The day in progress counts as used. */
    public static function unusedShare(CarbonInterface $starts, CarbonInterface $ends): float
    {
        $total = max(1, (int) round(($ends->getTimestamp() - $starts->getTimestamp()) / 86400));
        $from = max(now()->getTimestamp(), $starts->getTimestamp());
        $unused = (int) floor(max(0, $ends->getTimestamp() - $from) / 86400);

        return min(1.0, $unused / $total);
    }

    private static function shorten(CarbonInterface $until, int $seconds): Carbon
    {
        $new = Carbon::instance($until)->subSeconds($seconds);

        return $new->lessThan(now()) ? now() : $new;
    }

    private static function bump(Listing $listing): array
    {
        $before = $listing->bumped_at;
        $listing->forceFill(['bumped_at' => now()])->save();

        return [
            'effect' => ['listing_id' => $listing->getKey(), 'bumped_at' => [$before?->toIso8601String(), $listing->bumped_at->toIso8601String()]],
            'starts' => now(),
            'ends' => null,
        ];
    }

    private static function promote(Product $product, Listing $listing, ?User $user = null, ?int $orderId = null): array
    {
        $days = max(1, ProductCatalog::days($product));
        $columns = (array) (ProductCatalog::definition($product)['columns'] ?? []);
        $effect = ['listing_id' => $listing->getKey()];
        $starts = $ends = null;

        foreach ($columns as $column) {
            $before = $listing->{$column};
            $from = $before !== null && $before->isFuture() ? Carbon::instance($before) : now();
            $listing->{$column} = $from->copy()->addDays($days);
            $effect[$column] = [$before?->toIso8601String(), $listing->{$column}->toIso8601String()];
            // The period this order added (the first column; all columns move together).
            if ($starts === null) {
                [$starts, $ends] = [$from->copy(), $listing->{$column}->copy()];
            }
        }
        $listing->save();

        // Brza prodaja: the highlight plus bumps to use later.
        $bumps = (int) (ProductCatalog::definition($product)['bumps'] ?? 0);
        if ($bumps > 0 && $user !== null) {
            $effect['bump_credits_id'] = BumpCredits::grant($user, $bumps, $orderId);
            $effect['bumps'] = $bumps;
        }

        return ['effect' => $effect, 'starts' => $starts, 'ends' => $ends];
    }

    /** Plus prodavac: a term after the running one, and bumps for each started 30 days. */
    private static function plusSeller(Product $product, User $user, ?int $orderId): array
    {
        $days = max(1, ProductCatalog::days($product));
        [$starts, $ends] = PlusSeller::grant($user, $days, $orderId);
        $effect = ['plus_seller' => [$starts->toIso8601String(), $ends->toIso8601String()]];

        $bumps = (int) (ProductCatalog::definition($product)['bumps_per_month'] ?? 0) * (int) ceil($days / 30);
        if ($bumps > 0) {
            $effect['bump_credits_id'] = BumpCredits::grant($user, $bumps, $orderId);
            $effect['bumps'] = $bumps;
        }

        return ['effect' => $effect, 'starts' => $starts, 'ends' => $ends];
    }

    private static function bumpPack(Product $product, User $user, ?int $orderId): array
    {
        $bumps = max(1, (int) (ProductCatalog::definition($product)['bumps'] ?? 10));
        $id = BumpCredits::grant($user, $bumps, $orderId);

        return ['effect' => ['bump_credits_id' => $id, 'bumps' => $bumps], 'starts' => now(), 'ends' => null];
    }

    private static function slots(Product $product, User $user, ?int $orderId): array
    {
        $slots = max(1, ProductCatalog::slots($product));
        $starts = now();
        $ends = $starts->copy()->addDays(max(1, ProductCatalog::days($product)));
        $id = ListingSlots::grant($user, $slots, $starts, $ends, $orderId);

        return ['effect' => ['listing_slot_grant_id' => $id, 'slots' => $slots, 'ends_at' => $ends->toIso8601String()], 'starts' => $starts, 'ends' => $ends];
    }

    /** Firm package; the account becomes a firm account only when the admin has approved the firm. */
    private static function firmPackage(Product $product, User $user): array
    {
        $definition = ProductCatalog::definition($product);
        /** @var User $locked */
        $locked = User::withTrashed()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

        $firm = Firm::query()->where('user_id', $locked->getKey())->lockForUpdate()->first();
        if ($firm === null) {
            if (! $locked->isFirm()) {
                throw new OrderException('Paket za firme može da se uključi samo za firmu. Prvo otvorite profil firme za ovaj nalog.', 'firm_required');
            }
            $firm = new Firm(['name' => $locked->name]);
            $firm->forceFill(['user_id' => $locked->getKey()])->save();
        }

        $before = $firm->package_ends_at;
        FirmPackage::grant($firm, $definition['package'], $definition['period']);
        $firm->refresh();

        $ends = $firm->package_ends_at->copy();
        $starts = $definition['period'] === 'yearly' ? $ends->copy()->subYear() : $ends->copy()->subMonth();

        $effect = [
            'firm_id' => $firm->getKey(),
            'package' => $definition['package'].'_'.$definition['period'],
            'package_ends_at' => [$before?->toIso8601String(), $ends->toIso8601String()],
        ];
        if (! $locked->isFirm() && $firm->verified_at !== null) {
            $locked->forceFill(['account_type' => AccountType::Firm])->save();
            $effect['account_type'] = [AccountType::Private->value, AccountType::Firm->value];
        }

        return ['effect' => $effect, 'starts' => $starts, 'ends' => $ends];
    }

    /** @throws OrderException when the listing was deleted */
    private static function lockListing(?int $listingId): Listing
    {
        $listing = $listingId === null ? null : Listing::query()->whereKey($listingId)->lockForUpdate()->first();
        if ($listing === null) {
            throw new OrderException('Oglas za ovu narudžbinu je obrisan, usluga ne može da se uključi.', 'listing_missing');
        }

        return $listing;
    }
}
