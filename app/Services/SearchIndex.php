<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Support\Search\SearchText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps listing_search in sync with listings.
 *
 * - Listing saved, deleted or restored: sync() (SearchServiceProvider, model events).
 * - Account type of a user changed: syncUser(); category moved: syncCategories().
 * - Expired listings and ended featured periods: refresh(), scheduled every minute.
 * - After bulk changes that skip model events (import, Listing::where()->update()):
 *   syncIds($ids) or `php artisan search:rebuild`.
 */
class SearchIndex
{
    /** Cache key of a number that changes on every write; cached counts include it. */
    public const VERSION_KEY = 'search.version';

    private const CHUNK = 1000;

    public function __construct(private readonly Catalog $catalog) {}

    public static function isActive(Listing $listing): bool
    {
        return $listing->status === ListingStatus::Published
            && ! $listing->trashed()
            && ($listing->expires_at === null || $listing->expires_at->isFuture());
    }

    public function sync(Listing $listing): void
    {
        if (! $listing->exists || ! self::isActive($listing)) {
            $this->remove([$listing->id]);

            return;
        }

        $isFirm = DB::table('users')->where('id', $listing->user_id)->value('account_type') === AccountType::Firm->value;
        DB::table('listing_search')->upsert([$this->row($listing->getAttributes(), $isFirm)], ['id']);
        $this->touch();
    }

    /** @param  list<int>  $ids */
    public function syncIds(array $ids): void
    {
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), self::CHUNK) as $chunk) {
            $rows = $this->activeRows(fn ($q) => $q->whereIn('listings.id', $chunk));
            $keep = array_column($rows, 'id');
            DB::table('listing_search')->whereIn('id', array_diff($chunk, $keep))->delete();
            if ($rows !== []) {
                DB::table('listing_search')->upsert($rows, ['id']);
            }
        }
        $this->touch();
    }

    /** @param  list<int>  $ids */
    public function remove(array $ids): void
    {
        DB::table('listing_search')->whereIn('id', $ids)->delete();
        $this->touch();
    }

    public function syncUser(int $userId, bool $isFirm): void
    {
        DB::table('listing_search')->where('user_id', $userId)->update(['is_firm' => $isFirm]);
        $this->touch();
    }

    /** Category moved in the tree: new paths for its listings and those of its subcategories. */
    public function syncCategories(Category $category): void
    {
        $this->catalog->flush();
        foreach ($this->catalog->descendantIds($category) as $id) {
            [$c1, $c2, $c3] = $this->path($id);
            DB::table('listing_search')->where('category_id', $id)->update(['cat1' => $c1, 'cat2' => $c2, 'cat3' => $c3]);
        }
        $this->touch();
    }

    /** Removes listings that expired and turns off ended featured periods. Returns changed rows. */
    public function refresh(): int
    {
        $now = now();
        $changed = DB::table('listing_search')->whereNotNull('expires_at')->where('expires_at', '<=', $now)->delete();
        // A promotion ended (featured_until = when the rank next changes): rank the row again.
        $ids = DB::table('listing_search')->where('featured', '>', 0)
            ->where(fn ($q) => $q->whereNull('featured_until')->orWhere('featured_until', '<=', $now))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $rows = $this->activeRows(fn ($q) => $q->whereIn('listings.id', $chunk));
            foreach ($rows as $row) {
                DB::table('listing_search')->where('id', $row['id'])->update(['featured' => $row['featured'], 'featured_until' => $row['featured_until']]);
            }
            $changed += count($chunk);
        }

        if ($changed > 0) {
            $this->touch();
        }

        return $changed;
    }

    /** Builds the whole index again. Returns the number of rows. */
    public function rebuild(?callable $progress = null): int
    {
        $this->catalog->flush();
        DB::table('listing_search')->delete();
        $total = 0;
        $max = (int) DB::table('listings')->max('id');

        for ($from = 1; $from <= $max; $from += self::CHUNK) {
            $rows = $this->activeRows(fn ($q) => $q->whereBetween('listings.id', [$from, $from + self::CHUNK - 1]));
            if ($rows !== []) {
                DB::table('listing_search')->insert($rows);
                $total += count($rows);
            }
            $progress && $progress(min($max, $from + self::CHUNK - 1), $max);
        }

        $this->touch();

        return $total;
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    private function touch(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    /** Index rows of the active listings among the ones the scope selects. */
    private function activeRows(callable $scope): array
    {
        $query = DB::table('listings')
            ->join('users', 'users.id', '=', 'listings.user_id')
            ->where('listings.status', ListingStatus::Published->value)
            ->whereNull('listings.deleted_at')
            ->where(fn ($q) => $q->whereNull('listings.expires_at')->orWhere('listings.expires_at', '>', now()))
            ->select('listings.id', 'listings.user_id', 'listings.category_id', 'listings.location_id', 'listings.title',
                'listings.description', 'listings.price_eur', 'listings.featured_until', 'listings.priority_until',
                'listings.top_until', 'listings.bumped_at',
                'listings.published_at', 'listings.created_at', 'listings.expires_at', 'users.account_type');
        $scope($query);

        return $query->get()
            ->map(fn ($l) => $this->row((array) $l, $l->account_type === AccountType::Firm->value))
            ->all();
    }

    private function row(array $l, bool $isFirm): array
    {
        [$c1, $c2, $c3] = $this->path((int) $l['category_id']);
        [$rank, $rankUntil] = self::rank($l);

        return [
            'id' => (int) $l['id'],
            'user_id' => (int) $l['user_id'],
            'category_id' => (int) $l['category_id'],
            'cat1' => $c1,
            'cat2' => $c2,
            'cat3' => $c3,
            'location_id' => $l['location_id'] === null ? null : (int) $l['location_id'],
            'is_firm' => $isFirm,
            'price_eur' => $l['price_eur'],
            'no_price' => $l['price_eur'] === null,
            'featured' => $rank,
            'featured_until' => $rankUntil,
            'sort_at' => $l['bumped_at'] ?? $l['published_at'] ?? $l['created_at'] ?? now(),
            'expires_at' => $l['expires_at'] ?? null,
            'search_text' => SearchText::forListing($l['title'] ?? '', $l['description'] ?? ''),
        ];
    }

    /**
     * Promotion rank (3 TOP, 2 priority, 1 highlighted, 0 none) and when it next changes:
     * the earliest end among the running promotions.
     *
     * @return array{0: int, 1: ?Carbon}
     */
    public static function rank(array $l): array
    {
        $now = now();
        $ends = [];
        foreach (['top_until' => 3, 'priority_until' => 2, 'featured_until' => 1] as $column => $rank) {
            $value = $l[$column] ?? null;
            $value = $value instanceof \DateTimeInterface ? Carbon::instance($value) : ($value ? Carbon::parse($value) : null);
            if ($value !== null && $value->greaterThan($now)) {
                $ends[$rank] = $value;
            }
        }
        if ($ends === []) {
            return [0, null];
        }

        return [max(array_keys($ends)), collect($ends)->min()];
    }

    /** @return array{0: int, 1: ?int, 2: ?int} ids from the root, levels 1 to 3 */
    private function path(int $categoryId): array
    {
        $ids = $this->catalog->breadcrumbs($categoryId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            $ids = [$categoryId];
        }

        return [$ids[0], $ids[1] ?? null, $ids[2] ?? null];
    }
}
