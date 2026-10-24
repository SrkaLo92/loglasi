<?php

namespace App\Jobs\Notifications;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\Device;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\SavedSearchMatch;
use App\Models\User;
use App\Notifications\SavedSearchDigestNotification;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends one digest per user for the saved searches that have unsent matches and were
 * not notified in the last cooldown_hours (max one alert per search per day). Searches
 * still in cooldown keep their matches for a later run. Works in chunks of searches,
 * a constant number of queries per chunk.
 */
class SendSavedSearchDigests implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    private const CHUNK = 200;

    public function handle(): void
    {
        $cooldownUntil = now()->subHours((int) config('notifications.saved_searches.cooldown_hours', 24));

        // Covered by the (notified_at, saved_search_id) index.
        // Matches stored while this job runs wait for the next run.
        $maxMatchId = (int) SavedSearchMatch::query()->max('id');

        $pendingSearchIds = SavedSearchMatch::query()->whereNull('notified_at')
            ->distinct()->orderBy('saved_search_id')->pluck('saved_search_id');

        foreach ($pendingSearchIds->chunk(self::CHUNK) as $ids) {
            $searches = SavedSearch::query()
                ->whereKey($ids->all())
                ->where('notify', true)
                ->where(fn (Builder $q) => $q->whereNull('last_notified_at')->orWhere('last_notified_at', '<=', $cooldownUntil))
                ->select(['id', 'user_id', 'name', 'query', 'last_notified_at'])
                ->get();

            if ($searches->isNotEmpty()) {
                $this->sendChunk($searches, $maxMatchId);
            }
        }

        // Sent matches are kept a while so a re-published listing is not announced again.
        SavedSearchMatch::query()
            ->whereNotNull('notified_at')
            ->where('created_at', '<', now()->subDays((int) config('notifications.saved_searches.keep_days', 30)))
            ->delete();
    }

    /** @param  Collection<int, SavedSearch>  $searches */
    private function sendChunk(Collection $searches, int $maxMatchId): void
    {
        $searchIds = $searches->modelKeys();

        $users = User::query()
            ->whereKey($searches->pluck('user_id')->unique()->all())
            ->where('status', '!=', UserStatus::Blocked->value)
            ->get()
            ->keyBy('id');
        $withDevices = array_flip(Device::query()->whereIn('user_id', $users->keys()->all())->distinct()->pluck('user_id')->all());

        // Pending matches whose listing is still active, as plain rows (no models for thousands of rows).
        $rows = DB::table('saved_search_matches as m')
            ->join('listings as l', 'l.id', '=', 'm.listing_id')
            ->whereIn('m.saved_search_id', $searchIds)
            ->whereNull('m.notified_at')
            ->where('m.id', '<=', $maxMatchId)
            ->where('l.status', ListingStatus::Published->value)
            ->whereNull('l.deleted_at')
            ->where(fn ($q) => $q->whereNull('l.expires_at')->orWhere('l.expires_at', '>', now()))
            ->orderByDesc('m.listing_id')
            ->get(['m.saved_search_id', 'm.listing_id']);

        $perSearch = (int) config('notifications.saved_searches.listings_per_search', 5);
        $totals = [];
        $shown = [];
        foreach ($rows as $row) {
            $id = (int) $row->saved_search_id;
            $totals[$id] = ($totals[$id] ?? 0) + 1;
            if ($totals[$id] <= $perSearch) {
                $shown[$id][] = (int) $row->listing_id;
            }
        }

        $listings = Listing::query()
            ->whereKey(array_values(array_unique(array_merge([], ...array_values($shown)))))
            ->select(['id', 'title', 'slug', 'price', 'currency', 'location_id'])
            ->with('location:id,name')
            ->get()
            ->keyBy('id');

        $sentSearchIds = [];

        foreach ($searches->groupBy('user_id') as $userId => $userSearches) {
            $user = $users->get($userId);
            if ($user === null || ! NotificationPreferences::allows($user, NotificationPreferences::SAVED_SEARCHES)) {
                continue; // matches are marked below, nothing piles up
            }

            $digest = [];
            foreach ($userSearches as $search) {
                if (! isset($totals[$search->id])) {
                    continue;
                }

                $digest[] = [
                    'id' => $search->id,
                    'name' => $search->name ?: 'Sačuvana pretraga',
                    'url' => $search->url(),
                    'total' => $totals[$search->id],
                    'listings' => collect($shown[$search->id])->map(fn (int $id) => $listings->get($id))->filter()
                        ->map(fn (Listing $l) => [
                            'title' => $l->title,
                            'price' => $l->priceLabel(),
                            'location' => $l->location?->name,
                            'url' => $l->url(),
                        ])->values()->all(),
                ];
                $sentSearchIds[] = $search->id;
            }

            if ($digest !== []) {
                $user->notify(new SavedSearchDigestNotification($digest, isset($withDevices[$user->id])));
            }
        }

        // Everything looked at is done: sent, inactive listing, or alerts turned off.
        // One update per chunk of searches (index saved_search_id, listing_id).
        SavedSearchMatch::query()
            ->whereIn('saved_search_id', $searchIds)
            ->whereNull('notified_at')
            ->where('id', '<=', $maxMatchId)
            ->update(['notified_at' => now()]);

        if ($sentSearchIds !== []) {
            SavedSearch::query()->whereKey($sentSearchIds)->update(['last_notified_at' => now()]);
        }
    }
}
