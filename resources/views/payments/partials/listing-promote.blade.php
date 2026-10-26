{{--
	Promotion links for the owner of a listing ("Moji oglasi", the owner box on the listing page):
	@include('payments.partials.listing-promote', ['listing' => $listing])
	Renders nothing for other viewers or for listings that are not active. Each link opens the
	confirm page, which uses an included credit when there is one, otherwise creates an order.
	Needs public/css/payments.css (@include('payments.partials.head')).
--}}
@use('App\Services\OrderService')
@use('App\Services\Payments\ProductCatalog')
@use('App\Support\Pages\Pricing')
@php($viewer = auth()->user())
@if ($viewer && (int) $viewer->id === (int) $listing->user_id && $listing->isActive())
	@php
		$bumpAt = app(OrderService::class)->bumpAvailableAt($listing);
		$base = url_slash('moj-nalog/narudzbine/nova');
		$items = Pricing::products()->filter(fn ($p) => ! $p->display_only && ProductCatalog::needsListing($p))->values();
	@endphp
	@if ($items->isNotEmpty())
		<div class="lgpay-promote" role="group" aria-label="Promocija oglasa">
			@if ($listing->promotionRank() > 0 || $listing->isPremiumXl())
				<p class="lgpay-promote__note">Promovisan oglas</p>
			@endif
			@foreach ($items as $product)
				@if (ProductCatalog::type($product) === ProductCatalog::BUMP && $bumpAt)
					<span class="lg-btn lg-btn--outline lgpay-promote__btn" aria-disabled="true">{{ $product->name }} <small>od {{ $bumpAt->format('j. n. \u H:i') }}</small></span>
				@else
					<a class="lg-btn {{ ProductCatalog::type($product) === ProductCatalog::BUMP ? 'lg-btn--outline' : 'lg-btn--orange' }} lgpay-promote__btn" href="{{ $base }}?usluga={{ $product->code }}&amp;oglas={{ $listing->id }}">{{ $product->name }} <small>{{ Pricing::price($product->price_rsd) }}</small></a>
				@endif
			@endforeach
		</div>
	@endif
@endif
