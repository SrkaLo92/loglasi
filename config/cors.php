<?php

// The API is used by the mobile apps (no browser, no Origin) and by the site itself (same origin).
// Other websites must not call it from a visitor's browser, so no foreign origin is allowed.

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_filter([
        rtrim((string) env('APP_URL', ''), '/'),
        'https://loglasi.rs',
    ]))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Next-Cursor', 'X-Lg-Count', 'ETag'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
