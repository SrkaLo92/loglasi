{{-- Plain text (text/plain): values are printed raw, without HTML entities. --}}
Zdravo!

@if ($approved)
Vaš oglas „{!! $title !!}” je pregledan i objavljen. Kupci ga sada vide na sajtu:
{!! $url !!}
@else
Vaš oglas „{!! $title !!}” nije odobren.

Razlog: {!! $reason !!}

Oglas možete da izmenite i ponovo pošaljete na pregled u delu Moj nalog:
{!! $editUrl !!}
@endif

Pozdrav,
loglasi.rs
