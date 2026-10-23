<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

/** Who may change a listing: its owner and staff (admin, moderator). */
class ListingPolicy
{
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
