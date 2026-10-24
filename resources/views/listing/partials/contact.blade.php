@use('App\Support\Listing\Icons')
@use('App\Support\Listing\Text')
@use('App\Support\Seo')
@php
	$published = $listing->published_at ?? $listing->created_at;
@endphp
<section class="lgo lgo-card lgo-contact lgo-o3" aria-label="Cena i kontakt">
	<h1 class="lgo-contact__title">{{ Seo::get()->getH1() ?? $listing->title }}</h1>
	<div class="lgo-contact__pricerow">
		<p class="lgo-contact__price">{{ $listing->price === null ? 'Cena po dogovoru' : $listing->priceLabel() }}</p>
		@if ($swap)
			<span class="lgo-badge lgo-badge--zamena">{{ Icons::listing('swap') }}Moguća zamena</span>
		@endif
	</div>
	<p class="lgo-contact__meta">
		@if ($listing->location)
			<span class="lgo-contact__m">{{ Icons::listing('pin') }}<span>{{ $listing->location->name }}</span></span>
		@endif
		@if ($published)
			<span class="lgo-contact__m">{{ Icons::listing('clock') }}<span><time datetime="{{ $published->copy()->utc()->toIso8601ZuluString() }}" title="{{ Text::exact($published) }}">{{ Text::relative($published) }}</time></span></span>
		@endif
	</p>
	@if ($active)
		@include('listing.partials.actions')
		@if (session('listing_error'))
			<p class="lgo-alert lgo-alert--error" role="alert">{{ session('listing_error') }}</p>
		@endif
		<p class="lgo-alert lgo-alert--error" role="alert" data-lgo-error hidden></p>
	@else
		<p class="lgo-contact__off">Kontakt nije dostupan jer oglas nije aktivan.</p>
	@endif
</section>
