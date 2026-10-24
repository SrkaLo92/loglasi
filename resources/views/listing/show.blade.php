@extends('layouts.app')
@use('App\Enums\ListingStatus')
@use('App\Support\Listing\Icons')
@use('App\Support\Listing\Text')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/listing.css') }}">
@endpush

@push('scripts')
<script src="{{ asset_v('js/listing.js') }}" defer></script>
<script src="{{ asset_v('js/lightbox.js') }}" defer></script>
@endpush

@section('content')
@php
	$published = $listing->published_at ?? $listing->created_at;
@endphp
<div class="lg-wrap lgo-page">
	<nav class="lgo lgo-crumbs" aria-label="Putanja">
		<ol class="lgo-crumbs__list">
			@foreach ($breadcrumbs as [$name, $url])
				<li class="lgo-crumbs__item">
					@if ($url && ! $loop->last)
						<a href="{{ $url }}"><span>{{ $name }}</span></a>
					@else
						<span class="lgo-crumbs__here" @if ($loop->last) aria-current="page" @endif>{{ $name }}</span>
					@endif
				</li>
			@endforeach
		</ol>
	</nav>

	@if ($listing->isExpired())
		<p class="lgo lgo-notice lgo-notice--expired" role="status">{{ Icons::listing('info', 20) }}<span><strong>Oglas je istekao.</strong> Ovaj oglas više nije aktivan. Pogledajte slične oglase ispod.</span></p>
	@elseif ($listing->status === ListingStatus::Rejected)
		<p class="lgo lgo-notice lgo-notice--rejected" role="status">{{ Icons::listing('info', 20) }}<span><strong>Oglas je odbijen.</strong> @if ($listing->rejection_reason) Razlog: {{ $listing->rejection_reason }}. @endif Vidite ga samo vi.</span></p>
	@elseif ($listing->status !== ListingStatus::Published)
		<p class="lgo lgo-notice lgo-notice--pending" role="status">{{ Icons::listing('info', 20) }}<span><strong>{{ $listing->status->label() }}.</strong> Oglas još nije javan, vidite ga samo vi.</span></p>
	@endif

	<div class="lgo-grid">
		<div class="lgo-main">
			@include('listing.partials.gallery')

			@if ($fieldValues)
				<section class="lgo lgo-card lgo-fields lgo-o6" aria-labelledby="lgo-fields-title">
					<h2 class="lgo-fields__title" id="lgo-fields-title">Karakteristike</h2>
					{{-- Single values as a table; equipment-like lists (Oprema, Sigurnost...) as chips under their own title. --}}
					@php
						[$fieldLists, $fieldSingles] = collect($fieldValues)->partition(fn ($f) => ($f['items'] ?? []) !== []);
					@endphp
					@if ($fieldSingles->isNotEmpty())
						<dl class="lgo-fields__list">
							@foreach ($fieldSingles as $field)
								<div class="lgo-fields__row lgo-fields__row--{{ $field['key'] }}"><dt>{{ $field['label'] }}</dt><dd>{{ $field['display'] }}</dd></div>
							@endforeach
						</dl>
					@endif
					@foreach ($fieldLists as $field)
						<div class="lgo-fields__group lgo-fields__group--{{ $field['key'] }}">
							<h3 class="lgo-fields__sub">{{ $field['label'] }}</h3>
							<ul class="lgo-fields__chips" role="list">
								@foreach ($field['items'] as $item)
									<li>{{ $item }}</li>
								@endforeach
							</ul>
						</div>
					@endforeach
				</section>
			@endif

			<section class="lgo lgo-card lgo-box lgo-desc lgo-o7" aria-labelledby="lgo-opis">
				<h2 class="lgo-box__title" id="lgo-opis">Opis</h2>
				<ul class="lgo-meta" aria-label="Podaci o oglasu">
					@if ($listing->location)
						<li class="lgo-meta__mesto">{{ Icons::listing('pin') }}<span>
							@if ($listing->category)
								<a href="{{ url_slash('oglasi/'.$listing->category->slug.'/'.$listing->location->slug) }}">{{ $listing->location->name }}</a>
							@else
								{{ $listing->location->name }}
							@endif
						</span></li>
					@endif
					@if ($published)
						<li class="lgo-meta__vreme">{{ Icons::listing('clock') }}<span>Objavljeno <time datetime="{{ $published->copy()->utc()->toIso8601ZuluString() }}" title="{{ Text::exact($published) }}">{{ Text::relative($published) }}</time></span></li>
					@endif
					<li class="lgo-meta__broj">{{ Icons::listing('hash') }}<span>Oglas br. {{ $listing->id }}</span></li>
					<li class="lgo-meta__pregledi">{{ Icons::listing('eye') }}<span>{{ Text::views((int) $listing->views) }}</span></li>
				</ul>
				<div class="lgo-desc__text">
					@if (trim((string) $listing->description) !== '')
						{{ Text::description($listing->description) }}
					@else
						<p class="lgo-muted">Prodavac nije dodao opis.</p>
					@endif
				</div>
			</section>

			<div class="lgo-o10">
				@include('listing.partials.share')
				@if (! $isOwner)
					@include('listing.partials.report')
				@endif
			</div>
		</div>

		<aside class="lgo-side" aria-label="Cena, kontakt i prodavac">
			@include('listing.partials.contact')
			@include('listing.partials.seller')
			@if ($loan)
				@include('listing.partials.loan')
			@endif
			@include('listing.partials.tips')
		</aside>
	</div>

	@if ($similar['listings']->isNotEmpty())
		@php
			$from = $similar['category'];
			$fromCount = $from ? app(\App\Services\Catalog::class)->count($from) : 0;
		@endphp
		<section class="lgo lgo-similar" aria-labelledby="lgo-similar-title">
			<div class="lgo-similar__head">
				<h2 class="lgo-similar__title" id="lgo-similar-title">Slični oglasi</h2>
				@if ($from)
					<a class="lgo-similar__all" href="{{ url_slash('oglasi/'.$from->slug) }}"><span>Svi oglasi: {{ $from->name }}@if ($fromCount > 0) ({{ Text::number($fromCount) }})@endif</span>{{ Icons::listing('arrow') }}</a>
				@endif
			</div>
			<div class="lgo-similar__grid">
				@foreach ($similar['listings'] as $l)
					<div class="lgo-similar__item"><x-listing-card :listing="$l" /></div>
				@endforeach
			</div>
		</section>
	@endif
</div>

@if ($showBar)
	@include('listing.partials.bar')
@endif
@endsection
