<?php

/*
 * Uvoz iz WordPress-a (Listivo + loglasi-core): php artisan uvoz:wordpress
 *
 * Ključevi su isti kao u loglasi-core (inc/api/rest.php, inc/oglas/oglas.php,
 * inc/profil/profil.php, inc/zajednicko/cena.php). Ako se na pravom sajtu
 * pokaže drugačije, menja se samo ovde.
 */

return [

    // Druga konekcija na bazu WordPress-a (Local: socket ili port iz „Database” taba).
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

    // Cena: listivo_{ID polja cene}_listivo_{ID termina valute}.
    'price_field_id' => 130,
    'currency_term_eur' => 435,
    'currency_term_rsd' => 0, // 0 = prepoznaje se po nazivu termina, inače sve što nije EUR je RSD

    'meta' => [
        'featured' => 'featured',           // '1' / '0'
        'featured_expire' => 'featured_expire', // datetime
        'gallery' => 'listivo_145',
        'views' => 'views',
        'expires' => '',          // Listivo datum isteka, ako postoji
        'lat' => '',
        'lng' => '',
        'listing_phone' => '',    // telefon na samom oglasu, ako postoji
    ],

    'usermeta' => [
        'account_type' => 'account_type',
        'account_business' => 'business',
        'phone' => 'phone',
        'phone_country' => 'phone_country_code', // "&#x1F1F7&#x1F1F8 +381"
        'phone_verified' => 'loglasi_telefon_proveren',
        'avatar' => 'image', // attachment ID, 0 = none
        'favorites' => 'listivo_favorites',
        'biznis_until' => '',     // datum isteka Biznis paketa, ako se negde čuva
        // Polja firme (loglasi-core, inc/profil/profil.php)
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
        'category_icon' => '',    // ID priloga ili URL ikonice
        'order' => 'order',
    ],

    // Poruke: Listivo ih čuva u svojoj tabeli (bez prefiksa ovde). Ako tabela ne postoji, preskače se.
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

    // Polja oglasa. Taksonomije listivo_<ID> i meta listivo_<ID> se pronalaze same;
    // ovde se po izvornom ključu (wp_key) zadaje ključ, naziv, tip, filter i jedinica.
    'fields' => [
        // primer: 'listivo_1912' => ['key' => 'kilometraza', 'label' => 'Kilometraža', 'type' => 'number', 'filter' => 'range', 'unit' => 'km'],
    ],

    // Privremena polja iz loglasi-core (class-loglasi-api-fields.php, defaults()).
    'meta_fields' => [
        'loglasi_stanje' => ['key' => 'stanje', 'label' => 'Stanje', 'type' => 'select', 'filter' => 'multi', 'options' => ['Novo', 'Kao novo', 'Korišćeno', 'Neispravno']],
        'loglasi_vrsta_oglasa' => ['key' => 'vrsta_oglasa', 'label' => 'Vrsta oglasa', 'type' => 'select', 'filter' => 'select', 'options' => ['Prodajem', 'Kupujem']],
        'loglasi_moguca_zamena' => ['key' => 'moguca_zamena', 'label' => 'Moguća zamena', 'type' => 'select', 'filter' => 'select', 'options' => ['Da', 'Ne']],
        'loglasi_preuzimanje' => ['key' => 'preuzimanje', 'label' => 'Preuzimanje', 'type' => 'multiselect', 'filter' => 'multi', 'options' => ['Lično preuzimanje', 'Dostava']],
    ],

    // Spam nalozi koji se ne prenose (docs/09-deploy-seo.md, odeljak 2); njihovi URL-ovi idu u gone_urls.
    'spam_users' => [80, 106, 109],

    // Gde se piše izveštaj (izvestaj-{datum}.txt).
    'report_dir' => storage_path('app/uvoz'),

    // Istaknut oglas bez datuma isteka ostaje istaknut ovoliko dana od uvoza.
    'featured_days' => 30,

    // Kad WordPress nema datum isteka: objavljen oglas važi još ovoliko dana od uvoza.
    'listing_days' => 30,
];
