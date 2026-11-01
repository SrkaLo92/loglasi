<?php

// Local info portal (news, events, service info, business directory) on its own subdomain,
// served by this same application. Off until the subdomain exists: INFO_ENABLED=true.

$mainUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$mainHost = parse_url($mainUrl, PHP_URL_HOST) ?: 'localhost';

return [
    'enabled' => (bool) env('INFO_ENABLED', false),

    // Host of the portal, e.g. info.loglasi.rs. Every other host keeps the classifieds site.
    'domain' => env('INFO_DOMAIN') ?: 'info.'.$mainHost,

    // Links from the portal back to the classifieds site are absolute (another host).
    'main_url' => $mainUrl,

    // Indexing follows the main site's switch unless set on its own.
    'indexable' => (bool) (env('INFO_INDEXABLE') ?? env('SEO_INDEXABLE', false)),

    // Bearer token for POST /api/v1/info/drafts (the off-server routine that writes news drafts).
    // Empty = the endpoint is closed.
    'drafts_token' => env('INFO_DRAFTS_TOKEN', ''),

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
