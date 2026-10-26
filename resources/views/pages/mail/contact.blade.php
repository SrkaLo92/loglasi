{{-- Čist tekst (text/plain): bez HTML entiteta, pa se vrednosti ispisuju sirove. --}}
Nova poruka sa kontakt forme na loglasi.rs

Ime: {!! $data['ime'] !!}
E-mail ili telefon: {!! $data['email'] !== '' ? $data['email'] : $data['telefon'] !!}
Tema: {!! $data['tema'] !!}

{!! $data['poruka'] !!}

--
IP adresa: {!! $data['ip'] ?? 'nepoznata' !!}
{!! $data['email'] !== '' ? 'Odgovorom na ovu poruku pišete pošiljaocu.' : 'Pošiljalac je ostavio telefon, pozovite ga.' !!}
