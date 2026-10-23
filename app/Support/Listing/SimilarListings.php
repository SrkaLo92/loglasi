<?php

namespace App\Support\Listing;

use App\Http\Api\ListingFormatter;
use App\Models\Category;
use App\Models\Listing;
use App\Services\Catalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Similar active listings, featured first, then the newest (indexed queries, cached 10 minutes).
 * API: up to 10, same category (with subcategories), then the parent category.
 * Site: 4–8 cards; the parent levels are used only while the category itself has fewer than 4.
 */
class SimilarListings
{
    public const LIMIT = 10;

    public const WEB_MIN = 4;

    public const WEB_MAX = 8;

    private const TTL = 600;

    private const UNION_MAX = 12;

    /** @return Collection<int, Listing> */
    public static function for(Listing $listing, int $limit = self::LIMIT): Collection
    {
        return static::load(Cache::remember('listing.similar.'.$listing->id.'.'.$limit, self::TTL, fn () => static::ids($listing, $limit)));
    }

    /**
     * Cards for the listing page and the category they come from (for the "Svi oglasi: …" link).
     *
     * @return array{listings: Collection<int, Listing>, category: ?Category}
     */
    public static function forPage(Listing $listing): array
    {
        $catalog = app(Catalog::class);
        $category = $catalog->category((int) $listing->category_id);
        if ($category === null) {
            return ['listings' => new Collection, 'category' => null];
        }

        [$ids, $categoryId] = Cache::remember('listing.similar_page.'.$listing->id, self::TTL, function () use ($listing, $catalog, $category) {
            $ids = [];
            $from = $category->id;
            $chain = array_reverse($catalog->breadcrumbs($category)->all()); // deepest first

            foreach ($chain as $i => $level) {
                $need = self::WEB_MAX - count($ids);
                if ($need <= 0) {
                    break;
                }
                $found = static::idsIn($catalog->descendantIds($level), array_merge([$listing->id], $ids), $need);
                if ($found !== []) {
                    $from = $level->id;
                }
                $ids = array_merge($ids, $found);
                // Enough for one row from the category itself: do not mix in the parent.
                if ($i === 0 && count($ids) >= self::WEB_MIN) {
                    break;
                }
            }

            return [$ids, $from];
        });

        return ['listings' => static::load($ids), 'category' => $catalog->category((int) $categoryId)];
    }

    /** @return list<int> */
    public static function ids(Listing $listing, int $limit = self::LIMIT): array
    {
        $catalog = app(Catalog::class);
        $category = $catalog->category((int) $listing->category_id);
        if ($category === null) {
            return [];
        }

        $groups = [$catalog->descendantIds($category)];
        if ($category->parent_id && ($parent = $catalog->category((int) $category->parent_id))) {
            $groups[] = $catalog->descendantIds($parent);
        }

        $ids = [];
        foreach ($groups as $categoryIds) {
            $need = $limit - count($ids);
            if ($need <= 0) {
                break;
            }
            $ids = array_merge($ids, static::idsIn($categoryIds, array_merge([$listing->id], $ids), $need));
        }

        return $ids;
    }

    /**
     * Featured first, then the newest by bumped_at. Built so every part runs on an index:
     * featured listings are few (featured_until index), the rest is one LIMITed index
     * scan per category on (status, category_id, bumped_at), merged in a UNION ALL.
     *
     * @return list<int>
     */
    private static function idsIn(array $categoryIds, array $exclude, int $limit): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if ($categoryIds === [] || $limit <= 0) {
            return [];
        }

        $featured = Listing::query()->active()
            ->whereIn('category_id', $categoryIds)
            ->where('featured_until', '>', now())
            ->whereNotIn('id', $exclude)
            ->orderByDesc('bumped_at')->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $need = $limit - count($featured);
        if ($need <= 0) {
            return $featured;
        }
        $exclude = array_merge($exclude, $featured);

        // A big parent has many subcategories; past this, one range scan + filesort is cheaper.
        $perCategory = count($categoryIds) <= self::UNION_MAX ? $categoryIds : [null];
        $union = null;
        foreach ($perCategory as $categoryId) {
            $part = Listing::query()->active()
                ->select(['id', 'bumped_at'])
                ->when($categoryId === null, fn ($q) => $q->whereIn('category_id', $categoryIds), fn ($q) => $q->where('category_id', $categoryId))
                ->whereNotIn('id', $exclude)
                ->orderByDesc('bumped_at')->orderByDesc('id')
                ->limit($need)
                ->toBase();
            $union = $union === null ? $part : $union->unionAll($part);
        }

        $rest = DB::query()->fromSub($union, 'u')
            ->orderByDesc('u.bumped_at')->orderByDesc('u.id')
            ->limit($need)
            ->pluck('u.id')->map(fn ($id) => (int) $id)->all();

        return array_merge($featured, $rest);
    }

    /** @return Collection<int, Listing> in the given order */
    private static function load(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }
        $found = Listing::query()->active()->whereIn('id', $ids)->with(ListingFormatter::CARD_RELATIONS)->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn ($id) => $found[$id] ?? null, $ids))));
    }

    /**
     * Newest first (bumped_at is set on publish and on every bump), id as a tiebreaker.
     * Matches the (user_id, status, bumped_at) and (status, category_id, bumped_at) indexes.
     */
    public static function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('listings.bumped_at')->orderByDesc('listings.id');
    }
}
