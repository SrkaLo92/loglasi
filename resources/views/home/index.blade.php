{{-- Home page: main category tiles, popular categories, then the listing sections. Data: HomeSections (cached 60 s) and CategorySheet. --}}
@extends('layouts.app')
@use('App\Support\Home\HomeSettings')
@use('App\Support\Phone')
@use('App\Support\Search\CategoryIcons')
@use('App\Support\Search\CategorySheet')
@use('App\Support\Search\TimeAgo')
@use('App\Support\Seo')
@use('App\Support\Pages\Pricing')
@use('App\Support\Settings')
@use('App\Support\Pages\PageText')

@push('head')
	<link rel="stylesheet" href="{{ asset_v('css/home.css') }}">
@endpush
@push('scripts')
	<script src="{{ asset_v('js/home.js') }}" defer></script>
@endpush

@php
	$hmTotal = $sections['total'];
	$hmSheet = CategorySheet::get();
	$hmMain = $hmSheet['main'];
	$hmPopular = $hmSheet['popular'];
	$hmWhy = PageText::list('pocetna.zasto');
	$hmFaq = array_slice(array_values(array_filter(PageText::list('faq.pitanja'), fn ($q) => is_array($q) && ! empty($q['pitanje']) && ! empty($q['odgovor']))), 0, 5);
	$hmEmail = (string) Settings::get('contact_email');
	$hmPhone = (string) Settings::get('contact_phone');
	$hmAll = url_slash('/oglasi');
	$hmMobileLatest = HomeSettings::int('home.latest_mobile_count', 1, 24);
	$hmCatTotal = $sections['categories_total'];
	$hmFirms = $sections['firms'];
	$hmAbout = HomeSettings::get('home.about');
	$hmCount = number_format($hmTotal, 0, ',', '.');
	// "Pogledajte svih 30 oglasa", "Pogledajte sva 3 oglasa", "Pogledajte sve oglase (21)"
	$hmMore = match (true) {
		$hmTotal === 1 => 'Pogledajte oglas',
		$hmTotal % 10 === 1 && $hmTotal % 100 !== 11 => 'Pogledajte sve oglase ('.$hmCount.')',
		default => 'Pogledajte '.Pricing::plural($hmTotal, 'svih', 'sva', 'svih').' '.$hmCount.' oglasa',
	};
@endphp

@section('content')
{{-- 1. Intro: H1 and one line; the six tiles below are the way in (as on the apps) --}}
<section class="hm-intro">
	<div class="lg-wrap">
		<h1 class="hm-intro__title">{{ Seo::get()->getH1() }}</h1>
		<p class="hm-intro__text">{{ HomeSettings::get('home.intro_text') }}</p>
	</div>
</section>

