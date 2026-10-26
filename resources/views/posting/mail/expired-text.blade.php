{{-- Plain text: values are printed raw (no HTML entities). --}}
Zdravo{!! $name ? ', '.$name : '' !!}!

Vaš oglas „{!! $title !!}” je istekao i više se ne prikazuje na sajtu.
Ako još prodajete, obnovite ga jednim klikom. Obnova je besplatna i oglas je ponovo aktivan {!! $days !!} dana.

Obnovi besplatno: {!! $renewUrl !!}

Svi istekli oglasi: {!! $myListingsUrl !!}

Pozdrav, loglasi.rs
