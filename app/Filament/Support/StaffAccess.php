<?php

namespace App\Filament\Support;

use App\Models\User;
use BackedEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Resource access without model policies: staff (admin, moderator) or admin only.
 * Set `protected static bool $adminOnly = true;` for settings, prices, roles.
 * Read-only resources set `$readOnly = true`.
 */
trait StaffAccess
{
    protected static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected static function allowed(): bool
    {
        $user = static::actor();

        if ($user === null || ! $user->isStaff()) {
            return false;
        }

        return ! (property_exists(static::class, 'adminOnly') && static::$adminOnly) || $user->isAdmin();
    }

    protected static function writable(): bool
    {
        return static::allowed() && ! (property_exists(static::class, 'readOnly') && static::$readOnly);
    }

    /** Replaces the policy lookup: every resource action goes through here. */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof BackedEnum ? $action->value : ($action instanceof UnitEnum ? $action->name : $action);

        $ok = match ($action) {
            'viewAny', 'view' => static::allowed(),
            'forceDelete', 'forceDeleteAny', 'replicate' => false,
            default => static::writable(),
        };

        return $ok ? Response::allow() : Response::deny();
    }
}
