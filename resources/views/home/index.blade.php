{{-- Home page: eight sections ported from loglasi-core inc/home. Data: HomeSections (cached 60 s). --}}
@extends('layouts.app')
@use('App\Support\Home\HomeSettings')
@use('App\Support\Search\CategoryIcons')
@use('App\Support\Search\TimeAgo')
@use('App\Support\Seo')
@use('App\Support\Stranice\Cenovnik')
@use('App\Support\Stranice\PageText')

@push('head')
	<link rel="stylesheet" href="{{ asset_v('css/home.css') }}">
@endpush
@push('scripts')
	<script src="{{ asset_v('js/home.js') }}" defer></script>
@endpush

@php
	$hmTotal = $sections['total'];
	$hmAll = url_slash('/oglasi');
	$hmMobileLatest = HomeSettings::int('home.latest_mobile_count', 1, 24);
	$hmCatTotal = $sections['categories_total'];
	$hmFree = Cenovnik::freeListings();
	$hmFirms = $sections['firms'];
	$hmAbout = HomeSettings::get('home.about');
	$hmCount = number_format($hmTotal, 0, ',', '.');
	// „Pogledajte svih 30 oglasa”, „Pogledajte sva 3 oglasa”, „Pogledajte sve oglase (21)”
	$hmMore = match (true) {
		$hmTotal === 1 => 'Pogledajte oglas',
		$hmTotal % 10 === 1 && $hmTotal % 100 !== 11 => 'Pogledajte sve oglase ('.$hmCount.')',
		default => 'Pogledajte '.Cenovnik::plural($hmTotal, 'svih', 'sva', 'svih').' '.$hmCount.' oglasa',
	};
@endphp

@section('content')
{{-- 1. Intro --}}
<section class="hm-intro">
	<div class="lg-wrap">
		<h1 class="hm-intro__title">{{ Seo::get()->getH1() }}</h1>
		<p class="hm-intro__text">{{ HomeSettings::get('home.intro_text') }}</p>
		@if ($sections['chips'])
			<div class="hm-chips">
				<span class="hm-chips__label" id="hm-chips-label">{{ HomeSettings::get('home.intro_label') }}</span>
				<ul class="hm-chips__list" aria-labelledby="hm-chips-label">
					@foreach ($sections['chips'] as $chip)
						<li><a class="hm-chip" href="{{ $chip['url'] }}">{{ $chip['label'] }}</a></li>
					@endforeach
				</ul>
			</div>
		@endif
	</div>
</section>

