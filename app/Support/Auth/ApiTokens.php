<?php

namespace App\Support\Auth;

use App\Models\PersonalAccessToken;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tokeni mobilne aplikacije: access token (Sanctum, 15 minuta) i refresh token (30 dana).
 * Refresh token se čuva samo kao SHA-256 heš i menja se pri svakom osvežavanju.
 */
class ApiTokens
{
    public const ACCESS_MINUTES = 15;

    public const REFRESH_DAYS = 30;

    /**
     * Novi par tokena za uređaj.
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
     * Zamena refresh tokena za novi par. Vraća null ako token ne važi.
     * Ako se iskoristi već zamenjen token (moguća krađa), opoziva se ceo lanac tog uređaja.
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
                static::revokeChain($token);

                return null;
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

    /** Odjava jednog uređaja: refresh token iz zahteva i/ili onaj za koji je vezan trenutni access token. */
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

    /** Odjava sa svih uređaja (i posle promene lozinke ili blokiranja). */
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

    /** Opoziva token i sve koji su nastali iz njega. */
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
