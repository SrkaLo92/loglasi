<?php

namespace App\Services\Messages;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Services\Notifications\InAppNotifications;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Saved listings ("Omiljeni"). Table favorites (user_id, listing_id, created_at). */
class Favorites
{
    /** Only a live listing can be saved. */
    public function canSave(Listing $listing): bool
    {
        return $listing->isActive();
    }

    /** Idempotent. A new save counts in listing_daily_stats.favorites (not when the owner saves his own listing). */
    public function add(User $user, Listing $listing): bool
    {
        $inserted = DB::table('favorites')->insertOrIgnore([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'created_at' => now(),
        ]) > 0;

        if ($inserted && $listing->user_id !== $user->id) {
            ListingStats::increment($listing->id, 'favorites');
            app(InAppNotifications::class)->favorited($listing, $user);
        }

        return $inserted;
    }

    /** Idempotent, also for a listing that no longer exists. */
    public function remove(User $user, int $listingId): void
    {
        DB::table('favorites')->where('user_id', $user->id)->where('listing_id', $listingId)->delete();
    }

    public function has(User $user, int $listingId): bool
    {
        return DB::table('favorites')->where('user_id', $user->id)->where('listing_id', $listingId)->exists();
    }

    /**
     * Saved listings, newest saved first. Deleted listings and listings that are not public
     * (pending, rejected, draft) are left out; expired and sold ones stay and are marked.
     * Keyset on (favorites.created_at, favorites.listing_id), index favorites_user_created_index.
     *
     * @param  string[]  $with  relations to eager load
     * @return array{items: Collection<int, Listing>, next: ?string}
     */
    public function page(User $user, ?string $cursor, int $perPage, int $page = 1, array $with = []): array
    {
        $after = InboxCursor::decode($cursor);

        $rows = Listing::query()
            ->join('favorites', 'favorites.listing_id', '=', 'listings.id')
            ->where('favorites.user_id', $user->id)
            ->whereIn('listings.status', [ListingStatus::Published->value, ListingStatus::Expired->value, ListingStatus::Sold->value])
            ->when($after, fn ($q) => $q->where(fn ($q) => $q
                ->where('favorites.created_at', '<', $after['at'])
                ->orWhere(fn ($q) => $q->where('favorites.created_at', $after['at'])->where('favorites.listing_id', '<', $after['id']))))
            ->orderByDesc('favorites.created_at')
            ->orderByDesc('favorites.listing_id')
            ->when(! $after && $page > 1, fn ($q) => $q->offset(($page - 1) * $perPage))
            ->limit($perPage + 1)
            ->select('listings.*', 'favorites.created_at as saved_at')
            ->with($with)
            ->get();

        $items = $rows->take($perPage)->values();
        $last = $items->last();

        return [
            'items' => $items,
            'next' => $rows->count() > $perPage && $last ? InboxCursor::encode(CarbonImmutable::parse($last->saved_at), (int) $last->id) : null,
        ];
    }

    /** Ids among $listingIds that $user has saved (one query, for marking hearts on a list). */
    public function savedIds(User $user, array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        return DB::table('favorites')->where('user_id', $user->id)->whereIn('listing_id', $listingIds)->pluck('listing_id')->map(fn ($id) => (int) $id)->all();
    }
}
