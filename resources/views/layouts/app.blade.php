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
@stack('head')
</head>
@php
	$mnav = ! ($hideMobileNav ?? false);
@endphp
<body class="{{ trim(($mnav ? 'lg-has-mnav ' : '').($bodyClass ?? '')) }}">
<a class="lg-skip" href="#sadrzaj">Pređi na sadržaj</a>
@include('partials.header')
<main id="sadrzaj" tabindex="-1">
@yield('content')
</main>
@include('partials.footer')
@if ($mnav)
@include('partials.bottom-nav')
@endif
@stack('scripts')
</body>
</html>
