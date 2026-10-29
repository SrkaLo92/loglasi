<?php

namespace App\Support\Auth;

use App\Models\PersonalAccessToken;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mobile app tokens: access token (Sanctum, 15 minutes) and refresh token (30 days).
 * The refresh token is stored only as a SHA-256 hash and changes on every refresh.
 * A refresh whose answer never reached the phone (network cut, firewall) is retried with the old token:
 * while the new pair is still unused, that retry gets a fresh pair instead of a logout.
 */
class ApiTokens
{
    public const ACCESS_MINUTES = 15;

    public const REFRESH_DAYS = 30;

    /** How long after a swap the old refresh token may be retried while the new pair is unused. */
    public const RETRY_HOURS = 24;

    /**
     * New token pair for a device.
     *
     * @return array{access_token:string, refresh_token:string, expires_in:int}
     */
    public static function issue(User $user, ?string $deviceName = null): array
    {
        return DB::transaction(function () use ($user, $deviceName) {
            [$refresh, $plain] = static::newRefreshToken($user, $deviceName);

            return static::pair($user, $refresh, $plain);
        });
    }

    /**
     * Swaps a refresh token for a new pair. Returns null if the token is not valid.
     * If an already swapped token is used (possible theft), the whole chain of that device is revoked,
     * unless the pair it was swapped for was never used (the phone did not get the answer: retry).
     * Retries are accepted for RETRY_HOURS after the swap.
     *
     * @return array{user:User, tokens:array{access_token:string, refresh_token:string, expires_in:int}}|null
     */
    public static function refresh(string $plain): ?array
    {
        return DB::transaction(function () use ($plain) {
            $token = RefreshToken::where('token_hash', RefreshToken::hashToken($plain))->lockForUpdate()->first();
            if ($token === null) {
                return null;
            }

            if ($token->replaced_by_id !== null) {
                $retry = static::retriedSwap($token);
                if ($retry === null) {
                    static::revokeChain($token);

                    return null;
                }
                $token = $retry;
            }

            $user = User::find($token->user_id);
            if (! $token->isUsable() || $user === null || $user->isBlocked()) {
                return null;
            }

            [$next, $nextPlain] = static::newRefreshToken($user, $token->device_name);

            $token->forceFill(['revoked_at' => now(), 'last_used_at' => now(), 'replaced_by_id' => $next->id])->save();
            PersonalAccessToken::where('refresh_token_id', $token->id)->delete();

            return ['user' => $user, 'tokens' => static::pair($user, $next, $nextPlain)];
        });
    }

    /**
     * The old token of a swap whose new pair was never used: the unused pair is dropped and the old token
     * can be swapped again. Null when it looks like reuse by someone else.
     */
    private static function retriedSwap(RefreshToken $token): ?RefreshToken
    {
        $next = RefreshToken::query()->lockForUpdate()->find($token->replaced_by_id);
        $swappedAt = $token->last_used_at ?? $token->revoked_at;
        $unused = $next !== null && $next->replaced_by_id === null && $next->revoked_at === null && $next->last_used_at === null
            && ! PersonalAccessToken::where('refresh_token_id', $next->id)->whereNotNull('last_used_at')->exists();
        if (! $unused || $swappedAt === null || $swappedAt->lt(now()->subHours(self::RETRY_HOURS)) || $token->expires_at?->isPast()) {
            return null;
        }

        $next->forceFill(['revoked_at' => now()])->save();
        PersonalAccessToken::where('refresh_token_id', $next->id)->delete();
        $token->forceFill(['revoked_at' => null, 'replaced_by_id' => null]);

        return $token;
    }

    /** Logs out one device: the refresh token from the request and/or the one the current access token is tied to. */
    public static function revokeDevice(User $user, ?PersonalAccessToken $current, ?string $plainRefresh): void
    {
        $ids = [];
        if ($current?->refresh_token_id) {
            $ids[] = $current->refresh_token_id;
        }
        if ($plainRefresh !== null && $plainRefresh !== '') {
            $id = $user->refreshTokens()->where('token_hash', RefreshToken::hashToken($plainRefresh))->value('id');
            if ($id) {
                $ids[] = $id;
            }
        }

        if ($ids !== []) {
            $user->refreshTokens()->whereIn('id', $ids)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            PersonalAccessToken::whereIn('refresh_token_id', $ids)->delete();
        }

        $current?->delete();
    }

    /** Logs out of every device (also after a password change or a block). */
    public static function revokeAll(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $user->tokens()->delete();
    }

    /** @return array{0:RefreshToken, 1:string} */
    private static function newRefreshToken(User $user, ?string $deviceName): array
    {
        $plain = Str::random(64);

        $token = $user->refreshTokens()->create([
            'token_hash' => RefreshToken::hashToken($plain),
            'device_name' => $deviceName !== null ? Str::limit($deviceName, 120, '') : null,
            'expires_at' => now()->addDays(self::REFRESH_DAYS),
        ]);

        return [$token, $plain];
    }

    /** @return array{access_token:string, refresh_token:string, expires_in:int} */
    private static function pair(User $user, RefreshToken $refresh, string $plainRefresh): array
    {
        $access = $user->createToken($refresh->device_name ?: 'mobilna aplikacija', ['*'], now()->addMinutes(self::ACCESS_MINUTES));
        $access->accessToken->forceFill(['refresh_token_id' => $refresh->id])->save();

        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $plainRefresh,
            'expires_in' => self::ACCESS_MINUTES * 60,
        ];
    }

    /** Revokes the token and every token made from it. */
    private static function revokeChain(RefreshToken $token): void
    {
        $ids = [];
        $current = $token;
        while ($current !== null && ! in_array($current->id, $ids, true) && count($ids) < 1000) {
            $ids[] = $current->id;
            $current = $current->replaced_by_id ? RefreshToken::find($current->replaced_by_id) : null;
        }

        RefreshToken::whereIn('id', $ids)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        PersonalAccessToken::whereIn('refresh_token_id', $ids)->delete();
    }
}
