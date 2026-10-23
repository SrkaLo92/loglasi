<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

// Sanctum služi samo za Bearer tokene mobilne aplikacije (bez prijave kolačićem na API-ju).

return [

    'stateful' => [],

    // Prazno: API ne prihvata sesiju sajta, samo token.
    'guard' => [],

    // Access token traje 15 minuta (App\Support\Auth\ApiTokens::ACCESS_MINUTES).
    'expiration' => 15,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
