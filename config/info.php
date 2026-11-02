<?php

// Local info portal (news, events, service info, business directory) on its own subdomain,
// served by this same application. Off until the subdomain exists: INFO_ENABLED=true.

$mainUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$mainHost = parse_url($mainUrl, PHP_URL_HOST) ?: 'localhost';

return [
    'enabled' => (bool) env('INFO_ENABLED', false),

    // "Danas u Loznici" block on the classifieds home page; off until the portal is promoted there.
    'home_block' => (bool) env('INFO_HOME_BLOCK', false),

    // Host of the portal, e.g. info.loglasi.rs. Every other host keeps the classifieds site.
    'domain' => env('INFO_DOMAIN') ?: 'info.'.$mainHost,

    // Links from the portal back to the classifieds site are absolute (another host).
    'main_url' => $mainUrl,

    // Indexing follows the main site's switch unless set on its own.
    'indexable' => (bool) (env('INFO_INDEXABLE') ?? env('SEO_INDEXABLE', false)),

    // Bearer token for POST /api/v1/info/drafts (the off-server routine that writes news drafts).
    // Empty = the endpoint is closed.
    'drafts_token' => env('INFO_DRAFTS_TOKEN', ''),

    // Cities the portal covers. Every post, notice, pharmacy duty, firm and ad carries a city key, so the
    // portal can later get a city switch (Loznica, Šabac, ...) and show only that city's data. With one
    // city the filter stays off; INFO_CITY is the city new content gets by default.
    'city' => env('INFO_CITY', 'loznica'),
    'cities' => [
        'loznica' => ['name' => 'Loznica', 'latitude' => 44.5338, 'longitude' => 19.2258,
            'municipalities' => ['Loznica', 'Mali Zvornik', 'Krupanj', 'Ljubovija']],
        // 'sabac' => ['name' => 'Šabac', 'latitude' => 44.7489, 'longitude' => 19.6908, 'municipalities' => ['Šabac']],
    ],

    'weather' => [
        'latitude' => 44.5338,
        'longitude' => 19.2258,
        'url' => 'https://api.open-meteo.com/v1/forecast',
    ],

    // Planned power outages published by Elektrodistribucija Srbije, one page per area and day
    // (Dan_0 = today ... Dan_3). Rows are kept only for the municipalities below.
    'power' => [
        'url' => 'https://elektrodistribucija.rs/planirana-iskljucenja-srbija/{area}_Dan_{day}_Iskljucenja.htm',
        'areas' => array_filter(explode(',', (string) env('INFO_POWER_AREAS', 'Kraljevo'))),
        'days' => 4,
        'municipalities' => ['Loznica', 'Mali Zvornik', 'Krupanj', 'Ljubovija'],
    ],

    // HTTP calls from the scheduler: short, so a slow source never holds a shared-hosting process.
    'http_timeout' => 6,

    // Advertising price list (RSD) shown on /oglasavanje/. Any value can be overridden from the admin
    // with a setting named info_price_<key> (e.g. info_price_featured_month).
    'pricing' => [
        'featured_month' => 490,
        'featured_year' => 4900,
        'today_sponsor' => 2990,
        'article_bottom' => 1990,
        'home_middle' => 1490,
        'category_top' => 990,
        'banner_design' => 990,
        'event' => 990,
        'promo_own' => 1990,
        'promo_written' => 2990,
        'promo_video' => 5990,
        'package_neighbour' => 2490,
        'package_local' => 6990,
        'package_partner' => 14990,
    ],

    // Hourly news: RSS of active sources -> retold in our words by Claude -> "Na čekanju" for an editor.
    // Only life in town gets through (Srdjan 2026-11-02): curiosities, service info, culture, events. No politics.
    'news' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('INFO_NEWS_MODEL', 'claude-haiku-5-5'),
        'max_per_run' => (int) env('INFO_NEWS_MAX_PER_RUN', 8),
        'max_age_days' => 3,
        // Dropped before any API call: lower case, matched at the start of a word ("ubist" hits "ubistvo").
        'blocked_words' => [
            'sns', 'srpska napredna', 'opozicij', 'izbori', 'izborn', 'glasanj', 'skupštin', 'odborni', 'poslanik', 'stranka', 'stranke',
            'gradonačelni', 'vučić', 'vlada srbije', 'ministar', 'protest', 'blokad', 'studenti u blokadi',
            'litijum', 'jadar', 'rio tinto', 'rudnik litijuma',
            'ubist', 'ubio', 'uhapšen', 'uhapsi', 'pritvor', 'krivičn', 'policij', 'pucnjav', 'nožem', 'izboden', 'drog', 'silovan', 'pljačk', 'kradj', 'krađ',
            'poginu', 'nesreć', 'saobraćajni udes', 'u udesu', 'preminu', 'čitulj', 'sahran', 'umro', 'umrla', 'in memoriam', 'samoubist',
        ],
    ],

    'topics' => [
        'drustvo' => 'Društvo',
        'sport' => 'Sport',
        'kultura' => 'Kultura',
        'privreda' => 'Privreda',
        'zdravlje' => 'Zdravlje',
        'obrazovanje' => 'Obrazovanje',
        'saobracaj' => 'Saobraćaj',
    ],
];
