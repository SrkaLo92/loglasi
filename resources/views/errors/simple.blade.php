{{-- Error page without the database and session (500, 503): only the logo, message and link. The text is here, not from the settings. --}}
<!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} | loglasi.rs</title>
<meta name="robots" content="noindex">
<link rel="preload" href="{{ asset('fonts/manrope-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/manrope-latin-ext.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset_v('css/base.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/pages.css') }}">
</head>
<body>
<header class="lg-header">
	<div class="lg-wrap lg-header__in">
		<a class="lg-logo" href="{{ url('/') }}/" aria-label="loglasi.rs, početna strana">loglasi<span>.rs</span></a>
	</div>
</header>
<main id="sadrzaj" class="lg-page lgx">
	<section class="lgx-error" aria-labelledby="greska-naslov">
		<p class="lgx-error__code" aria-hidden="true">{{ $code }}</p>
		<h1 class="lgx-error__title" id="greska-naslov">{{ $title }}</h1>
		<p class="lgx-error__text">{{ $text }}</p>
		<div class="lgx-error__buttons">
			<a class="lgx-btn lgx-btn--green" href="{{ url('/') }}/">Početna strana</a>
		</div>
	</section>
</main>
<footer class="lg-footer">
	<div class="lg-wrap lg-footer__bottom">
		<p class="lg-footer__text">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.</p>
	</div>
</footer>
</body>
</html>
