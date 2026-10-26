{{--
    Listing card used on every list (home, search, similar listings, profiles).
    Props: listing (Listing with ListingSearch::cardRelations() loaded; with fieldValues
    loaded the card also shows up to three field values), highlight (orange frame of the
    featured section), eager (first visible images), layout ("grid" or "list": a row on phones),
    heading (h2-h4), xl (the wide Premium XL card: two columns on desktop, bigger image,
    "Premium" badge, description excerpt; pick the listings with PremiumXl::cards()),
    wide (a featured listing on a two column phone grid takes the whole row, KP style).
--}}
@props(['listing', 'highlight' => false, 'eager' => false, 'layout' => 'grid', 'heading' => 'h3', 'xl' => false, 'wide' => false])
@use('App\Support\Search\CategoryIcons')
@use('App\Support\Search\Favorites')
@use('App\Support\Search\ImageUrls')
@use('App\Support\Search\PremiumXl')
@use('App\Support\Search\TimeAgo')
@use('App\Services\ListingFields')
@once
	@push('head')
		<link rel="stylesheet" href="{{ asset_v('css/listing-card.css') }}">
	@endpush
@endonce
@php
	/** @var \App\Models\Listing $listing */
	$lcImage = $listing->coverImage;
	$lcTime = $listing->bumped_at ?? $listing->published_at ?? $listing->created_at;
	$lcFirm = $listing->user?->isFirm() ?? false;
	$lcXl = (bool) $xl;
	$lcFeatured = ! $lcXl && $listing->isFeatured();
	$lcExcerpt = $lcXl ? PremiumXl::excerpt($listing->description) : '';
	$lcTag = in_array($heading, ['h2', 'h3', 'h4'], true) ? $heading : 'h3';
	$lcSpecs = [];
	if ($listing->relationLoaded('fieldValues')) {
		foreach (app(ListingFields::class)->values($listing) as $value) {
			if ($value['display'] !== '' && $value['display'] !== 'Da') {
				$lcSpecs[] = $value['display'];
			}
			if (count($lcSpecs) === 3) {
				break;
			}
		}
	}
	$lcFav = auth()->check() ? app(Favorites::class)->has((int) $listing->id) : null;
@endphp
<article {{ $attributes->class(['lc', 'lc--featured' => $highlight && ! $lcXl, 'lc--xl' => $lcXl, 'lc--list' => $layout === 'list', 'lc--wide' => $wide && ! $lcXl]) }}>
	<a class="lc__link" href="{{ url_slash('/oglas/'.$listing->slug) }}">
		<div class="lc__media">
			@if ($lcImage)
				@php($lcFallback = ImageUrls::fallback($lcImage, 'medium'))
				<picture>
					@if ($lcFallback)
						@if ($lcXl)
							<source type="image/webp" srcset="{{ ImageUrls::url($lcImage, 'medium') }} 800w, {{ ImageUrls::url($lcImage, 'large') }} 1600w" sizes="(max-width: 767px) 100vw, 400px">
						@else
							{{-- 34vw on a phone (the card is half the screen; a wide card takes the row): a 3x phone still takes the 400 px file, which is sharp at that size, instead of the 800 px one. --}}
							<source type="image/webp" srcset="{{ ImageUrls::url($lcImage, 'thumb') }} 400w, {{ ImageUrls::url($lcImage, 'medium') }} 800w" sizes="(max-width: 767px) {{ $wide && ! $lcXl ? '100vw' : '34vw' }}, 300px">
						@endif
					@endif
					<img class="lc__img" src="{{ $lcFallback ?? ImageUrls::url($lcImage, 'medium') }}" alt="{{ $listing->title }}" width="400" height="300" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if ($eager) fetchpriority="high" @endif>
				</picture>
			@else
				<span class="lc__ph" aria-hidden="true">
					{{ CategoryIcons::forCategory($listing->category, 32) }}
					@if ($listing->category)<span class="lc__ph-label">{{ $listing->category->name }}</span>@endif
				</span>
			@endif
			@if ($lcXl || $lcFeatured || $lcFirm)
				<span class="lc__badges">
					@if ($lcXl)<span class="lc__badge lc__badge--premium">Premium</span>@endif
					@if ($lcFeatured)<span class="lc__badge lc__badge--featured">Istaknuto</span>@endif
					@if ($lcFirm)<span class="lc__badge lc__badge--firm">Firma</span>@endif
				</span>
			@endif
		</div>
		<div class="lc__body">
			<{{ $lcTag }} class="lc__title">{{ $listing->title }}</{{ $lcTag }}>
			@if ($lcSpecs)
				<p class="lc__specs">{{ implode(' · ', $lcSpecs) }}</p>
			@endif
			<p class="lc__price">{{ $listing->priceLabel() }}</p>
			@if ($lcExcerpt !== '')
				<p class="lc__excerpt">{{ $lcExcerpt }}</p>
			@endif
			<p class="lc__meta">
				@if ($listing->location)
					{{ CategoryIcons::svg('pin', 14) }}<span class="lg-visually-hidden">Mesto: </span><span class="lc__place">{{ $listing->location->name }}</span>
				@endif
				@if ($listing->location && $lcTime)<span class="lc__sep" aria-hidden="true">·</span>@endif
				@if ($lcTime)<time datetime="{{ $lcTime->toIso8601String() }}">{{ TimeAgo::format($lcTime) }}</time>@endif
			</p>
			@if ($lcXl)
				<span class="lc__cta" aria-hidden="true">Kontaktirajte prodavca</span>
			@endif
		</div>
	</a>
	@if ($lcFav !== null)
		<form class="lc__fav" method="post" action="{{ Favorites::url((int) $listing->id) }}" data-lg-fav>
			@csrf
			<button class="lc__fav-btn" type="submit" aria-pressed="{{ $lcFav ? 'true' : 'false' }}" aria-label="{{ $lcFav ? 'Ukloni iz omiljenih' : 'Sačuvaj u omiljene' }}: {{ $listing->title }}">{{ CategoryIcons::svg('heart', 20, '2') }}</button>
		</form>
	@else
		{{-- A guest sees the heart too (as on kupujemprodajem); it leads to login. --}}
		<div class="lc__fav"><a class="lc__fav-btn" href="{{ \App\Support\Auth\LoginPage::urlBack(request()) }}" rel="nofollow" aria-label="Prijavite se da biste sačuvali oglas: {{ $listing->title }}">{{ CategoryIcons::svg('heart', 20, '2') }}</a></div>
	@endif
</article>
