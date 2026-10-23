{{-- Small listing summary (title, price, image, state). $listing may be null or deleted. --}}
@php
	$gone = ! $listing || $listing->trashed();
	$expired = ! $gone && ($listing->status !== \App\Enums\ListingStatus::Published || ($listing->expires_at && $listing->expires_at->isPast()));
@endphp
<div class="lgm-listing">
	<div class="lgm-listing__img" aria-hidden="true">
		@if (! $gone && $thumb)
			<img src="{{ $thumb }}" alt="" width="64" height="48" loading="lazy">
		@endif
	</div>
	<div class="lgm-listing__text">
		@if ($gone)
			<span class="lgm-listing__title">Oglas je obrisan</span>
		@else
			<a class="lgm-listing__title" href="{{ $listing->url() }}">{{ $listing->title }}</a>
			<span class="lgm-listing__price">{{ $listing->priceLabel() }}</span>
			@if ($expired)
				<span class="lgm-badge">Istekao</span>
			@endif
		@endif
	</div>
</div>
