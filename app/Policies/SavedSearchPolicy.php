<?php

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;

/** A saved search belongs to one user; nobody else reads or changes it. */
class SavedSearchPolicy
{
    public function update(User $user, SavedSearch $search): bool
    {
        return $search->user_id === $user->id;
    }

    public function delete(User $user, SavedSearch $search): bool
    {
        return $search->user_id === $user->id;
    }
}
