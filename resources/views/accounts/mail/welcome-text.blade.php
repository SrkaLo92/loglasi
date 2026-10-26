{{-- Plain text: values are printed raw (no HTML entities). --}}
Zdravo{!! $name ? ', '.$name : '' !!}!

Dobro došli na loglasi.rs, sajt za besplatne oglase za Loznicu i okolinu. Vaš nalog je spreman.

Prvi oglas za par minuta:
1. Kliknite „Postavite oglas” i izaberite kategoriju.
2. Napišite naslov, opis i cenu, ili izaberite „Dogovor”.
3. Dodajte nekoliko fotografija. Oglasi sa slikama dobijaju više poziva.

Privatna lica besplatno imaju {!! $free !!}. Oglas traje {!! $days !!} dana, a obnova je besplatna.

Postavite prvi oglas: {!! $postUrl !!}

Svi oglasi: {!! $listingsUrl !!}
Imate firmu ili radnju? Profil firme sa logom i svim oglasima: {!! $firmsUrl !!}

Pozdrav, loglasi.rs
Pitanja: {!! $contact !!}
