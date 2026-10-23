{{-- Placeholder card for the listing module tests; the search module owns and replaces this component. --}}
@props(['listing'])
@php
	$cover = $listing->relationLoaded('coverImage') ? $listing->coverImage : $listing->images->first();
@endphp
<article class="lg-card">
	<a class="lg-card__link" href="{{ url_slash('oglas/'.$listing->slug) }}">
		<div class="lg-card__media">
			@if ($cover)
				<img src="{{ $cover->sizeUrl('sm') }}" alt="" width="400" height="300" loading="lazy" decoding="async">
			@endif
		</div>
		<h3 class="lg-card__title">{{ $listing->title }}</h3>
		<p class="lg-card__price">{{ $listing->priceLabel() }}</p>
		@if ($listing->location)
			<p class="lg-card__place">{{ $listing->location->name }}</p>
		@endif
	</a>
</article>
