<!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
@include('partials.seo-head')
<link rel="preload" href="{{ asset('fonts/manrope-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/manrope-latin-ext.woff2') }}" as="font" type="font/woff2" crossorigin>
@include('partials.scroll-restore')
<link rel="stylesheet" href="{{ asset_v('css/base.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/pick.css') }}">
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
<div class="lg-acct lg-wrap">
	<aside class="lg-acct__side">@include('partials.account-tabs')</aside>
	<div class="lg-acct__main">
		@unless (request()->is('moj-nalog'))
			{{-- One way back on a phone: to the section the page belongs to (a conversation goes back to all messages). --}}
			@php
				$lgBack = match (true) {
					request()->is('moj-nalog/poruke/novo') => [null, null],
					request()->is('moj-nalog/poruke/*') => ['moj-nalog/poruke', 'Sve poruke'],
					request()->is('moj-nalog/oglasi/*') => ['moj-nalog/oglasi', 'Moji oglasi'],
					request()->is('moj-nalog/obavestenja/*') => ['moj-nalog/obavestenja', 'Obaveštenja'],
					request()->is('moj-nalog/narudzbine/*') => ['moj-nalog/narudzbine', 'Narudžbine'],
					default => ['moj-nalog', 'Moj nalog'],
				};
			@endphp
			@if ($lgBack[0])
				<a class="lg-acct__back" href="{{ url_slash($lgBack[0]) }}">{{ \App\Support\Pages\SiteIcons::svg('chevron-l', 18) }} {{ $lgBack[1] }}</a>
			@endif
		@endunless
		@yield('content')
	</div>
</div>
@else
@yield('content')
@endif
</main>
@include('partials.footer')
@if ($mnav)
@include('partials.bottom-nav')
@endif
@include('partials.cookie-bar')
<script src="{{ asset_v('js/pick.js') }}" defer></script>
<script src="{{ asset_v('js/accordion.js') }}" defer></script>
<script src="{{ asset_v('js/recent.js') }}" defer></script>
<script src="{{ asset_v('js/fav.js') }}" defer></script>
<script src="{{ asset_v('js/password.js') }}" defer></script>
@stack('scripts')
</body>
</html>
