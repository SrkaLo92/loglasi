{{-- About: what the site is, why local, how it works, how it earns, firms, contact. Texts from o_nama.* (admin). --}}
@extends('layouts.app')
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')
@use('App\Support\Pages\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@section('content')
<div class="lg-strana">
	<section class="lgx lgx-onama" aria-labelledby="onama-naslov">
		<div class="lgx-hero lgx-onama__hero">
			<div class="lgx-hero__tekst">
				<span class="lgx-oznaka">{{ PageText::get('o_nama.oznaka') }}</span>
				<h1 class="lgx-hero__naslov" id="onama-naslov">{{ PageText::get('o_nama.hero') }}</h1>
				<p class="lgx-hero__uvod">{{ PageText::replace((string) PageText::get('o_nama.uvod')) }}</p>
				<div class="lgx-hero__dugmad">
					<a class="lgx-btn lgx-btn--orange" href="{{ Navigation::link('postavi') }}">{{ SiteIcons::svg('plus', 18) }} Postavite oglas</a>
					<a class="lgx-btn lgx-btn--outline" href="{{ Navigation::link('oglasi') }}">Pogledajte oglase</a>
				</div>
			</div>
			@if ($stats)
				<dl class="lgx-onama__brojke">
					@foreach ($stats as $stat)
						<div class="lgx-onama__brojka">
							<dt>{{ $stat['label'] }}</dt>
							<dd>{{ $stat['value'] }}</dd>
						</div>
					@endforeach
				</dl>
			@endif
		</div>

		<div class="lgx-blok">
			<h2 class="lgx-naslov">{{ PageText::get('o_nama.prednosti.naslov') }}</h2>
			<ul class="lgx-prednosti lgx-prednosti--cetiri">
				@foreach (PageText::list('o_nama.prednosti') as $prednost)
					@continue(! is_array($prednost) || empty($prednost['naslov']))
					<li class="lgx-prednost">
						<span class="lgx-prednost__ikonica">{{ SiteIcons::svg((string) ($prednost['ikonica'] ?? 'check'), 26) }}</span>
						<h3 class="lgx-prednost__naslov">{{ PageText::replace((string) $prednost['naslov']) }}</h3>
						<p class="lgx-prednost__tekst">{{ PageText::replace((string) ($prednost['tekst'] ?? '')) }}</p>
					</li>
				@endforeach
			</ul>
		</div>

		<div class="lgx-blok">
			<h2 class="lgx-naslov">{{ PageText::get('o_nama.koraci.naslov') }}</h2>
			@include('pages.partials.steps', ['koraci' => PageText::list('o_nama.koraci'), 'mreza' => true])
		</div>

		<div class="lgx-blok lgx-onama__dve">
			<article class="lgx-onama__kartica">
				<span class="lgx-prednost__ikonica">{{ SiteIcons::svg('bank', 24) }}</span>
				<h2 class="lgx-onama__naslov">{{ PageText::get('o_nama.zaradjujemo.naslov') }}</h2>
				<p>{{ PageText::replace((string) PageText::get('o_nama.zaradjujemo.tekst')) }}</p>
				<a class="lgx-onama__link" href="{{ Navigation::link('cenovnik') }}">Pogledajte cenovnik {{ SiteIcons::svg('arrow', 18) }}</a>
			</article>
			<article class="lgx-onama__kartica">
				<span class="lgx-prednost__ikonica">{{ SiteIcons::svg('grid', 24) }}</span>
				<h2 class="lgx-onama__naslov">{{ PageText::get('o_nama.firme.naslov') }}</h2>
				<p>{{ PageText::replace((string) PageText::get('o_nama.firme.tekst')) }}</p>
				<a class="lgx-onama__link" href="{{ Navigation::link('za_firme') }}">Za firme {{ SiteIcons::svg('arrow', 18) }}</a>
			</article>
		</div>

		<div class="lgx-cta">
			<div class="lgx-cta__tekst">
				<h2 class="lgx-cta__naslov">{{ PageText::get('o_nama.cta.naslov') }}</h2>
				<p>{{ PageText::get('o_nama.cta.tekst') }}</p>
			</div>
			<div class="lgx-cta__dugmad">
				<a class="lgx-btn lgx-btn--orange" href="{{ Navigation::link('kontakt') }}#forma">Pišite nam {{ SiteIcons::svg('arrow', 18) }}</a>
			</div>
		</div>
	</section>
</div>
@endsection
