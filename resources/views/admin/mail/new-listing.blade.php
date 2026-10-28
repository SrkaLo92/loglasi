{{-- Plain text (text/plain): values are printed raw, without HTML entities. --}}
@if ($pending)
Stigao je nov oglas i čeka odobrenje.
@else
Stigao je nov oglas i odmah je objavljen.
@endif

Oglas br. {!! $number !!}: „{!! $title !!}”
Kategorija: {!! $category ?? '—' !!}
Mesto: {!! $location ?? '—' !!}
Korisnik: {!! $owner !!}

Otvori u adminu:
{!! $adminUrl !!}
@if ($url)

Na sajtu:
{!! $url !!}
@endif

Ovaj mejl se isključuje u adminu: Podešavanja → „Mejl adminima za svaki novi oglas”.
