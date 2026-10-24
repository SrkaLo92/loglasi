<?php

// Podešavanja SEO modula: kanonski domen, pravila za indeksiranje, sitemap, preusmerenja.
// Opis svih pravila: docs/moduli/seo.md

return [

    // Kanonski domen za canonical, og:url, sitemap i robots.txt. Nikad lokalni domen.
    'base_url' => rtrim((string) env('SEO_BASE_URL', 'https://loglasi.rs'), '/'),

    // Da li pretraživači smeju da indeksiraju sajt (robots.txt). Van produkcije: zabranjeno sve.
    'indexable' => (bool) env('SEO_INDEXABLE', env('APP_ENV') === 'production'),

    'site_name' => 'loglasi.rs',
    'tagline' => 'loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.',
    'default_title' => 'Oglasi Loznica – besplatni mali oglasi | loglasi.rs',
    'title_suffix' => ' | loglasi.rs',
    'title_max' => 60,
    'description_max' => 155,

    // Slika za Open Graph kad strana nema svoju (putanja iz public/ ili apsolutni URL).
    'default_image' => env('SEO_DEFAULT_IMAGE', '/img/loglasi-og.png'),
    'logo' => env('SEO_LOGO', '/img/loglasi-logo.png'),

    'organization' => [
        'email' => 'kontakt@loglasi.rs',
        'postal_code' => '15300',
        'city' => 'Loznica',
        'area_served' => ['Loznica', 'Banja Koviljača', 'Lešnica', 'Krupanj', 'Ljubovija', 'Mali Zvornik'],
        'same_as' => [
            'https://www.facebook.com/profile.php?id=61571911496798',
            'https://www.instagram.com/loglasi.rs/',
            'https://www.tiktok.com/@loglasi.rs',
        ],
    ],

    // SearchAction u WebSite schemi; {search_term_string} zamenjuje Google.
    'search_url' => '/oglasi/?keyword={search_term_string}',

    // Parametri koji se ne računaju kao filter i odbacuju se pri preusmeravanju.
    'tracking_params' => [
        'utm_*', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'ttclid', 'igshid',
        'mc_cid', 'mc_eid', '_gl', 'nc',
    ],

    // Parametri paginacije: strana je noindex,follow, canonical na samu sebe.
    'page_params' => ['page', 'paged'],

    // Strane naloga i pomoćne strane: 200, noindex,follow, bez canonical-a (Str::is obrasci, bez kose crte na kraju).
    'noindex_paths' => [
        'prijava', 'prijava/*', 'registracija', 'registracija/*', 'odjava',
        'login-registracija', 'zaboravljena-lozinka', 'zaboravljena-lozinka/*', 'nova-lozinka/*',
        'nalog', 'nalog/*', 'moj-nalog', 'moj-nalog/*', 'panel', 'panel/*',
        'postavi-oglas', 'postavi-oglas/*', 'izmeni-oglas/*', 'poruke', 'poruke/*',
        'omiljeni', 'sacuvane-pretrage', 'uporedite-oglase', 'brisanje-podataka',
        'placanje', 'placanje/*', 'narudzbine', 'narudzbine/*', 'pretraga',
    ],

    // Javne strane koje ulaze u /sitemap-strane.xml (i koje skripta provere očekuje kao 200 + index).
    'pages' => [
        '/', '/oglasi/', '/o-nama/', '/faq/', '/kontakt/', '/cenovnik/', '/za-firme/',
        '/uslovi-koriscenja/', '/politika-privatnosti/', '/brisanje-licnih-podataka/',
    ],

    // Narodna fraza po slugu kategorije (keyword mapa): naziv za title i H1, „za” = akuzativ za opis.
    'phrases' => [
        'automobili' => ['naziv' => 'Polovni automobili', 'za' => 'polovne automobile'],
        'nekretnine' => ['naziv' => 'Nekretnine', 'za' => 'nekretnine'],
        'stanovi' => ['naziv' => 'Stanovi', 'za' => 'stanove'],
        'mobilni-telefoni' => ['naziv' => 'Polovni telefoni', 'za' => 'polovne telefone'],
        'poslovi' => ['naziv' => 'Posao', 'za' => 'posao'],
        'usluge' => ['naziv' => 'Majstori i usluge', 'za' => 'majstore i usluge'],
        'besplatne-stvari' => ['naziv' => 'Besplatne stvari', 'za' => 'besplatne stvari'],
        'drva' => ['naziv' => 'Drva', 'za' => 'drva'],
    ],

    // Kategorija + mesto ide u indeks i u sitemap tek od ovoliko aktivnih oglasa
    // (u adminu: podešavanje „seo_place_min_listings”).
    'place_min_listings' => 3,

    'sitemap' => [
        'per_file' => 1000,
        'cache_ttl' => 86400,
        'max_age' => 3600, // Cache-Control za pregledač i CDN
    ],

    // Odredišta za stare WordPress/WooCommerce adrese (otvorena pitanja za vlasnika, docs/moduli/seo.md).
    'targets' => [
        'kategorija_unknown' => '/oglasi/',
        'marka_automobila' => '/oglasi/automobili/',
        'woo_product' => '/oglasi/',
        'woo_shop' => '/oglasi/',
        'woo_account' => '/',
        'wp_admin' => '/admin/',
        'wp_login' => '/prijava/',
    ],

    // Feed: broj oglasa u /feed/.
    'feed_items' => 30,
];
