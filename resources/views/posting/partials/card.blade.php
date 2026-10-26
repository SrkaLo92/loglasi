{{-- Listing card as in lists; the page script updates it while typing (data-pst-card-*). --}}
@php
	$cover = $listing->images->first();
	$images = app(\App\Services\ListingImages::class);
@endphp
<div class="pst-preview-card">
	<div class="pst-preview-card__media">
		@if ($cover)
			<picture>
				<source srcset="{{ $images->url($cover, 'md', 'webp') }}" type="image/webp">
				<img src="{{ $images->url($cover, 'md', 'jpg') }}" alt="" width="{{ $cover->width ?: 800 }}" height="{{ $cover->height ?: 600 }}" loading="lazy">
			</picture>
		@else
			<span class="pst-preview-card__ph">{{ \App\Support\Pages\SiteIcons::svg('image', 32) }}<span>Bez slike</span></span>
		@endif
	</div>
	<div class="pst-preview-card__body">
		<p class="pst-preview-card__title" data-pst-card-title>{{ $listing->title !== '' ? $listing->title : 'Naslov oglasa' }}</p>
		<p class="pst-preview-card__price" data-pst-card-price>{{ $listing->title !== '' || $listing->price !== null ? $listing->priceLabel() : 'Cena' }}</p>
		<p class="pst-preview-card__meta" data-pst-card-location>{{ $listing->location?->name ?? 'Mesto' }}</p>
	</div>
</div>
