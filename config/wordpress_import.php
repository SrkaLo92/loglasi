<?php

/*
 * Import from WordPress (Listivo + loglasi-core): php artisan import:wordpress
 *
 * The keys are the same as in loglasi-core (inc/api/rest.php, inc/oglas/oglas.php,
 * inc/profil/profil.php, inc/zajednicko/cena.php). If the real site turns out
 * different, only this file changes.
 */

return [

    // Second connection to the WordPress database (Local: socket or port from the "Database" tab).
    'connection' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'port' => env('WP_DB_PORT', '3306'),
        'unix_socket' => env('WP_DB_SOCKET', ''),
        'database' => env('WP_DB_DATABASE', 'local'),
        'username' => env('WP_DB_USERNAME', 'root'),
        'password' => env('WP_DB_PASSWORD', 'root'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => env('WP_DB_PREFIX', 'wp_'),
        'prefix_indexes' => true,
        'strict' => false,
        'engine' => null,
    ],

    'post_type' => 'listivo_listing',
    'tax_category' => 'listivo_14',
    'tax_location' => 'listivo_14144',

    // Price: listivo_{price field ID}_listivo_{currency term ID}.
    'price_field_id' => 130,
    'currency_term_eur' => 435,
    'currency_term_rsd' => 0, // 0 = recognised by the term name, otherwise everything that is not EUR is RSD

    'meta' => [
        'featured' => 'featured',           // '1' / '0'
        'featured_expire' => 'featured_expire', // datetime
        'gallery' => 'listivo_145',
        'views' => 'views',
        'expires' => '',          // Listivo expiry date, if it exists
        'lat' => '',
        'lng' => '',
        'listing_phone' => '',    // phone on the listing itself, if it exists
    ],

    'usermeta' => [
        'account_type' => 'account_type',
        'account_business' => 'business',
        'phone' => 'phone',
        'phone_country' => 'phone_country_code', // "&#x1F1F7&#x1F1F8 +381"
        'phone_verified' => 'loglasi_telefon_proveren',
        'avatar' => 'image', // attachment ID, 0 = none
        'favorites' => 'listivo_favorites',
        'biznis_until' => '',     // expiry date of the Biznis package, if it is stored somewhere
        // Firm fields (loglasi-core, inc/profil/profil.php)
        'firm_logo' => 'loglasi_firma_logo',
        'firm_cover' => 'loglasi_firma_naslovna',
        'firm_verified' => 'loglasi_firma_proverena',
        'firm_activity' => 'loglasi_firma_delatnost',
        'firm_description' => 'loglasi_firma_opis',
        'firm_address' => 'loglasi_firma_adresa',
        'firm_city' => 'loglasi_firma_mesto',
        'firm_hours' => 'loglasi_firma_radno_vreme',
        'firm_website' => 'loglasi_firma_sajt',
        'firm_facebook' => 'loglasi_firma_facebook',
        'firm_instagram' => 'loglasi_firma_instagram',
        'firm_phone' => 'loglasi_firma_telefon',
    ],

    'termmeta' => [
        'category_icon' => '',    // attachment ID or icon URL
        'order' => 'order',
    ],

    // Messages: Listivo keeps them in its own table (no prefix here). If the table does not exist, it is skipped.
    'messages' => [
        'table' => 'listivo_messages',
        'columns' => [
            'id' => 'id',
            'from' => 'user_from',
            'to' => 'user_to',
            'listing' => 'listing_id',
            'body' => 'message',
            'created_at' => 'created_at',
        ],
    ],

    // Listing fields. listivo_<ID> taxonomies and listivo_<ID> meta are found on their own;
    // here, by source key (wp_key), you set the key, name, type, filter and unit.
    'fields' => [
        // example: 'listivo_1912' => ['key' => 'kilometraza', 'label' => 'Kilometraža', 'type' => 'number', 'filter' => 'range', 'unit' => 'km'],
    ],

    // Temporary fields from loglasi-core (class-loglasi-api-fields.php, defaults()).
    'meta_fields' => [
        'loglasi_stanje' => ['key' => 'stanje', 'label' => 'Stanje', 'type' => 'select', 'filter' => 'multi', 'options' => ['Novo', 'Kao novo', 'Korišćeno', 'Neispravno']],
        'loglasi_vrsta_oglasa' => ['key' => 'vrsta_oglasa', 'label' => 'Vrsta oglasa', 'type' => 'select', 'filter' => 'select', 'options' => ['Prodajem', 'Kupujem']],
        'loglasi_moguca_zamena' => ['key' => 'moguca_zamena', 'label' => 'Moguća zamena', 'type' => 'select', 'filter' => 'select', 'options' => ['Da', 'Ne']],
        'loglasi_preuzimanje' => ['key' => 'preuzimanje', 'label' => 'Preuzimanje', 'type' => 'multiselect', 'filter' => 'multi', 'options' => ['Lično preuzimanje', 'Dostava']],
    ],

    // After the import, make fields and category fields KP-like (catalog:apply, database/seeders/data/catalog).
    'catalog_definition' => (bool) env('UVOZ_CATALOG_DEFINITION', true),

    // Paths from the live sitemap that no longer exist (deleted while cleaning the copy): 410 after import.
    'gone_file' => env('UVOZ_GONE_FILE', database_path('seeders/data/gone-urls.txt')),

    // Old addresses with Google clicks whose listing is not imported: "path target" per line, 301 instead of 410.
    'redirect_file' => env('UVOZ_REDIRECT_FILE', database_path('seeders/data/redirects-with-clicks.txt')),

    // Spam accounts that are not carried over (docs/09-deploy-seo.md, section 2); their URLs go to gone_urls.
    'spam_users' => [80, 106, 109],

    // Where the report is written (izvestaj-{date}.txt).
    'report_dir' => storage_path('app/uvoz'),

    // A featured listing without an expiry date stays featured this many days from the import.
    'featured_days' => 30,

    // When WordPress has no expiry date: a published listing is valid this many more days from the import.
    'listing_days' => 30,
];
