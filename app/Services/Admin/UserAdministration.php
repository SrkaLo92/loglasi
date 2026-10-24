<?php

namespace App\Services\Admin;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Account actions for the admin panel and the admin API. Permissions are checked by the caller (UserPolicy). */
class UserAdministration
{
    public function verifyPhone(User $user): User
    {
        if (blank($user->phone)) {
            throw new DomainException('Korisnik nema unet broj telefona.');
        }

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
            AuditLog::record('user.verify_phone', $user, ['phone' => $user->phone]);
        }

        return $user;
    }

    public function unverifyPhone(User $user): User
    {
        $user->forceFill(['phone_verified_at' => null])->save();
        AuditLog::record('user.unverify_phone', $user, ['phone' => $user->phone]);

        return $user;
    }

    /** Blocks the account and signs it out of the app (tokens are revoked). */
    public function block(User $user): User
    {
        DB::transaction(function () use ($user) {
            $user->forceFill(['status' => UserStatus::Blocked])->save();
            $user->tokens()->delete();
            $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });

        AuditLog::record('user.block', $user);

        return $user;
    }

    public function unblock(User $user): User
    {
        $user->forceFill(['status' => UserStatus::Active])->save();
        AuditLog::record('user.unblock', $user);

        return $user;
    }

    /** Firm account: creates the firms row when it does not exist yet. */
    public function makeFirm(User $user, ?string $firmName = null): Firm
    {
        return DB::transaction(function () use ($user, $firmName) {
            $firm = $user->firm;

            if ($firm === null) {
                $firm = new Firm(['name' => trim((string) $firmName) !== '' ? trim($firmName) : $user->name]);
                $firm->user()->associate($user);
                $firm->save();
                $user->setRelation('firm', $firm);
            }

            $user->forceFill(['account_type' => AccountType::Firm])->save();
            AuditLog::record('user.make_firm', $user, ['firm_id' => $firm->id]);

            return $firm;
        });
    }

    /** Back to a private account. The firm profile stays saved (hidden while the account is private). */
    public function makePrivate(User $user): User
    {
        $user->forceFill(['account_type' => AccountType::Private])->save();
        AuditLog::record('user.make_private', $user);

        return $user;
    }

    public function changeRole(User $user, Role $role): User
    {
        $before = $user->role;

        if ($before === Role::Admin && $role !== Role::Admin && User::query()->where('role', Role::Admin)->count() <= 1) {
            throw new DomainException('Sajt mora da ima bar jednog administratora.');
        }

        $user->forceFill(['role' => $role])->save();
        AuditLog::record('user.change_role', $user, ['role' => [$before?->value, $role->value]]);

        return $user;
    }
}
