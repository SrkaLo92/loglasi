{{-- Listing list: /oglasi/, /oglasi/{kategorija}/, /oglasi/{kategorija}/{mesto}/ (filters are a GET form, no JavaScript needed). --}}
@extends('layouts.app')
@use('App\Support\Search\CategoryIcons')
@use('App\Support\Search\TimeAgo')
@use('App\Support\Search\WebFilters')
@use('App\Support\Seo')

@push('head')
	<link rel="stylesheet" href="{{ asset_v('css/search.css') }}">
@endpush
@push('scripts')
	<script src="{{ asset_v('js/search.js') }}" defer></script>
@endpush

@php
	/** @var WebFilters $filters */
	$srChips = $filters->chips();
	$srCount = $filters->count();
	$srFaq = $category && ! $location ? Seo::faqItems($category->seo_faq) : [];
@endphp

@section('content')
<div class="lg-wrap sr">
	<nav class="sr-crumbs" aria-label="Putanja">
		<ol>
			<li><a href="{{ url_slash('/') }}">Početna</a></li>
			@if ($category)
				<li><a href="{{ url_slash('/oglasi') }}">Oglasi</a></li>
				@foreach ($crumbs as $crumb)
					@if ($loop->last && ! $location)
						<li><span aria-current="page">{{ $crumb->name }}</span></li>
					@else
						<li><a href="{{ $crumb->url() }}">{{ $crumb->name }}</a></li>
					@endif
				@endforeach
				@if ($location)
					<li><span aria-current="page">{{ $location->name }}</span></li>
				@endif
			@else
				<li><span aria-current="page">Oglasi</span></li>
			@endif
		</ol>
	</nav>

	<div class="sr-head">
		<h1 class="sr-head__title">{{ Seo::get()->getH1() }}</h1>
		<p class="sr-head__count" role="status">{{ TimeAgo::listings($total) }}</p>
	</div>
	@if ($category && ! $location && $results->currentPage() === 1 && trim((string) $category->seo_intro) !== '')
		<p class="sr-intro">{{ trim(strip_tags((string) $category->seo_intro)) }}</p>
	@endif

	<div class="sr-mtools">
		<a class="sr-mtools__btn{{ $srCount ? ' is-active' : '' }}" href="#filteri">{{ CategoryIcons::svg('filter', 18, '2') }}Filteri{{ $srCount ? ' ('.$srCount.')' : '' }}</a>
	</div>

	<div class="sr-layout">
		<aside class="sr-filters" id="filteri" aria-labelledby="sr-filters-title">
			<div class="sr-filters__panel">
				<div class="sr-filters__head">
					<h2 class="sr-filters__title" id="sr-filters-title">Filteri</h2>
					<a class="sr-filters__close" href="#sadrzaj" aria-label="Zatvori filtere">{{ CategoryIcons::svg('close', 22, '2') }}</a>
				</div>

				<nav class="sr-cats" aria-label="Kategorije">
					<h3 class="sr-group__title">Kategorija</h3>
					<ul>
						@if ($categoryLinks['up'])
							<li><a class="sr-cats__up" href="{{ $categoryLinks['up']['url'] }}">‹ {{ $categoryLinks['up']['name'] }}</a></li>
						@endif
						@if ($categoryLinks['current'])
							<li><span class="sr-cats__current" aria-current="page">{{ $categoryLinks['current']['name'] }} ({{ number_format($categoryLinks['current']['count'], 0, ',', '.') }})</span></li>
						@endif
						@foreach ($categoryLinks['items'] as $item)
							<li><a class="{{ $categoryLinks['current'] ? 'sr-cats__child' : '' }}" href="{{ $item['url'] }}">{{ $item['name'] }} <span class="sr-cats__n">({{ number_format($item['count'], 0, ',', '.') }})</span></a></li>
						@endforeach
					</ul>
				</nav>

				<form class="sr-form" id="sr-form" method="get" action="{{ $filters->formAction() }}">
					<div class="sr-group">
						<label class="sr-group__title" for="sr-keyword">Ključna reč</label>
						<input class="sr-input" id="sr-keyword" type="search" name="keyword" value="{{ $filters->query['keyword'] ?? '' }}" maxlength="100" placeholder="npr. Golf, stan, bicikl" enterkeyhint="search">
					</div>

					<fieldset class="sr-group">
						<legend class="sr-group__title">Cena</legend>
						<div class="sr-range">
							<label>Od<input class="sr-input" type="text" inputmode="numeric" pattern="[0-9 .]*" name="cena_od" value="{{ $filters->query['cena_od'] ?? '' }}" placeholder="0" autocomplete="off"></label>
							<label>Do<input class="sr-input" type="text" inputmode="numeric" pattern="[0-9 .]*" name="cena_do" value="{{ $filters->query['cena_do'] ?? '' }}" autocomplete="off"></label>
							<label>Valuta<select class="sr-input" name="valuta">
								<option value="EUR" @selected($filters->currency()->value === 'EUR')>€</option>
								<option value="RSD" @selected($filters->currency()->value === 'RSD')>din</option>
							</select></label>
						</div>
					</fieldset>

					@if ($locations->isNotEmpty())
						<div class="sr-group">
							<label class="sr-group__title" for="sr-place">Mesto</label>
							<select class="sr-input" id="sr-place" name="mesto">
								<option value="">Loznica i okolina</option>
								@foreach ($locations as $place)
									<option value="{{ $place->slug }}" @selected($filters->placeSlug() === $place->slug)>{{ $place->name }}</option>
								@endforeach
							</select>
						</div>
					@endif

					@foreach ($fieldFilters as $field)
						@include('search.partials.field', ['field' => $field])
					@endforeach

					<fieldset class="sr-group">
						<legend class="sr-group__title">Prodavac</legend>
						@foreach (['' => 'Svi', 'privatno' => 'Privatna lica', 'firma' => 'Samo firme'] as $value => $label)
							<label class="sr-check"><input type="radio" name="prodavac" value="{{ $value }}" @checked(($filters->query['prodavac'] ?? '') === $value)>{{ $label }}</label>
						@endforeach
					</fieldset>

					<div class="sr-form__actions">
						<button class="lg-btn lg-btn--green" type="submit">Prikaži oglase</button>
						@if ($filters->hasFilters() || $location)
							<a class="sr-form__clear" href="{{ $filters->urlFor($category) }}">Obriši filtere</a>
						@endif
					</div>
				</form>
			</div>
		</aside>

		<div class="sr-main">
			<div class="sr-toolbar">
				@if ($srChips)
					<ul class="sr-chips" aria-label="Izabrani filteri">
						@foreach ($srChips as $chip)
							<li class="sr-chip">{{ $chip['label'] }}<a class="sr-chip__x" href="{{ $chip['url'] }}" aria-label="Ukloni filter {{ $chip['label'] }}">×</a></li>
						@endforeach
						<li><a class="sr-chips__clear" href="{{ $filters->urlClear() }}">Obriši sve</a></li>
					</ul>
				@endif
				<div class="sr-toolbar__end">
					@auth
						<form class="sr-save" method="post" action="{{ url_slash('/moj-nalog/pretrage') }}">
							@csrf
							@foreach (\Illuminate\Support\Arr::dot($filters->savedSearchQuery()) as $name => $value)
								@php($inputName = str_contains($name, '.') ? preg_replace('/^([^.]+)\.(.+)$/', '$1[$2]', $name) : $name)
								<input type="hidden" name="{{ $inputName }}" value="{{ $value }}">
							@endforeach
							<button class="sr-save__btn" type="submit">Sačuvaj pretragu</button>
						</form>
					@endauth
					<div class="sr-sort">
						<label for="sr-sort">Poređaj</label>
						<select class="sr-input" id="sr-sort" name="sortiranje" form="sr-form" data-autosubmit>
							@foreach (WebFilters::SORT_LABELS as $value => $label)
								@continue($value === 'relevantnost' && ! isset($filters->query['keyword']))
								<option value="{{ $value }}" @selected($filters->sort() === $value)>{{ $label }}</option>
							@endforeach
						</select>
						<noscript><button class="sr-sort__go" type="submit" form="sr-form">Primeni</button></noscript>
					</div>
				</div>
			</div>

			@if ($filters->droppedFilters)
				<p class="sr-notice" role="status">Neki filteri nisu ispravni, pa nisu primenjeni.</p>
			@endif

			@if ($results->isEmpty())
				<div class="sr-empty">
					<h2 class="sr-empty__title">Nema oglasa za ovu pretragu</h2>
					<p>{{ $filters->hasFilters() ? 'Probajte sa manje filtera ili drugom rečju.' : 'U ovom delu još nema aktivnih oglasa.' }}</p>
					<p class="sr-empty__actions">
						@if ($filters->hasFilters())
							<a class="lg-btn lg-btn--outline" href="{{ $filters->urlClear() }}">Obriši filtere</a>
						@endif
						<a class="lg-btn lg-btn--outline" href="{{ url_slash('/oglasi') }}">Svi oglasi</a>
						<a class="lg-btn lg-btn--orange" href="{{ url_slash('/postavi-oglas') }}">Postavite oglas</a>
					</p>
				</div>
			@else
				<div class="sr-grid">
					@foreach ($results as $listing)
						<x-listing-card :listing="$listing" layout="list" :eager="$loop->index < 4" heading="h2" />
					@endforeach
				</div>
				@include('search.partials.pagination', ['results' => $results, 'filters' => $filters])
			@endif

			@if ($srFaq)
				<section class="sr-faq" aria-labelledby="sr-faq-title">
					<h2 class="sr-faq__title" id="sr-faq-title">{{ Seo::text('category.faq_title', ['{kategorija}' => Seo::categoryName($category)]) }}</h2>
					@foreach ($srFaq as $item)
						<details class="sr-faq__item">
							<summary>{{ $item['q'] }}</summary>
							<p>{{ $item['a'] }}</p>
						</details>
					@endforeach
				</section>
			@endif

			@if ($category && trim((string) $category->description) !== '' && ! $location && trim((string) $category->seo_intro) === '')
				<details class="sr-about">
					<summary>O kategoriji {{ $category->name }}</summary>
					<div class="sr-about__text">{!! nl2br(e(strip_tags($category->description))) !!}</div>
				</details>
			@endif
		</div>
	</div>
</div>
@endsection
