<?php

namespace App\Policies;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;

/** Who may see a listing page and use its contact and report actions, and who may change it (owner and staff). */
class ListingPolicy
{
    /** Published (also expired and sold) for everyone; pending, rejected and drafts only for the owner and staff. */
    public function view(?User $user, Listing $listing): bool
    {
        if ($listing->trashed()) {
            return false;
        }
        if ($user && ($user->id === $listing->user_id || $user->isStaff())) {
            return true;
        }
        if ($listing->user === null || $listing->user->isBlocked()) {
            return false;
        }

        return in_array($listing->status, [ListingStatus::Published, ListingStatus::Expired, ListingStatus::Sold], true);
    }

    /** The phone is shown only while the listing is active. */
    public function revealPhone(?User $user, Listing $listing): bool
    {
        return $this->view($user, $listing) && $listing->isActive();
    }

    /** Anyone who can see the listing can report it, except its owner. */
    public function report(?User $user, Listing $listing): bool
    {
        return $this->view($user, $listing) && ($user === null || $user->id !== $listing->user_id);
    }

    public function update(User $user, Listing $listing): bool
    {
        return $user->id === $listing->user_id || $user->isStaff();
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    public function renew(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    /** Statistics and the owner's view of a listing. */
    public function manage(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }
}