<div class="lg-wrap hm">
	{{-- 2. Categories --}}
	@if ($sections['categories'])
		<section class="hm-sec" aria-labelledby="hm-cats-title">
			<div class="hm-sec__head">
				<h2 class="hm-sec__title" id="hm-cats-title">{{ HomeSettings::get('home.categories_title') }}</h2>
				<a class="hm-sec__link hm-hide-m" href="{{ $hmAll }}">Sve kategorije ({{ $hmCatTotal }})</a>
				<a class="hm-sec__link hm-show-m" href="{{ $hmAll }}">Sve ({{ $hmCatTotal }})</a>
			</div>
			<ul class="hm-cats">
				@foreach ($sections['categories'] as $item)
					<li class="{{ $loop->index >= 7 ? 'hm-hide-m' : '' }}">
						<a class="hm-cat" href="{{ $item['category']->url() }}">
							<span class="hm-cat__icon">{{ CategoryIcons::forCategory($item['category'], 20) }}</span>
							<span class="hm-cat__text">
								<span class="hm-cat__name">{{ $item['category']->name }}</span>
								<span class="hm-cat__count">{{ TimeAgo::listings($item['count']) }}</span>
							</span>
						</a>
					</li>
				@endforeach
				<li>
					<a class="hm-cat hm-cat--all" href="{{ $hmAll }}">
						<span class="hm-cat__icon">{{ CategoryIcons::svg('grid', 20) }}</span>
						<span class="hm-cat__text">
							<span class="hm-cat__name">Sve kategorije</span>
							<span class="hm-cat__count">{{ $hmCatTotal }} {{ Cenovnik::plural($hmCatTotal, 'kategorija', 'kategorije', 'kategorija') }}</span>
						</span>
					</a>
				</li>
			</ul>
		</section>
	@endif

	{{-- 3. Featured (nothing when there are none) --}}
	@if ($sections['featured']->isNotEmpty())
		<section class="hm-sec" aria-labelledby="hm-featured-title">
			<div class="hm-sec__head">
				<h2 class="hm-sec__title" id="hm-featured-title">{{ HomeSettings::get('home.featured_title') }}</h2>
				<a class="hm-sec__link" href="{{ HomeSettings::url('home.featured_link_url') }}">{{ HomeSettings::get('home.featured_link_text') }}</a>
			</div>
			<div class="hm-grid">
				@foreach ($sections['featured'] as $listing)
					<x-listing-card :listing="$listing" highlight :eager="$loop->index < 2" />
				@endforeach
			</div>
		</section>
	@endif

	{{-- 4. Latest --}}
	<section class="hm-sec" aria-labelledby="hm-latest-title">
		<div class="hm-sec__head">
			<h2 class="hm-sec__title" id="hm-latest-title">{{ HomeSettings::get('home.latest_title') }}</h2>
			@if ($hmTotal > 0)
				<a class="hm-sec__link" href="{{ $hmAll }}">Pogledajte sve</a>
			@endif
		</div>
		@if ($sections['latest']->isEmpty())
			<div class="hm-empty">
				<p>Još nema oglasa. Budite prvi koji će postaviti oglas za Loznicu i okolinu.</p>
				<a class="lg-btn lg-btn--orange" href="{{ url_slash('/postavi-oglas') }}">Postavite oglas</a>
			</div>
		@else
			<div class="hm-grid">
				@foreach ($sections['latest'] as $listing)
					<x-listing-card :listing="$listing" :eager="$sections['featured']->isEmpty() && $loop->index < 2" @class(['hm-hide-m' => $loop->index >= $hmMobileLatest]) />
				@endforeach
			</div>
			<p class="hm-more">
				<a class="lg-btn lg-btn--outline" href="{{ $hmAll }}">{{ $hmMore }}</a>
			</p>
		@endif
	</section>

	{{-- 5. Tabs (desktop; without JavaScript every panel shows with its heading) --}}
	@if ($sections['tabs'])
		<section class="hm-sec hm-tabs" aria-labelledby="hm-tabs-title" data-tabs>
			<h2 class="hm-sec__title" id="hm-tabs-title">{{ HomeSettings::get('home.tabs_title') }}</h2>
			<div class="hm-tabs__list" role="tablist" aria-labelledby="hm-tabs-title" hidden>
				@foreach ($sections['tabs'] as $tab)
					<button class="hm-tabs__tab" type="button" role="tab" id="hm-tab-{{ $loop->index }}" aria-controls="hm-panel-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}">{{ $tab['category']->name }} ({{ number_format($tab['count'], 0, ',', '.') }})</button>
				@endforeach
			</div>
			@foreach ($sections['tabs'] as $tab)
				<div class="hm-tabs__panel" id="hm-panel-{{ $loop->index }}" role="tabpanel" aria-labelledby="hm-tab-{{ $loop->index }}" tabindex="0">
					<h3 class="hm-tabs__heading">{{ $tab['category']->name }}</h3>
					<div class="hm-grid">
						@foreach ($tab['listings'] as $listing)
							<x-listing-card :listing="$listing" heading="h4" />
						@endforeach
					</div>
					<p class="hm-tabs__all"><a href="{{ $tab['category']->url() }}">Svi oglasi u kategoriji {{ $tab['category']->name }} ({{ number_format($tab['count'], 0, ',', '.') }})</a></p>
				</div>
			@endforeach
		</section>
	@endif

	{{-- 6. Firms --}}
	<section class="hm-firms" aria-labelledby="hm-firms-title">
		<span class="hm-firms__badge">FIRMA</span>
		<h2 class="hm-firms__title" id="hm-firms-title">{{ HomeSettings::get('home.firms_title') }}</h2>
		<p class="hm-firms__text">{{ HomeSettings::get('home.firms_text') }}</p>
		@php($hmOffer = Cenovnik::offerText())
		@if ($hmOffer !== '')
			<p class="hm-firms__offer">{{ $hmOffer }}@if (HomeSettings::get('home.firms_show_remaining') && Cenovnik::offerOpen()) {{ sprintf(Cenovnik::plural(Cenovnik::foundingLeft(), 'Ostalo je još %d besplatno mesto od %d.', 'Ostala su još %d besplatna mesta od %d.', 'Ostalo je još %d besplatnih mesta od %d.'), Cenovnik::foundingLeft(), Cenovnik::foundingTotal()) }}@endif</p>
		@endif
		@if ($hmFirms['items'])
			<div class="hm-firms__strip">
				<p class="hm-firms__count">Na loglasi.rs {{ Cenovnik::plural($hmFirms['total'], 'je', 'su', 'su') }} već {{ number_format($hmFirms['total'], 0, ',', '.') }} {{ Cenovnik::plural($hmFirms['total'], 'firma', 'firme', 'firmi') }}</p>
				<ul class="hm-firms__list">
					@foreach ($hmFirms['items'] as $firm)
						<li>
							<a class="hm-firm" href="{{ $firm['url'] }}">
								<span class="hm-firm__logo">
									@if ($firm['logo'])
										<img src="{{ $firm['logo'] }}" alt="" width="64" height="64" loading="lazy" decoding="async">
									@else
										<span aria-hidden="true">{{ $firm['initials'] }}</span>
									@endif
								</span>
								<span class="hm-firm__name">{{ $firm['name'] }}</span>
							</a>
						</li>
					@endforeach
				</ul>
			</div>
		@endif
		<a class="hm-firms__btn" href="{{ HomeSettings::url('home.firms_url') }}">{{ HomeSettings::get('home.firms_button') }}{{ CategoryIcons::svg('arrow', 18, '2') }}</a>
	</section>

	{{-- 7. Why loglasi.rs --}}
	<section class="hm-sec" aria-labelledby="hm-why-title">
		<h2 class="hm-sec__title" id="hm-why-title">{{ HomeSettings::get('home.why_title') }}</h2>
		<ul class="hm-why">
			<li class="hm-why__item">
				<span class="hm-why__icon">{{ CategoryIcons::svg('pin', 24) }}</span>
				<h3 class="hm-why__title">Samo Loznica i okolina</h3>
				<p>Oglasi iz Loznice, Banje Koviljače, Lešnice, Tršića i okolnih sela. Sve je blizu, pa stvar možete da pogledate uživo pre nego što kupite.</p>
			</li>
			<li class="hm-why__item">
				<span class="hm-why__icon">{{ CategoryIcons::svg('gift', 24) }}</span>
				<h3 class="hm-why__title">Postavljanje je besplatno</h3>
				<p>{{ $hmFree > 0 ? ucfirst(Cenovnik::freeListingsText()).' bez ikakve naplate.' : 'Oglase postavljate bez ikakve naplate.' }} Nema provizije ni skrivenih troškova, plaćate samo ako želite da istaknete oglas.</p>
			</li>
			<li class="hm-why__item">
				<span class="hm-why__icon">{{ CategoryIcons::svg('chat', 24) }}</span>
				<h3 class="hm-why__title">Direktno sa prodavcem</h3>
				<p>Dogovarate se direktno, porukom ili telefonom, bez posrednika i bez provizije.</p>
			</li>
		</ul>
	</section>

	{{-- 8. About the site --}}
	<details class="hm-about">
		<summary class="hm-about__summary"><h2 class="hm-about__title">{{ HomeSettings::get('home.about_title') }}</h2>{{ CategoryIcons::svg('chevron', 20, '2') }}</summary>
		<div class="hm-about__text">{{ PageText::markdown($hmAbout !== '' ? $hmAbout : HomeSettings::ABOUT_DEFAULT) }}</div>
	</details>
</div>
@endsection
