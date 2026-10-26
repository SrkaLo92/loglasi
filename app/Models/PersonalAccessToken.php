<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/** Mobile app access token (15 minutes), tied to the device's refresh token. */
class PersonalAccessToken extends SanctumToken
{
    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'refresh_token_id'];

    public function refreshToken(): BelongsTo
    {
        return $this->belongsTo(RefreshToken::class);
    }
}
