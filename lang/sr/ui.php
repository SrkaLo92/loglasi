<?php

/*
 * Zajednički tekstovi interfejsa (dugmad, oznake, poruke). Prenete odluke o rečima
 * iz loglasi-core/inc/dorada/class-loglasi-dorada-prevodi.php: kako se šta zove na sajtu.
 * Upotreba: __('ui.postavi_oglas'), trans_choice('ui.oglasa', $n, ['n' => $n]).
 */

return [

    // Nalog
    'prijava' => 'Prijava',
    'registracija' => 'Registracija',
    'prijava_registracija' => 'Prijava / Registracija',   // ne „Login / Register”
    'odjava' => 'Odjava',
    'moj_nalog' => 'Moj nalog',
    'email_ili_korisnicko_ime' => 'E-pošta ili korisničko ime',
    'email' => 'E-pošta',                                  // ne „Email”
    'lozinka' => 'Lozinka',
    'na_mrezi' => 'Na mreži',                              // ne „Online”
    'nije_na_mrezi' => 'Nije na mreži',                    // ne „Offline”
    'korisnik_na_mrezi' => 'Korisnik je na mreži',
    'korisnik_sada_na_mrezi' => 'Korisnik je sada na mreži',
    'korisnik_nije_na_mrezi' => 'Korisnik nije na mreži',

    // Oglasi
    'postavi_oglas' => 'Postavi oglas',                    // donja navigacija
    'postavite_oglas' => 'Postavite oglas',                // dugme u zaglavlju i pozivi na akciju
    'oglasi' => 'Oglasi',                                  // ne „Rezultati pretrage” / „Search Results”
    'svi_oglasi_u_loznici' => 'Svi oglasi u Loznici',
    'prikazi_jos' => 'Prikaži još',                        // ne „Prikazivanje više” / „Load more”
    'karakteristike' => 'Karakteristike',                  // ne „Osobine” / „Features”
    'najniza_cena' => 'Najniža cena',                      // ne „Min Cena”
    'najvisa_cena' => 'Najviša cena',                      // ne „Max Cena”
    'maksimalno' => 'Maksimalno',
    'prijavi_oglas' => 'Prijavi oglas',                    // ne „Prijavite zlostavljanje” / „Report abuse”
    'uporedite_oglase' => 'Uporedite oglase',
    'kreditni_kalkulator' => 'Kreditni kalkulator (informativno)', // ne „Kupite sada, platite kasnije”
    'istaknuto' => 'Istaknuto',
    'firma' => 'Firma',
    'proverena_firma' => 'Proverena firma',
    'dogovor' => 'Dogovor',
    'trazi' => 'Traži',
    'pretraga' => 'Pretraga',
    'poruke' => 'Poruke',
    'pocetna' => 'Početna',

    // Množina: 1 oglas, 2 oglasa, 5 oglasa, 21 oglas
    'oglasa' => ':n oglas|:n oglasa|:n oglasa',

    // Vreme: „pre 3 dana”, ne „3 days ago” ni „3 dana pre”
    'pre' => 'pre :vreme',
    'na_sajtu' => 'Na sajtu :vreme',

    // Kontakt forma (umesto CF7 poruka)
    'poruka_poslata' => 'Hvala na poruci. Poslata je.',
    'poruka_greska' => 'Poruka nije poslata. Pokušajte ponovo malo kasnije.',
    'polja_neispravna' => 'Neka polja nisu dobro popunjena. Proverite označena polja i pošaljite ponovo.',
    'obavezno_polje' => 'Ovo polje je obavezno.',
    'neispravna_email' => 'Unesite ispravnu adresu e-pošte.',
    'neispravan_telefon' => 'Unesite ispravan broj telefona, npr. 65 123 4567.',
    'izaberite' => '— Izaberite —',
    'posalji' => 'Pošalji',
    'posalji_poruku' => 'Pošalji poruku',
    'kontakt_forma' => 'Kontakt forma',
];
