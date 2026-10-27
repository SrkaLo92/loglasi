<?php

// Browser security headers (App\Http\Middleware\SecurityHeaders) and upload/password rules.

return [
    // HSTS over HTTPS. Start small on a new domain; one year once HTTPS works everywhere.
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    'hsts_subdomains' => (bool) env('SECURITY_HSTS_SUBDOMAINS', false),

    // Content Security Policy for the site's HTML pages. Inline scripts run only with the per-request nonce
    // (SecurityHeaders adds it); inline style attributes are still used, hence 'unsafe-inline' for styles.
    'csp' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", 'https://www.googletagmanager.com', 'https://*.google-analytics.com'],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
        'font-src' => ["'self'", 'data:'],
        'connect-src' => ["'self'", 'https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://*.googletagmanager.com'],
        'frame-src' => ["'self'", 'https://www.googletagmanager.com'],
        'frame-ancestors' => ["'self'"],
        'form-action' => ["'self'"],
        'base-uri' => ["'self'"],
        'object-src' => ["'none'"],
        'manifest-src' => ["'self'"],
        'worker-src' => ["'self'", 'blob:'],
    ],
];
