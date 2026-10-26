<?php

namespace App\Policies;

use App\Models\User;

/** Who may do what with an account. The admin panel uses block, verifyPhone, changeAccountType and changeRole. */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) || $actor->isStaff();
    }

    /** Users change their own data; someone else's only an administrator. */
    public function update(User $actor, User $user): bool
    {
        return $actor->is($user) || $actor->isAdmin();
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->is($user) || ($actor->isAdmin() && ! $user->isAdmin());
    }

    /** Blocking: moderator and admin, but not themselves and not an administrator. */
    public function block(User $actor, User $user): bool
    {
        return $actor->isStaff() && ! $actor->is($user) && ! $user->isAdmin();
    }

    /** Staff verify phones by hand. */
    public function verifyPhone(User $actor, User $user): bool
    {
        return $actor->isStaff();
    }

    /** Only an administrator creates a firm account. */
    public function changeAccountType(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    public function changeRole(User $actor, User $user): bool
    {
        return $actor->isAdmin() && ! $actor->is($user);
    }
}
