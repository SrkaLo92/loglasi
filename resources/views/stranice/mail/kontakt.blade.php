{{-- Čist tekst (text/plain): bez HTML entiteta, pa se vrednosti ispisuju sirove. --}}
Nova poruka sa kontakt forme na loglasi.rs

Ime: {!! $data['ime'] !!}
E-pošta: {!! $data['email'] !!}
Telefon: {!! $data['telefon'] !== '' ? $data['telefon'] : 'nije unet' !!}
Tema: {!! $data['tema'] !!}

{!! $data['poruka'] !!}

--
IP adresa: {!! $data['ip'] ?? 'nepoznata' !!}
Odgovorom na ovu poruku pišete pošiljaocu.
