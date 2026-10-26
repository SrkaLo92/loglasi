<?php

// Paths used by the header, footer and bottom navigation; the same as on the old site.
return [

    'links' => [
        'home' => '/',
        'listings' => '/oglasi/',
        'post' => '/postavi-oglas/',
        'messages' => '/moj-nalog/poruke/',
        'account' => '/moj-nalog/',
        'login' => '/login-registracija/',
        'data_deletion' => '/brisanje-licnih-podataka/',
        'pricing' => '/cenovnik/',
        'for_firms' => '/za-firme/',
        'contact' => '/kontakt/',
        'about' => '/o-nama/',
        'faq' => '/faq/',
        'terms' => '/uslovi-koriscenja/',
        'rules' => '/pravila-oglasavanja/',
        'privacy' => '/politika-privatnosti/',
        'payment_terms' => '/uslovi-placanja/',
        'withdrawal' => '/odustanak-od-ugovora/',
        'complaints' => '/reklamacije/',
    ],

    // Contact form: least seconds from opening to sending, longest form validity, messages per IP address.
    'contact' => [
        'min_seconds' => 3,
        'max_hours' => 12,
        'limit' => 3,
        'limit_minutes' => 10,
    ],
];
