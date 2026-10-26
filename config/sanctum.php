<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

// Sanctum is used only for the mobile app's Bearer tokens (no cookie login on the API).

return [

    'stateful' => [],

    // Empty: the API does not accept the site session, only a token.
    'guard' => [],

    // The access token lasts 15 minutes (App\Support\Auth\ApiTokens::ACCESS_MINUTES).
    'expiration' => 15,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
