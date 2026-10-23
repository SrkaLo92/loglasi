<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/** Access token mobilne aplikacije (15 minuta), vezan za refresh token uređaja. */
class PersonalAccessToken extends SanctumToken
{
    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'refresh_token_id'];

    public function refreshToken(): BelongsTo
    {
        return $this->belongsTo(RefreshToken::class);
    }
}
