{{--
	"Istakni 7 dana / 30 dana", "Podigni oglas" for the owner of a listing.
	Include from "Moji oglasi" and the owner box on the listing page:
	@include('payments.partials.listing-promote', ['listing' => $listing])
	Renders nothing for other viewers or for listings that are not active.
	Needs public/css/payments.css (@include('payments.partials.head')).
--}}
@use('App\Enums\ListingStatus')
@use('App\Models\Product')
@use('App\Services\OrderService')
@use('App\Support\Stranice\Cenovnik')
@php
	$viewer = auth()->user();
	$active = $listing->status === ListingStatus::Published && ! $listing->trashed()
		&& ($listing->expires_at === null || $listing->expires_at->isFuture());
@endphp
@if ($viewer && (int) $viewer->id === (int) $listing->user_id && $active)
	@php
		$bumpAt = app(OrderService::class)->bumpAvailableAt($listing);
		$base = url_slash('moj-nalog/narudzbine/nova');
		$items = [];
		foreach ([Product::FEATURED_7 => 'Istakni 7 dana', Product::FEATURED_30 => 'Istakni 30 dana', Product::BUMP => 'Podigni oglas'] as $code => $label) {
			if ($product = Cenovnik::product($code)) {
				$items[] = ['code' => $code, 'label' => $label, 'price' => $product->price_rsd];
			}
		}
	@endphp
	@if ($items)
		<div class="lgpay-promote" role="group" aria-label="Isticanje i podizanje oglasa">
			@if ($listing->isFeatured())
				<p class="lgpay-promote__note">Istaknut do {{ $listing->featured_until->format('j. n. Y.') }}</p>
			@endif
			@foreach ($items as $item)
				@if ($item['code'] === Product::BUMP && $bumpAt)
					<span class="lg-btn lg-btn--outline lgpay-promote__btn" aria-disabled="true" title="Ponovo od {{ $bumpAt->format('j. n. \u H:i') }}">{{ $item['label'] }} <small>od {{ $bumpAt->format('j. n. \u H:i') }}</small></span>
				@else
					<a class="lg-btn {{ $item['code'] === Product::BUMP ? 'lg-btn--outline' : 'lg-btn--orange' }} lgpay-promote__btn" href="{{ $base }}?usluga={{ $item['code'] }}&amp;oglas={{ $listing->id }}">{{ $item['label'] }} <small>{{ Cenovnik::price($item['price']) }}</small></a>
				@endif
			@endforeach
		</div>
	@endif
@endif
