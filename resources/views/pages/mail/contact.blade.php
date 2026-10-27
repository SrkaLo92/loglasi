{{-- Plain text (text/plain): no HTML entities, so the values are printed raw. --}}
Nova poruka sa kontakt forme na loglasi.rs

Ime: {!! $data['ime'] !!}
E-mail ili telefon: {!! $data['email'] !== '' ? $data['email'] : $data['telefon'] !!}
Tema: {!! $data['tema'] !!}

{!! $data['poruka'] !!}

--
{!! $data['email'] !== '' ? 'Odgovorom na ovu poruku pišete pošiljaocu.' : 'Pošiljalac je ostavio telefon, pozovite ga.' !!}
