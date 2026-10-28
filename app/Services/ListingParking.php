<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Support\ListingLimits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * When an admin lowers an account's limit (ends a "no limit" deal), the active listings over the new limit
 * go to drafts, oldest first, marked with parked_at. They keep their address and come back without a new
 * review when there is room again: from the admin ("Vrati oglase iz nacrta") or by the owner submitting
 * the draft. Parked drafts are not pruned and do not count toward the draft cap.
 */
class ListingParking
{
    public function __construct(private readonly Posting $posting) {}

    /** How many active listings are over the given limit (null = unlimited). */
    public function excess(User $user, ?int $limit): int
    {
        return $limit === null ? 0 : max(0, ListingLimits::activeCount($user) - $limit);
    }

    /** Moves the active listings over the current limit to drafts. Returns how many were moved. */
    public function parkExcess(User $user): int
    {
        $count = $this->excess($user, ListingLimits::monthlyLimit($user));
        if ($count === 0) {
            return 0;
        }

        return DB::transaction(function () use ($user, $count) {
            // Oldest published first; waiting for review counts as newest.
            $listings = $this->active($user)->orderByRaw('published_at IS NULL')->orderBy('published_at')->orderBy('id')
                ->limit($count)->get();
            foreach ($listings as $listing) {
                $listing->status = ListingStatus::Draft;
                $listing->parked_at = now();
                $listing->save();
            }

            return $listings->count();
        });
    }

    public function parkedCount(User $user): int
    {
        return Listing::query()->where('user_id', $user->id)->where('status', ListingStatus::Draft)->whereNotNull('parked_at')->count();
    }

    /** Brings parked listings back while there is room, most recently published first. Returns how many. */
    public function restore(User $user): int
    {
        $limit = ListingLimits::monthlyLimit($user);
        $room = $limit === null ? PHP_INT_MAX : max(0, $limit - ListingLimits::activeCount($user));
        if ($room === 0) {
            return 0;
        }

        $listings = Listing::query()->where('user_id', $user->id)->where('status', ListingStatus::Draft)->whereNotNull('parked_at')
            ->orderByDesc('published_at')->orderByDesc('id')->limit(min($room, 10000))->get();
        foreach ($listings as $listing) {
            $this->unpark($listing);
        }

        return $listings->count();
    }

    /** Public again for a full listing period; it was approved before it was parked. */
    public function unpark(Listing $listing): void
    {
        $listing->parked_at = null;
        $this->posting->activate($listing);
    }

    /** @return Builder<Listing> */
    private function active(User $user): Builder
    {
        return Listing::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ListingStatus::Pending, ListingStatus::Published])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
