<?php

// Putanje koje zaglavlje, podnožje i donja navigacija koriste; iste kao na sadašnjem sajtu.
return [

    'linkovi' => [
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
        'privatnost' => '/politika-privatnosti/',
    ],

    // Kontakt forma: najmanje sekundi od otvaranja do slanja, najduže važenje forme, broj poruka po IP adresi.
    'kontakt' => [
        'min_sekundi' => 3,
        'max_sati' => 12,
        'limit' => 3,
        'limit_minuta' => 10,
    ],
];
