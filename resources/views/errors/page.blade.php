{{-- Zajednički sadržaj strane greške (404, 410) u punom izgledu sajta. --}}
@extends('layouts.app', ['title' => ($seoTitle ?? '') !== '' ? $seoTitle : $naslov.' | loglasi.rs', 'robots' => 'noindex, follow'])
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@section('content')
<div class="lg-strana lgx">
	<section class="lgx-greska" aria-labelledby="greska-naslov">
		<p class="lgx-greska__kod" aria-hidden="true">{{ $kod }}</p>
		<h1 class="lgx-greska__naslov" id="greska-naslov">{{ $naslov }}</h1>
		<p class="lgx-greska__tekst">{{ $tekst }}</p>
		<form class="lg-search" role="search" action="{{ Navigation::link('oglasi') }}" method="get">
			<label class="lg-visually-hidden" for="greska-q">Pretraga oglasa</label>
			<div class="lg-search__polje">
				{{ SiteIcons::svg('search', 20, '2') }}
				<input class="lg-search__input" id="greska-q" type="search" name="keyword" placeholder="Šta tražite?" maxlength="100" enterkeyhint="search">
			</div>
			<button class="lg-search__dugme" type="submit">Traži</button>
		</form>
		<div class="lgx-greska__dugmad">
			<a class="lgx-btn lgx-btn--green" href="{{ Navigation::link('oglasi') }}">Svi oglasi</a>
			<a class="lgx-btn lgx-btn--outline" href="{{ url_slash('/') }}">Početna strana</a>
		</div>
	</section>
</div>
@endsection
