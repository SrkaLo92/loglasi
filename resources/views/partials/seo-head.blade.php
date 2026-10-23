{{-- Naslov, opis, canonical, robots, Open Graph i schema; puna verzija dolazi u modulu „SEO”. --}}
<title>{{ $title ?? 'loglasi.rs: besplatni oglasi za Loznicu i okolinu' }}</title>
@isset($description)<meta name="description" content="{{ $description }}">@endisset
@isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
@isset($robots)<meta name="robots" content="{{ $robots }}">@endisset
