<?php

// Firms module: directory, firm self-service, Biznis package rules. See docs/modules/firms.md.

return [
    // Public directory of firms with an active Biznis package.
    'directory' => [
        'path' => '/firme/',
        'per_page' => 24,
        'cache_ttl' => 600, // seconds; also cleared when a firm or a firm account changes
    ],

    // Where "Produžite Biznis paket" leads (order flow of the payments module).
    'renew_url' => '/cenovnik/#biznis',

    // Reminder mails before biznis_until, in days (largest first).
    'reminder_days' => [14, 3],

    // Firm statistics: periods offered on the web page (days) and cache time.
    'stats' => [
        'periods' => [30, 90],
        'default_period' => 30,
        'cache_ttl' => 300,
        'top_listings' => 50,
    ],

    // Profile view count is shown from this number up (Biznis benefit).
    'profile_views_min' => 10,

    // Image sizes (WebP).
    'logo_size' => 400,          // fits inside 400 x 400, aspect kept
    'cover_size' => [1600, 500], // center crop
];
