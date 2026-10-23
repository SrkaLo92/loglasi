<?php

namespace App\Jobs\Notifications;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
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
                $this->sendChunk($searches);
            }
        }

        // Sent matches are kept a while so a re-published listing is not announced again.
        SavedSearchMatch::query()
            ->whereNotNull('notified_at')
            ->where('created_at', '<', now()->subDays((int) config('notifications.saved_searches.keep_days', 30)))
            ->delete();
    }

    /** @param  Collection<int, SavedSearch>  $searches */
    private function sendChunk(Collection $searches): void
    {
        $users = User::query()
            ->whereKey($searches->pluck('user_id')->unique()->all())
            ->where('status', '!=', UserStatus::Blocked->value)
            ->get()
            ->keyBy('id');

        $matches = SavedSearchMatch::query()
            ->whereIn('saved_search_id', $searches->modelKeys())
            ->whereNull('notified_at')
            ->orderByDesc('listing_id')
            ->get(['id', 'saved_search_id', 'listing_id']);

        $listings = Listing::query()
            ->whereKey($matches->pluck('listing_id')->unique()->all())
            ->where('status', ListingStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->select(['id', 'title', 'slug', 'price', 'currency', 'location_id'])
            ->with('location:id,name')
            ->get()
            ->keyBy('id');

        $perSearch = (int) config('notifications.saved_searches.listings_per_search', 5);
        $bySearch = $matches->groupBy('saved_search_id');
        $sentSearchIds = [];

        foreach ($searches->groupBy('user_id') as $userId => $userSearches) {
            $user = $users->get($userId);
            if ($user === null || ! NotificationPreferences::allows($user, NotificationPreferences::SAVED_SEARCHES)) {
                continue; // matches are marked below, nothing piles up
            }

            $digest = [];
            foreach ($userSearches as $search) {
                $active = $bySearch->get($search->id, collect())
                    ->map(fn (SavedSearchMatch $m) => $listings->get($m->listing_id))
                    ->filter();

                if ($active->isEmpty()) {
                    continue;
                }

                $digest[] = [
                    'id' => $search->id,
                    'name' => $search->name ?: 'Sačuvana pretraga',
                    'url' => $search->url(),
                    'total' => $active->count(),
                    'listings' => $active->take($perSearch)->map(fn (Listing $l) => [
                        'title' => $l->title,
                        'price' => $l->priceLabel(),
                        'location' => $l->location?->name,
                        'url' => $l->url(),
                    ])->values()->all(),
                ];
                $sentSearchIds[] = $search->id;
            }

            if ($digest !== []) {
                $user->notify(new SavedSearchDigestNotification($digest));
            }
        }

        // Everything looked at is done: sent, inactive listing, or alerts turned off.
        foreach (array_chunk($matches->modelKeys(), 1000) as $chunk) {
            SavedSearchMatch::query()->whereKey($chunk)->update(['notified_at' => now()]);
        }

        if ($sentSearchIds !== []) {
            SavedSearch::query()->whereKey($sentSearchIds)->update(['last_notified_at' => now()]);
        }
    }
}
