<?php

// Whole-page cache for visitors who are not signed in (App\Http\Middleware\GuestPageCache).
// A page is built once and served from the cache for `ttl` seconds, so a sudden crowd of visitors
// (a reel, a share) does not reach the database. Signed-in users always get a fresh page.

return [
    'enabled' => (bool) env('PAGE_CACHE_ENABLED', true),

    // Seconds; also bounds how long a changed listing or price may look old to visitors.
    'ttl' => (int) env('PAGE_CACHE_TTL', 120),

    // Route names whose pages may be cached (public pages only, never forms that post data or accounts).
    'routes' => [
        'home',
        'listings.all', 'listings.all.page', 'listings.category', 'listings.category.page', 'listings.place', 'listings.place.page',
        'listing.show',
        'profile.show', 'ratings.index', 'firms.directory',
        'pages.pricing', 'pages.for-firms', 'pages.about', 'pages.faq', 'pages.terms', 'pages.privacy', 'pages.rules',
        'pages.payment-terms', 'pages.withdrawal', 'pages.complaints', 'pages.data-deletion',
    ],
];