<div class="lg-wrap hm">
	{{-- 2. Main categories: five big tiles and "Sve kategorije" (3 x 2 on phones, one row on a computer) --}}
	@if ($hmMain)
		<nav class="hm-main" aria-label="Glavne kategorije">
			<ul class="hm-main__list">
				@foreach ($hmMain as $tile)
					<li>
						<a class="hm-main__tile hm-main__tile--{{ $tile['color'] }}" href="{{ $tile['url'] }}">
							<span class="hm-main__icon">{{ CategoryIcons::svg($tile['icon'], 34) }}</span>
							@if ($tile['tagline'])
								<span class="hm-main__tagline">{{ $tile['tagline'] }}</span>
							@endif
							<span class="hm-main__name">{{ $tile['name'] }}</span>
							<span class="hm-main__count">{{ $tile['count'] > 0 ? TimeAgo::listings($tile['count']) : 'Budite prvi' }}</span>
						</a>
					</li>
				@endforeach
				<li>
					<a class="hm-main__tile hm-main__tile--gray" href="{{ $hmAll }}" data-lg-cats>
						<span class="hm-main__icon">{{ CategoryIcons::svg('grid', 34) }}</span>
						<span class="hm-main__name">Sve kategorije</span>
						<span class="hm-main__count">{{ $hmCatTotal }} {{ Pricing::plural($hmCatTotal, 'kategorija', 'kategorije', 'kategorija') }}</span>
					</a>
				</li>
			</ul>
		</nav>
	@endif

	{{-- 2a. Popular: categories chosen in the admin (home.popular_categories), round icons (same list as the apps) --}}
	@if ($hmPopular)
		<section class="hm-sec hm-pop" aria-labelledby="hm-pop-title">
			<div class="hm-sec__head">
				<h2 class="hm-sec__title" id="hm-pop-title">{{ HomeSettings::get('home.categories_title') }}</h2>
			</div>
			<ul class="hm-pop__list">
				@foreach ($hmPopular as $tile)
					<li>
						<a class="hm-pop__item" href="{{ $tile['url'] }}">
							<span class="hm-pop__icon lg-tone--{{ $tile['color'] }}">{{ CategoryIcons::svg($tile['icon'], 26) }}</span>
							<span class="hm-pop__name">{{ str_replace(' | ', ' · ', $tile['name']) }}</span>
						</a>
					</li>
				@endforeach
			</ul>
		</section>
	@endif

	{{-- 2b. Premium XL + početna (nothing when there are none): wide cards, one column on phones --}}
	@if ($sections['premium']->isNotEmpty())
		<section class="hm-sec hm-premium" aria-labelledby="hm-premium-title">
			<div class="hm-sec__head">
				<h2 class="hm-sec__title" id="hm-premium-title">{{ HomeSettings::get('home.premium_title') }}</h2>
				<a class="hm-sec__link" href="{{ url_slash('/cenovnik') }}">Premium za vaš oglas</a>
			</div>
			<div class="hm-premium__grid">
				@foreach ($sections['premium'] as $listing)
					<x-listing-card :listing="$listing" xl :eager="$loop->index < 2" />
				@endforeach
			</div>
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
					<x-listing-card :listing="$listing" highlight :eager="$sections['premium']->isEmpty() && $loop->index < 2" />
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
					<x-listing-card :listing="$listing" :eager="$sections['premium']->isEmpty() && $sections['featured']->isEmpty() && $loop->index < 2" @class(['hm-hide-m' => $loop->index >= $hmMobileLatest]) />
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

	{{-- 6. Firms: cards for local firms and a small sign-up box; only when firms exist --}}
	@if ($hmFirms['items'])
		<section class="hm-sec hm-firms" aria-labelledby="hm-firms-title">
			<div class="hm-sec__head hm-firms__head">
				<div>
					<h2 class="hm-sec__title" id="hm-firms-title">{{ HomeSettings::get('home.firms_title') }}</h2>
					@if (HomeSettings::get('home.firms_text') !== '')
						<p class="hm-firms__text">{{ HomeSettings::get('home.firms_text') }}</p>
					@endif
				</div>
				@if (Route::has('firms.directory') && ($hmFirms['directory_total'] ?? 0) > 0)
					<a class="hm-sec__link" href="{{ url_slash('/firme') }}">Sve firme ({{ number_format($hmFirms['directory_total'], 0, ',', '.') }}){{ CategoryIcons::svg('arrow', 18, '2') }}</a>
				@endif
			</div>
			<ul class="hm-firms__list">
				@foreach ($hmFirms['items'] as $firm)
					<li>
						<a class="hm-firm hm-firm--{{ $loop->index % 4 }}" href="{{ $firm['url'] }}">
							<span class="hm-firm__cover">
								@if ($firm['cover'])
									<img src="{{ $firm['cover'] }}" alt="" width="320" height="96" loading="lazy" decoding="async">
								@endif
							</span>
							<span class="hm-firm__body">
								<span class="hm-firm__logo">
									@if ($firm['logo'])
										<img src="{{ $firm['logo'] }}" alt="" width="64" height="64" loading="lazy" decoding="async">
									@else
										<span aria-hidden="true">{{ $firm['initials'] }}</span>
									@endif
								</span>
								<span class="hm-firm__title"><span class="hm-firm__name">{{ $firm['name'] }}</span><span class="hm-firm__badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true" focusable="false"><path d="M5 12l5 5L20 7"/></svg>Firma</span></span>
								@if ($firm['activity'] !== '' || $firm['place'] !== '')
									<span class="hm-firm__meta">{{ implode(' · ', array_filter([$firm['activity'], $firm['place']])) }}</span>
								@endif
								<span class="hm-firm__foot"><span class="hm-firm__count">{{ number_format($firm['count'], 0, ',', '.') }} {{ Pricing::plural($firm['count'], 'oglas', 'oglasa', 'oglasa') }}</span><span class="hm-firm__more">Pogledaj profil</span></span>
							</span>
						</a>
					</li>
				@endforeach
			</ul>
			<div class="hm-firms__cta">
				<span class="hm-firms__cta-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/></svg></span>
				<div class="hm-firms__cta-text">
					<h3>{{ HomeSettings::get('home.firms_cta_title') }}</h3>
					@if (Pricing::offerOpen())
						<p>Prvih {{ Pricing::foundingTotal() }} firmi koristi loglasi.rs besplatno 12 meseci. {{ sprintf(Pricing::plural(Pricing::foundingLeft(), 'Ostalo je još %d mesto.', 'Ostala su još %d mesta.', 'Ostalo je još %d mesta.'), Pricing::foundingLeft()) }}</p>
					@else
						<p>{{ HomeSettings::get('home.firms_cta_text') }}</p>
					@endif
				</div>
				<a class="lg-btn hm-firms__btn" href="{{ HomeSettings::url('home.firms_url') }}">{{ HomeSettings::get('home.firms_button') }}</a>
			</div>
		</section>
	@endif

	{{-- 7. Why choose us (the old site's feature block, texts in PageText) --}}
	@if ($hmWhy)
		<section class="hm-sec" aria-labelledby="hm-why-title">
			<h2 class="hm-sec__title" id="hm-why-title">{{ PageText::get('pocetna.zasto.naslov') }}</h2>
			<ul class="hm-why">
				@foreach ($hmWhy as $item)
					<li class="hm-why__item">
						<span class="hm-why__icon">{{ CategoryIcons::svg((string) ($item['ikonica'] ?? 'tag'), 24) }}</span>
						<h3 class="hm-why__title">{{ PageText::replace((string) ($item['naslov'] ?? '')) }}</h3>
						<p>{{ PageText::replace((string) ($item['tekst'] ?? '')) }}</p>
					</li>
				@endforeach
			</ul>
		</section>
	@endif

	{{-- 8. Frequently asked questions: the first ones from the FAQ page, the rest on /faq/ --}}
	@if ($hmFaq)
		<section class="hm-sec hm-faq" aria-labelledby="hm-faq-title">
			<div class="hm-faq__intro">
				<h2 class="hm-sec__title" id="hm-faq-title">{{ PageText::get('pocetna.pitanja.naslov') }}</h2>
				<p>Niste našli odgovor?@if ($hmEmail !== '') Pišite nam na <a href="mailto:{{ $hmEmail }}">{{ $hmEmail }}</a>@endif@if ($hmPhone !== ''){{ $hmEmail !== '' ? ' ili pozovite' : ' Pozovite' }} <a href="tel:{{ $hmPhone }}">{{ Phone::display($hmPhone) }}</a>@endif.</p>
				<a class="hm-faq__all" href="{{ url_slash('/faq') }}">Sva pitanja{{ CategoryIcons::svg('arrow', 18, '2') }}</a>
			</div>
			<div class="hm-faq__list">
				@foreach ($hmFaq as $item)
					<details class="hm-faq__item" name="hm-faq">
						<summary class="hm-faq__question"><span>{{ PageText::replace((string) $item['pitanje']) }}</span>{{ CategoryIcons::svg('chevron', 20, '2.5') }}</summary>
						<p class="hm-faq__answer">{{ PageText::inline((string) $item['odgovor']) }}</p>
					</details>
				@endforeach
			</div>
		</section>
	@endif

	{{-- 9. About the site --}}
	<details class="hm-about">
		<summary class="hm-about__summary"><h2 class="hm-about__title">{{ HomeSettings::get('home.about_title') }}</h2>{{ CategoryIcons::svg('chevron', 20, '2') }}</summary>
		<div class="hm-about__text">{{ PageText::markdown($hmAbout !== '' ? $hmAbout : HomeSettings::ABOUT_DEFAULT) }}</div>
	</details>
</div>
@include('partials.category-sheet')
@endsection
