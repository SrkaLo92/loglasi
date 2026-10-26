{{-- Strana greške bez baze i sesije (500, 503): samo logo, poruka i link. Tekst je ovde, ne iz podešavanja. --}}
<!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $naslov }} | loglasi.rs</title>
<meta name="robots" content="noindex">
<link rel="preload" href="{{ asset('fonts/manrope-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/manrope-latin-ext.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset_v('css/osnova.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
</head>
<body>
<header class="lg-header">
	<div class="lg-wrap lg-header__in">
		<a class="lg-logo" href="{{ url('/') }}/" aria-label="loglasi.rs, početna strana">loglasi<span>.rs</span></a>
	</div>
</header>
<main id="sadrzaj" class="lg-strana lgx">
	<section class="lgx-greska" aria-labelledby="greska-naslov">
		<p class="lgx-greska__kod" aria-hidden="true">{{ $kod }}</p>
		<h1 class="lgx-greska__naslov" id="greska-naslov">{{ $naslov }}</h1>
		<p class="lgx-greska__tekst">{{ $tekst }}</p>
		<div class="lgx-greska__dugmad">
			<a class="lgx-btn lgx-btn--green" href="{{ url('/') }}/">Početna strana</a>
		</div>
	</section>
</main>
<footer class="lg-footer">
	<div class="lg-wrap lg-footer__dno">
		<p class="lg-footer__tekst">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.</p>
	</div>
</footer>
</body>
</html>
