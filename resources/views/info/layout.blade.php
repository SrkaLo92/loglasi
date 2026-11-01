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
		'' => 'Naslovna',
		'vesti' => 'Vesti',
		'desavanja' => 'Dešavanja',
		'servisne-informacije' => 'Servisne info',
		'price' => 'Priče iz kraja',
		'vodici' => 'Vodiči',
		'firme' => 'Firme',
		'oglasavanje' => 'Oglašavanje',
	];
	$infToday = \App\Support\Info\InfoToday::get();
	$infContact = Settings::get('contact_email', 'kontakt@loglasi.rs');
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
<header>
	<div class="inf-top">
		<div class="inf-wrap inf-top__in">
			<strong>{{ ucfirst(now('Europe/Belgrade')->translatedFormat('l, j. F')) }}</strong>
			@if ($infToday['weather'])
				<span class="inf-top__item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>Loznica {{ $infToday['weather']['temp'] }}°, {{ $infToday['weather']['label'] }}</span>
			@endif
			@if ($infToday['pharmacy'])
				<span class="inf-top__item inf-top__hide">Dežurna apoteka: {{ $infToday['pharmacy']['name'] }}@if ($infToday['pharmacy']['address']), {{ $infToday['pharmacy']['address'] }}@endif</span>
			@endif
			<a class="inf-top__main" href="{{ InfoUrl::main('/') }}">Oglasi na loglasi.rs</a>
		</div>
	</div>
	<div class="inf-head">
		<div class="inf-wrap inf-head__in">
			<a class="inf-logo" href="{{ InfoUrl::to('/') }}" aria-label="loglasi Info, naslovna">
				<img src="{{ asset_v('img/brand/logo.svg') }}" alt="" width="91" height="34">
				<span class="inf-logo__tag">Info</span>
			</a>
			<form class="inf-search" role="search" method="get" action="{{ InfoUrl::to('pretraga') }}">
				<label class="inf-visually-hidden" for="inf-q">Pretraga</label>
				<input id="inf-q" type="search" name="q" value="{{ request()->routeIs('info.search') ? request('q') : '' }}" placeholder="Pretražite vesti, događaje i firme" maxlength="80">
				<button type="submit" aria-label="Traži"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
			</form>
			<a class="inf-send" href="{{ InfoUrl::to('posalji-vest') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Pošalji vest</a>
		</div>
		<nav class="inf-wrap inf-nav" aria-label="Rubrike">
			@foreach ($infMenu as $path => $label)
				<a href="{{ InfoUrl::to($path) }}" @if ($infSection === $path) aria-current="page" @endif>{{ $label }}</a>
			@endforeach
		</nav>
	</div>
</header>
<main id="sadrzaj" tabindex="-1" class="inf-main">
@if (session('info_status'))
	<div class="inf-wrap"><p class="inf-flash" role="status">{{ session('info_status') }}</p></div>
@endif
@yield('content')
</main>
<footer class="inf-foot">
	<div class="inf-wrap inf-foot__in">
		<div class="inf-foot__grid">
			<div class="inf-foot__brand">
				<span class="inf-foot__logo"><img src="{{ asset_v('img/brand/logo-light.svg') }}" alt="loglasi" width="85" height="32"><span>Info</span></span>
				<p>Vesti, dešavanja i korisne informacije iz Loznice i okoline. Deo loglasi.rs, oglasa za naš kraj.</p>
				<a class="inf-btn inf-btn--white" href="{{ InfoUrl::to('posalji-vest') }}">Pošalji vest ili događaj</a>
			</div>
			<nav aria-labelledby="inf-f1"><h2 id="inf-f1">Rubrike</h2>
				<a href="{{ InfoUrl::to('vesti') }}">Vesti</a><a href="{{ InfoUrl::to('desavanja') }}">Dešavanja</a><a href="{{ InfoUrl::to('servisne-informacije') }}">Servisne informacije</a><a href="{{ InfoUrl::to('price') }}">Priče iz kraja</a><a href="{{ InfoUrl::to('vodici') }}">Vodiči</a>
			</nav>
			<nav aria-labelledby="inf-f2"><h2 id="inf-f2">Za firme</h2>
				<a href="{{ InfoUrl::to('firme') }}">Imenik firmi</a><a href="{{ InfoUrl::to('oglasavanje') }}">Oglašavanje i cene</a><a href="{{ InfoUrl::to('oglasavanje') }}#upit">Dodajte svoju firmu</a><a href="{{ InfoUrl::main('/') }}">Oglasi na loglasi.rs</a>
			</nav>
			<nav aria-labelledby="inf-f3"><h2 id="inf-f3">O nama</h2>
				<a href="{{ InfoUrl::main('uslovi-koriscenja') }}">Uslovi korišćenja</a><a href="{{ InfoUrl::main('politika-privatnosti') }}">Politika privatnosti</a><a href="mailto:{{ $infContact }}">{{ $infContact }}</a>
			</nav>
		</div>
		<div class="inf-foot__legal">
			<span>Izdavač: {{ Company::name() }}, {{ Company::address() }}. MB {{ Company::mb() }}, PIB {{ Company::pib() }}. Sažeci vesti drugih medija su kratki, našim rečima i uz link ka izvoru.</span>
			<span>Urednik: {{ Settings::get('info_editor', 'Srđan Marinković') }}</span>
		</div>
	</div>
</footer>
</body>
</html>
