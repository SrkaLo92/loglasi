{{-- About: what the site is, why local, how it works, how it earns, firms, contact. Texts from o_nama.* (admin). --}}
@extends('layouts.app')
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')
@use('App\Support\Pages\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/pages.css') }}">
@endpush

@section('content')
<div class="lg-page">
	<section class="lgx lgx-about" aria-labelledby="onama-naslov">
		<div class="lgx-hero lgx-about__hero">
			<div class="lgx-hero__text">
				<span class="lgx-label">{{ PageText::get('o_nama.oznaka') }}</span>
				<h1 class="lgx-hero__title" id="onama-naslov">{{ PageText::get('o_nama.hero') }}</h1>
				<p class="lgx-hero__intro">{{ PageText::replace((string) PageText::get('o_nama.uvod')) }}</p>
				<div class="lgx-hero__buttons">
					<a class="lgx-btn lgx-btn--orange" href="{{ Navigation::link('post') }}">{{ SiteIcons::svg('plus', 18) }} Postavite oglas</a>
					<a class="lgx-btn lgx-btn--outline" href="{{ Navigation::link('listings') }}">Pogledajte oglase</a>
				</div>
			</div>
			@if ($stats)
				<dl class="lgx-about__stats">
					@foreach ($stats as $stat)
						<div class="lgx-about__stat">
							<dt>{{ $stat['label'] }}</dt>
							<dd>{{ $stat['value'] }}</dd>
						</div>
					@endforeach
				</dl>
			@endif
		</div>

		<div class="lgx-block">
			<h2 class="lgx-title">{{ PageText::get('o_nama.prednosti.naslov') }}</h2>
			<ul class="lgx-benefits lgx-benefits--four">
				@foreach (PageText::list('o_nama.prednosti') as $benefit)
					@continue(! is_array($benefit) || empty($benefit['naslov']))
					<li class="lgx-benefit">
						<span class="lgx-benefit__icon">{{ SiteIcons::svg((string) ($benefit['ikonica'] ?? 'check'), 26) }}</span>
						<h3 class="lgx-benefit__title">{{ PageText::replace((string) $benefit['naslov']) }}</h3>
						<p class="lgx-benefit__text">{{ PageText::replace((string) ($benefit['tekst'] ?? '')) }}</p>
					</li>
				@endforeach
			</ul>
		</div>

		<div class="lgx-block">
			<h2 class="lgx-title">{{ PageText::get('o_nama.koraci.naslov') }}</h2>
			@include('pages.partials.steps', ['steps' => PageText::list('o_nama.koraci'), 'grid' => true])
		</div>

		<div class="lgx-block lgx-about__two">
			<article class="lgx-about__card">
				<span class="lgx-benefit__icon">{{ SiteIcons::svg('bank', 24) }}</span>
				<h2 class="lgx-about__title">{{ PageText::get('o_nama.zaradjujemo.naslov') }}</h2>
				<p>{{ PageText::replace((string) PageText::get('o_nama.zaradjujemo.tekst')) }}</p>
				<a class="lgx-about__link" href="{{ Navigation::link('pricing') }}">Pogledajte cenovnik {{ SiteIcons::svg('arrow', 18) }}</a>
			</article>
			<article class="lgx-about__card">
				<span class="lgx-benefit__icon">{{ SiteIcons::svg('grid', 24) }}</span>
				<h2 class="lgx-about__title">{{ PageText::get('o_nama.firme.naslov') }}</h2>
				<p>{{ PageText::replace((string) PageText::get('o_nama.firme.tekst')) }}</p>
				<a class="lgx-about__link" href="{{ Navigation::link('for_firms') }}">Za firme {{ SiteIcons::svg('arrow', 18) }}</a>
			</article>
		</div>

		<div class="lgx-cta">
			<div class="lgx-cta__text">
				<h2 class="lgx-cta__title">{{ PageText::get('o_nama.cta.naslov') }}</h2>
				<p>{{ PageText::get('o_nama.cta.tekst') }}</p>
			</div>
			<div class="lgx-cta__buttons">
				<a class="lgx-btn lgx-btn--orange" href="{{ Navigation::link('contact') }}#forma">Pišite nam {{ SiteIcons::svg('arrow', 18) }}</a>
			</div>
		</div>
	</section>
</div>
@endsection
