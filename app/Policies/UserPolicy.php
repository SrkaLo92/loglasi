<?php

namespace App\Policies;

use App\Models\User;

/** Ko sme šta sa nalogom. Admin panel koristi block, verifyPhone, changeAccountType i changeRole. */
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

    /** Svoje podatke menja sam korisnik; tuđe samo administrator. */
    public function update(User $actor, User $user): bool
    {
        return $actor->is($user) || $actor->isAdmin();
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->is($user) || ($actor->isAdmin() && ! $user->isAdmin());
    }

    /** Blokiranje: moderator i admin, ali ne sebe i ne administratora. */
    public function block(User $actor, User $user): bool
    {
        return $actor->isStaff() && ! $actor->is($user) && ! $user->isAdmin();
    }

    /** Telefon proverava osoblje, ručno. */
    public function verifyPhone(User $actor, User $user): bool
    {
        return $actor->isStaff();
    }

    /** Nalog firme pravi samo administrator. */
    public function changeAccountType(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    public function changeRole(User $actor, User $user): bool
    {
        return $actor->isAdmin() && ! $actor->is($user);
    }
}
