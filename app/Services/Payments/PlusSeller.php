<?php

namespace App\Services\Payments;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Plus prodavac (price list of 25 Oct 2026): a private seller gets up to 100 active listings (products
 * listing_limit), listings renewed automatically every 15 days and bumps each month. It is for used and
 * own things: listings in the categories of setting plus_seller_excluded_categories (services, jobs)
 * use only the free limit. Terms bought one after another follow each other.
 */
final class PlusSeller
{
    public const EXCLUDED_SETTING = 'plus_seller_excluded_categories';

    /** Category slugs (and everything under them) where Plus prodavac does not count. */
    public const EXCLUDED_DEFAULT = ['usluge', 'poslovi'];

    public const RENEW_DAYS = 15;

    public static function activeUntil(User $user): ?Carbon
    {
        $ends = DB::table('plus_seller_terms')->where('user_id', $user->getKey())
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->max('ends_at');
        if ($ends === null) {
            return null;
        }
        // A following term continues the running one.
        $last = DB::table('plus_seller_terms')->where('user_id', $user->getKey())->where('ends_at', '>', now())->max('ends_at');

        return Carbon::parse($last ?? $ends);
    }

    public static function isActive(User $user): bool
    {
        return self::activeUntil($user) !== null;
    }

    /** Active listing limit while Plus prodavac runs (admin-editable on the product). */
    public static function listingLimit(): int
    {
        $limit = Product::query()->where('code', 'plus_seller_30')->value('listing_limit');

        return max(1, (int) ($limit ?: 100));
    }

    /** Starts after the term that is running (or now) and returns [starts, ends]. */
    public static function grant(User $user, int $days, ?int $orderId = null): array
    {
        $last = DB::table('plus_seller_terms')->where('user_id', $user->getKey())->where('ends_at', '>', now())->max('ends_at');
        $starts = $last !== null ? Carbon::parse($last) : now();
        $ends = $starts->copy()->addDays(max(1, $days));

        DB::table('plus_seller_terms')->insert([
            'user_id' => $user->getKey(),
            'order_id' => $orderId,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$starts, $ends];
    }

    /** Ends an order's term now (refund). */
    public static function endForOrder(int $orderId, CarbonInterface $at): void
    {
        DB::table('plus_seller_terms')->where('order_id', $orderId)->where('ends_at', '>', now())
            ->update(['ends_at' => $at, 'updated_at' => now()]);
    }

    /** @return list<string> */
    public static function excludedSlugs(): array
    {
        $value = Settings::get(self::EXCLUDED_SETTING, self::EXCLUDED_DEFAULT);
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        }

        return array_values(array_filter(array_map(fn ($slug) => trim((string) $slug), (array) $value), 'strlen'));
    }

    /** Whether Plus prodavac counts for a listing in this category (false under services and jobs). */
    public static function appliesTo(?Category $category): bool
    {
        $excluded = self::excludedSlugs();
        for ($node = $category, $depth = 0; $node !== null && $depth < 6; $node = $node->parent, $depth++) {
            if (in_array($node->slug, $excluded, true)) {
                return false;
            }
        }

        return true;
    }
}
