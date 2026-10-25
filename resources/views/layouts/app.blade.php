<!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
@include('partials.seo-head')
<link rel="preload" href="{{ asset('fonts/manrope-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/manrope-latin-ext.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset_v('css/osnova.css') }}">
<meta name="theme-color" content="#ffffff">
<link rel="icon" href="{{ asset_v('img/brand/icon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset_v('img/brand/favicon-32.png') }}" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="{{ asset_v('img/brand/apple-touch-icon.png') }}">
@include('partials.google')
@stack('head')
</head>
@php
	$mnav = ! ($hideMobileNav ?? false);
@endphp
<body class="{{ trim(($mnav ? 'lg-has-mnav ' : '').($bodyClass ?? '')) }}">
<a class="lg-skip" href="#sadrzaj">Pređi na sadržaj</a>
@include('partials.header')
<main id="sadrzaj" tabindex="-1">
@if (auth()->check() && request()->is('moj-nalog', 'moj-nalog/*') && ! request()->is('moj-nalog/obavestenja/odjava/*'))
	@include('partials.account-tabs')
@endif
@yield('content')
</main>
@include('partials.footer')
@if ($mnav)
@include('partials.bottom-nav')
@endif
@include('partials.cookie-bar')
<script src="{{ asset_v('js/recent.js') }}" defer></script>
<script src="{{ asset_v('js/fav.js') }}" defer></script>
@stack('scripts')
</body>
</html>
