<?php

namespace App\Jobs\Notifications;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\SavedSearchMatch;
use App\Services\SavedSearchMatcher;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Matches one chunk of newly published listings against every saved search with alerts on
 * and stores the hits in saved_search_matches (duplicates ignored). Two queries for the
 * listings and their field values, one for the searches, one insert.
 */
class MatchSavedSearches implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param  list<int>  $listingIds */
    public function __construct(public array $listingIds) {}

    public function handle(SavedSearchMatcher $matcher): void
    {
        $listings = Listing::query()
            ->whereKey($this->listingIds)
            ->where('status', ListingStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->select(['id', 'user_id', 'category_id', 'location_id', 'title', 'description', 'price_eur'])
            ->with('fieldValues:id,listing_id,field_id,value,value_num')
            ->get();

        if ($listings->isEmpty()) {
            return;
        }

        $searches = SavedSearch::query()
            ->where('notify', true)
            ->whereHas('user', fn (Builder $u) => $u->where('status', '!=', UserStatus::Blocked->value))
            ->select(['id', 'user_id', 'query'])
            ->lazyById(1000);

        $now = now();
        $rows = [];
        foreach ($matcher->match($listings, $searches) as $searchId => $ids) {
            foreach ($ids as $listingId) {
                $rows[] = ['saved_search_id' => $searchId, 'listing_id' => $listingId, 'created_at' => $now];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            SavedSearchMatch::query()->insertOrIgnore($chunk);
        }
    }
}
