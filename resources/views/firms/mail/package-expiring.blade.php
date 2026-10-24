{{-- Plain text (text/plain): values are printed raw, without HTML entities. --}}
Zdravo,

@if ($free)
Besplatna prva godina firme {!! $name !!} na loglasi.rs (prvih 30 firmi) važi još {!! $days !!}, do {!! $until !!}.

Posle toga nema automatske naplate. Ako želite da zadržite profil firme, paket birate sami: Business Start, Pro ili Max, mesečno ili godišnje.
@else
Paket {!! $label !!} firme {!! $name !!} na loglasi.rs važi još {!! $days !!}, do {!! $until !!}.

Pretplata se ne obnavlja sama. Produžite je uplatom pre isteka.
@endif

Kada paket istekne, podaci firme ostaju sačuvani, ali profil gubi logo, naslovnu sliku, radno vreme, mapu i dugmad za WhatsApp i Viber, firma izlazi iz imenika „Firme iz Loznice”, a broj aktivnih oglasa se vraća na limit privatnog lica.

Paketi i cene:
{!! $renewUrl !!}

Podaci firme i statistika:
{!! $accountUrl !!}

Ako ste već uplatili, ne morate ništa da radite. Uplatu potvrđujemo ručno, najčešće istog radnog dana.

Pozdrav,
loglasi.rs
