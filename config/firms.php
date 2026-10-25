<?php

// Firms module: directory, firm self-service, firm packages. See docs/modules/firms.md.

return [
    // Public directory of firm accounts with an active package or the free first year.
    'directory' => [
        'path' => '/firme/',
        'per_page' => 24,
        'cache_ttl' => 600, // seconds; also cleared when a firm or a firm account changes
    ],

    // Where "choose / renew a package" leads (order flow of the payments module).
    'renew_url' => '/cenovnik/#lgc-firme',

    /*
     * Firm packages (price list of 25 Oct 2026). The active listing limit is read from the product
     * (products.listing_limit, editable in the admin) and falls back to 'listing_limit' here;
     * null = unlimited. Included promotions are granted per calendar month.
     */
    'packages' => [
        'vizitka' => [
            'label' => 'Vizitka',
            'products' => ['monthly' => 'business_vizitka_monthly', 'yearly' => 'business_vizitka_yearly'],
            'listing_limit' => 5,
            'credits' => ['bumps' => 0, 'highlights' => 0, 'premium_xl' => 0],
            'advanced_stats' => false,
        ],
        'start' => [
            'label' => 'Business Start',
            'products' => ['monthly' => 'business_start_monthly', 'yearly' => 'business_start_yearly'],
            'listing_limit' => 50,
            'credits' => ['bumps' => 3, 'highlights' => 0, 'premium_xl' => 0],
            'advanced_stats' => false,
        ],
        'pro' => [
            'label' => 'Business Pro',
            'products' => ['monthly' => 'business_pro_monthly', 'yearly' => 'business_pro_yearly'],
            'listing_limit' => null,
            'credits' => ['bumps' => 10, 'highlights' => 2, 'premium_xl' => 0],
            'advanced_stats' => true,
        ],
        'max' => [
            'label' => 'Business Max',
            'products' => ['monthly' => 'business_max_monthly', 'yearly' => 'business_max_yearly'],
            'listing_limit' => null,
            'credits' => ['bumps' => 20, 'highlights' => 5, 'premium_xl' => 1],
            'advanced_stats' => true,
        ],
    ],

    // First 30 firms: 12 months free, unlimited standard listings, verified badge, monthly promotions, no auto-billing after.
    'free_program' => [
        'label' => 'Prvih 30 firmi',
        'months' => 12,
        'listing_limit' => null,
        'credits' => ['bumps' => 5, 'highlights' => 1, 'premium_xl' => 0],
        'advanced_stats' => false,
    ],

    // Reminder mails before the package ends, in days (largest first). Monthly terms get only the last one.
    'reminder_days' => [14, 3],
    'reminder_days_monthly' => [3],

    // Firm statistics: periods offered on the web page (days) and cache time.
    'stats' => [
        'periods' => [30, 90], // 90 days and the per-listing table need advanced statistics
        'default_period' => 30,
        'cache_ttl' => 300,
        'top_listings' => 50,
    ],

    // Profile view count is shown from this number up (package benefit).
    'profile_views_min' => 10,

    // Image sizes (WebP).
    'logo_size' => 400,          // fits inside 400 x 400, aspect kept
    'cover_size' => [1600, 500], // center crop
];
