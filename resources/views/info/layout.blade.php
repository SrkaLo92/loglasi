{{-- Info portal layout: own header, menu and footer; same brand, fonts and colours as loglasi.rs.
     Variables: $title, $description, $canonical (default: this address), $noindex, $section (active menu item). --}}
@use('App\Support\Info\InfoUrl')
@use('App\Support\Pages\Company')
@use('App\Support\Settings')
@php
	$infTitle = isset($title) ? $title.' | loglasi Info' : 'loglasi Info: vesti, dešavanja i servisne informacije za Loznicu';
	$infDescription = $description ?? 'Vesti, dešavanja, isključenja struje i vode, dežurna apoteka i firme iz Loznice i okoline, na jednom mestu.';
	$infCanonical = $canonical ?? InfoUrl::to(request()->path());
	$infIndex = config('info.indexable') && ! ($noindex ?? false);
	$infSection = $section ?? null;
	$infMenu = [
		'' => 'Početna',
		'servisne-informacije' => 'Servisne info',
		'desavanja' => 'Dešavanja',
		'vesti' => 'Vesti',
		'price' => 'Priče iz Loznice',
		'vodici' => 'Vodiči',
		'firme' => 'Firme',
	];
@endphp
<!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $infTitle }}</title>
<meta name="description" content="{{ $infDescription }}">
<meta name="robots" content="{{ $infIndex ? 'index, follow, max-image-preview:large' : 'noindex, follow' }}">
<link rel="canonical" href="{{ $infCanonical }}">
<meta property="og:site_name" content="loglasi Info">
<meta property="og:locale" content="sr_RS">
<meta property="og:title" content="{{ $title ?? 'loglasi Info' }}">
<meta property="og:description" content="{{ $infDescription }}">
<meta property="og:url" content="{{ $infCanonical }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:image" content="{{ $ogImage ?? asset('img/loglasi-og.png') }}">
<link rel="preload" href="{{ asset('fonts/manrope-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/manrope-latin-ext.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset_v('css/base.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/info.css') }}">
<meta name="theme-color" content="#1f5b4a">
<link rel="icon" href="{{ asset_v('img/brand/icon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset_v('img/brand/favicon-32.png') }}" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="{{ asset_v('img/brand/apple-touch-icon.png') }}">
@foreach (($jsonld ?? []) as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
@include('partials.google')
@stack('head')
</head>
<body class="inf">
<a class="lg-skip" href="#sadrzaj">Pređi na sadržaj</a>
<header class="inf-head">
	<div class="inf-wrap inf-head__in">
		<a class="inf-logo" href="{{ InfoUrl::to('/') }}" aria-label="loglasi Info, početna">
			<img src="{{ asset_v('img/brand/logo.svg') }}" alt="" width="120" height="45">
			<span class="inf-logo__tag">INFO</span>
		</a>
		<p class="inf-head__place">Vesti i dešavanja · Loznica i okolina</p>
		<a class="inf-head__main" href="{{ InfoUrl::main('/') }}">Oglasi na loglasi.rs <span aria-hidden="true">→</span></a>
	</div>
	<nav class="inf-nav" aria-label="Rubrike">
		<div class="inf-wrap inf-nav__in">
			@foreach ($infMenu as $path => $label)
				<a href="{{ InfoUrl::to($path) }}" @if ($infSection === $path) aria-current="page" @endif>{{ $label }}</a>
			@endforeach
			<a class="inf-nav__send" href="{{ InfoUrl::to('posalji-vest') }}">Pošalji vest <span aria-hidden="true">→</span></a>
		</div>
	</nav>
</header>
<main id="sadrzaj" tabindex="-1" class="inf-main">
@if (session('info_status'))
	<div class="inf-wrap"><p class="inf-flash" role="status">{{ session('info_status') }}</p></div>
@endif
@yield('content')
</main>
<nav class="inf-bottom" aria-label="Brza navigacija">
	<a href="{{ InfoUrl::to('/') }}" @if ($infSection === '') aria-current="page" @endif>Početna</a>
	<a href="{{ InfoUrl::to('desavanja') }}" @if ($infSection === 'desavanja') aria-current="page" @endif>Dešavanja</a>
	<a href="{{ InfoUrl::to('servisne-informacije') }}" @if ($infSection === 'servisne-informacije') aria-current="page" @endif>Servisne</a>
	<a href="{{ InfoUrl::main('/') }}">Oglasi</a>
</nav>
<footer class="inf-foot">
	<div class="inf-wrap inf-foot__in">
		<div>
			<p class="inf-foot__name">loglasi Info</p>
			<p>Vesti, dešavanja i korisne informacije za Loznicu i okolinu.</p>
			<p>Izdavač: {{ Company::name() }}, {{ Company::address() }}, MB {{ Company::mb() }}, PIB {{ Company::pib() }}.<br>
			Glavni i odgovorni urednik: {{ Settings::get('info_editor', 'Srđan Marinković') }}.</p>
		</div>
		<div>
			<p>Sažeci vesti drugih medija objavljuju se kratko, našim rečima i uz link ka izvoru. Za ispravku ili uklanjanje pišite na <a href="mailto:{{ Settings::get('contact_email', 'kontakt@loglasi.rs') }}">{{ Settings::get('contact_email', 'kontakt@loglasi.rs') }}</a>.</p>
			<p><a href="{{ InfoUrl::main('/') }}">loglasi.rs</a> · <a href="{{ InfoUrl::main('uslovi-koriscenja') }}">Uslovi korišćenja</a> · <a href="{{ InfoUrl::main('politika-privatnosti') }}">Privatnost</a></p>
		</div>
	</div>
</footer>
</body>
</html>
