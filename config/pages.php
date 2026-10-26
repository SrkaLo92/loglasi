<?php

// Paths used by the header, footer and bottom navigation; the same as on the old site.
return [

    'links' => [
        'pocetna' => '/',
        'oglasi' => '/oglasi/',
        'postavi' => '/postavi-oglas/',
        'poruke' => '/moj-nalog/poruke/',
        'nalog' => '/moj-nalog/',
        'prijava' => '/login-registracija/',
        'brisanje' => '/brisanje-licnih-podataka/',
        'cenovnik' => '/cenovnik/',
        'za_firme' => '/za-firme/',
        'kontakt' => '/kontakt/',
        'o_nama' => '/o-nama/',
        'faq' => '/faq/',
        'uslovi' => '/uslovi-koriscenja/',
        'pravila' => '/pravila-oglasavanja/',
        'privatnost' => '/politika-privatnosti/',
        'placanje' => '/uslovi-placanja/',
        'odustanak' => '/odustanak-od-ugovora/',
        'reklamacije' => '/reklamacije/',
    ],

    // Contact form: least seconds from opening to sending, longest form validity, messages per IP address.
    'contact' => [
        'min_seconds' => 3,
        'max_hours' => 12,
        'limit' => 3,
        'limit_minutes' => 10,
    ],
];
