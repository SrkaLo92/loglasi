<?php

namespace App\Support;

use App\Enums\AccountType;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\Catalog;
use App\Services\Payments\PlusSeller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Admin warning "verovatno firma" (price list of 25 Oct 2026): a private account with more than 50 active
 * listings, 3 or more active listings with the same title, or active listings in services. The admin then
 * moves the account to a firm or takes the listings down; nothing happens automatically. An account with a
 * personal listing limit (set by the admin on purpose) is not flagged for the count.
 */
final class LikelyFirm
{
    public const MAX_ACTIVE = 50;

    public const SAME_TITLE = 3;

    /** @return list<string> why the account looks like a firm (empty: it does not) */
    public static function reasons(User $user): array
    {
        if ($user->account_type !== AccountType::Private) {
            return [];
        }
        $reasons = [];
        $active = self::active()->where('user_id', $user->id);

        $count = (clone $active)->count();
        if ($count > self::MAX_ACTIVE && $user->listing_limit === null && ! $user->listing_unlimited) {
            $reasons[] = $count.' aktivnih oglasa';
        }
        $same = (clone $active)->selectRaw('LOWER(TRIM(title)) AS t, COUNT(*) AS n')->groupBy('t')
            ->having('n', '>=', self::SAME_TITLE)->orderByDesc('n')->first();
        if ($same !== null) {
            $reasons[] = $same->n.' oglasa sa istim naslovom';
        }
        $services = self::serviceCategoryIds();
        if ($services !== [] && (clone $active)->whereIn('category_id', $services)->exists()) {
            $reasons[] = 'oglasi za usluge';
        }

        return $reasons;
    }

    /** Private accounts that match at least one rule (admin filter). */
    public static function scope(Builder $users): Builder
    {
        $services = self::serviceCategoryIds();

        return $users->where('account_type', AccountType::Private->value)->where(function (Builder $q) use ($services) {
            $q->where(fn (Builder $big) => $big->whereNull('listing_limit')->where('listing_unlimited', false)->whereRaw('(SELECT COUNT(*) FROM listings l WHERE l.user_id = users.id AND l.deleted_at IS NULL AND l.status IN (?, ?) AND (l.expires_at IS NULL OR l.expires_at > ?)) > ?', [
                ListingStatus::Pending->value, ListingStatus::Published->value, now(), self::MAX_ACTIVE,
            ]))->orWhereExists(fn (QueryBuilder $sub) => self::activeRaw($sub)
                ->selectRaw('1')->groupByRaw('LOWER(TRIM(l.title))')->havingRaw('COUNT(*) >= ?', [self::SAME_TITLE]));
            if ($services !== []) {
                $q->orWhereExists(fn (QueryBuilder $sub) => self::activeRaw($sub)->selectRaw('1')->whereIn('l.category_id', $services));
            }
        });
    }

    /** @return Builder<Listing> */
    private static function active(): Builder
    {
        return Listing::query()
            ->whereIn('status', [ListingStatus::Pending, ListingStatus::Published])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    private static function activeRaw(QueryBuilder $sub): QueryBuilder
    {
        return $sub->from('listings', 'l')->whereColumn('l.user_id', 'users.id')->whereNull('l.deleted_at')
            ->whereIn('l.status', [ListingStatus::Pending->value, ListingStatus::Published->value])
            ->where(fn ($q) => $q->whereNull('l.expires_at')->orWhere('l.expires_at', '>', now()));
    }

    /** @return list<int> the services category and everything under it */
    private static function serviceCategoryIds(): array
    {
        return once(function () {
            $ids = [];
            $catalog = app(Catalog::class);
            foreach (Category::query()->whereIn('slug', array_diff(PlusSeller::excludedSlugs(), ['poslovi']))->get() as $category) {
                $ids = array_merge($ids, $catalog->descendantIds($category));
            }

            return array_values(array_unique($ids));
        });
    }
}
