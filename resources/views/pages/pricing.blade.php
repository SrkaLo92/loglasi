{{-- Price list in sections: private users, promotions, firms (first 30 free, then packages), extras, payment. Prices from products. --}}
@extends('layouts.app')
@use('App\Services\ListingImages')
@use('App\Support\Pages\Pricing')
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')
@use('App\Support\Pages\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/pages.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/pricing.css') }}">
@endpush

@if ($paymentRows)
	@push('scripts')
	<script src="{{ asset_v('js/pages.js') }}" defer></script>
	@endpush
@endif

@php
	$counter = Pricing::counter();
	$slots = Pricing::listedOf(Pricing::SLOTS);
	$promotions = Pricing::listedOf(Pricing::PROMOTIONS);
	$packages = Pricing::packages();
	$popular = PageText::get('cenovnik.paketi.istaknut');
	$extras = array_values(array_filter(PageText::list('cenovnik.dodatno'), fn ($e) => is_array($e) && ! empty($e['naziv'])));
	$free = Pricing::freeListings();
	$maxImages = app(ListingImages::class)->maxImages();
	$check = fn () => SiteIcons::svg('check', 18, '2.5');
	$plus = Pricing::listedOf(Pricing::PLUS_SELLER);
	$plusMain = $plus[0] ?? null;
	$plusFeatures = $plusMain ? array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $plusMain->description) ?: []), 'strlen')) : [];
	$promoIcons = [
		'bump' => 'M12 19V5M5 12l7-7 7 7',
		'bump_10' => 'M12 19V5M5 12l7-7 7 7M5 21h14',
		'featured_7' => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
		'quick_sale_7' => 'M13 2L4 14h7l-1 8 9-12h-7z',
		'priority_3' => 'M13 2L3 14h9l-1 8 10-12h-9z',
		'top_7' => 'M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0z',
		'premium_xl_7' => 'M3 3h18v14H3zM3 21h8',
		'premium_xl_home_7' => 'M3 11l9-8 9 8M5 10v10h14V10',
	];
	$section = 0;
	// Payments: order page for products that are on sale (display-only rows have no link). Products under the
	// smallest order come from packs and credits. Until private payments are on, only firm packages and firm
	// accounts can order; private users see „Uskoro”.
	$canOrder = \App\Support\Payments\PaymentMode::canOrder(auth()->user());
	$minOrder = \App\Support\Payments\PaymentMode::minOrder();
	$sellable = fn ($p) => $p && $p->is_active && ! $p->display_only && \App\Services\Payments\ProductCatalog::isSellable($p) && $p->price_rsd >= $minOrder;
	$isPackage = fn ($p) => \App\Services\Payments\ProductCatalog::type($p) === \App\Services\Payments\ProductCatalog::FIRM_PACKAGE;
	$orderUrl = fn ($p) => $sellable($p) && ($canOrder || $isPackage($p)) ? url_slash('moj-nalog/narudzbine/nova').'?usluga='.$p->code : null;
	// One „Kupovina uskoro” per block instead of a pill on every row.
	$soon = fn ($products) => collect($products)->contains(fn ($p) => $sellable($p) && ! $canOrder && ! $isPackage($p));
	$soonNote = '<p class="lgc-soon-note">'.SiteIcons::svg('clock', 16).' Kupovina uskoro</p>';
	$orderCell = function ($p) use ($orderUrl, $minOrder) {
		if ($url = $orderUrl($p)) {
			return '<a class="lgc-order" href="'.e($url).'">Naruči<span class="lg-visually-hidden"> '.e($p->name).'</span></a>';
		}
		if ($p && $p->is_active && $p->price_rsd < $minOrder) {
			return '<span class="lgc-soon lgc-soon--with">uz paket</span>';
		}

		return '';
	};
@endphp

@section('content')
<div class="lg-page lgc">
	<header class="lgc-head">
		<h1 class="lg-page__title">{{ PageText::get('cenovnik.naslov') }}</h1>
		<p class="lgc-head__intro">{{ PageText::get('cenovnik.uvod') }}</p>
		@unless ($canOrder)
			<p class="lgc-head__note">{{ SiteIcons::svg('info', 18) }} Za sada plaćaju firme i preduzetnici. Za privatna lica plaćanje uključujemo uskoro, a do tada imate {{ Pricing::freeListingsText() }} besplatno.</p>
		@endunless
	</header>

	{{-- 1. Private users --}}
	<section class="lgc-sec" aria-labelledby="lgc-private">
		<div class="lgc-sec__head">
			<span class="lgc-sec__num">{{ sprintf('%02d', ++$section) }}</span>
			<h2 class="lgc-sec__heading" id="lgc-private">{{ PageText::get('cenovnik.privatni.naslov') }}</h2>
		</div>
		<div class="lgc-free" data-proizvod="free">
			<div class="lgc-free__price">
				<span class="lgc-free__name">{{ PageText::get('cenovnik.besplatno.naziv') }}</span>
				<span class="lgc-free__amount">Besplatno</span>
				@if ($free > 0)
					<span class="lgc-free__desc">{{ Pricing::freeListingsText() }}</span>
				@endif
			</div>
			<ul class="lgc-free__items">
				@foreach (PageText::list('cenovnik.besplatno.stavke') as $item)
					<li>{{ $check() }}{{ PageText::replace((string) $item) }}</li>
				@endforeach
				<li>{{ $check() }}Do {{ $maxImages }} {{ Pricing::plural($maxImages, 'slike', 'slike', 'slika') }} po oglasu</li>
			</ul>
			<a class="lg-btn lg-btn--orange lgc-free__button" href="{{ Navigation::link('post') }}">Postavite oglas</a>
		</div>

		@if ($slots)
			<div class="lgc-sub">
				<h3 class="lgc-sub__title">{{ PageText::get('cenovnik.mesta.naslov') }}</h3>
				<p class="lgc-sub__intro">{{ PageText::get('cenovnik.mesta.uvod') }}</p>
				@if ($soon($slots)) {!! $soonNote !!} @endif
			</div>
			<ul class="lgc-slots">
				@foreach ($slots as $product)
					<li class="lgc-slot" data-proizvod="{{ $product->code }}">
						<span class="lgc-slot__text">
							<span class="lgc-slot__name">{{ $product->name }}</span>
							<span class="lgc-slot__desc">{{ Pricing::description($product) }}</span>
						</span>
						<span class="lgc-slot__price">{{ Pricing::price($product->price_rsd) }}</span>
						{!! $orderCell($product) !!}
					</li>
				@endforeach
			</ul>
		@endif

		@if ($plusMain)
			<div class="lgc-plus" data-proizvod="{{ $plusMain->code }}">
				<div class="lgc-plus__text">
					<span class="lgc-plus__label">Novo</span>
					<h3 class="lgc-plus__name">{{ PageText::get('cenovnik.plus.naslov') }}</h3>
					<p class="lgc-plus__intro">{{ PageText::get('cenovnik.plus.uvod') }}</p>
					@if ($plusFeatures)
						<ul class="lgc-plus__items">
							@foreach ($plusFeatures as $feature)
								<li>{{ $check() }}{{ $feature }}</li>
							@endforeach
						</ul>
					@endif
					@if (($plusNote = PageText::get('cenovnik.plus.napomena')) !== '')
						<p class="lgc-plus__note">{{ SiteIcons::svg('info', 16) }} {{ $plusNote }}</p>
					@endif
				</div>
				<div class="lgc-plus__side">
					<ul class="lgc-plus__prices">
						@foreach ($plus as $product)
							<li class="lgc-plus__price">
								<span class="lgc-plus__period">{{ match (true) { $product->duration_days >= 365 => 'Godinu dana', default => $product->duration_days.' dana' } }}</span>
								<span class="lgc-plus__amount">{{ Pricing::price($product->price_rsd) }}</span>
								{!! $orderCell($product) !!}
							</li>
						@endforeach
					</ul>
					@if ($soon($plus)) {!! $soonNote !!} @endif
				</div>
			</div>
		@endif
	</section>

	{{-- 2. Promotions --}}
	@if ($promotions)
		<section class="lgc-sec" aria-labelledby="lgc-promocije">
			<div class="lgc-sec__head">
				<span class="lgc-sec__num">{{ sprintf('%02d', ++$section) }}</span>
				<h2 class="lgc-sec__heading" id="lgc-promocije">{{ PageText::get('cenovnik.promocije.naslov') }}</h2>
				<span class="lgc-sec__intro">{{ PageText::get('cenovnik.promocije.uvod') }}</span>
				@if ($soon($promotions)) {!! $soonNote !!} @endif
			</div>
			<div class="lgc-table">
				<table>
					<caption class="lg-visually-hidden">Promocije oglasa: usluga, šta dobijate, trajanje i cena</caption>
					<thead class="lg-visually-hidden">
						<tr><th scope="col">Usluga</th><th scope="col">Šta dobijate</th><th scope="col">Trajanje</th><th scope="col">Cena</th></tr>
					</thead>
					<tbody>
						@foreach ($promotions as $product)
							<tr class="lgc-promo" data-proizvod="{{ $product->code }}">
								<th scope="row" class="lgc-promo__service">
									<span class="lgc-promo__icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $promoIcons[$product->code] ?? $promoIcons['bump'] }}"/></svg></span>
									<span class="lgc-promo__name">{{ $product->name }}</span>
								</th>
								<td class="lgc-promo__desc">{{ Pricing::description($product) }}</td>
								<td class="lgc-promo__duration">{{ Pricing::promotionDuration($product) }}</td>
								<td class="lgc-promo__price">{{ Pricing::price($product->price_rsd) }} {!! $orderCell($product) !!}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
				<div class="lgc-table__bottom">
					<span>{{ PageText::get('cenovnik.promocije.napomena') }}</span>
					<a href="#uplata">Kako da platim</a>
				</div>
			</div>
		</section>
	@endif

	{{-- 3. Firms: first 30 free for a year, then packages --}}
	@if (Pricing::foundingTotal() > 0 || $packages)
		<section class="lgc-sec" aria-labelledby="lgc-firme">
			<div class="lgc-sec__head">
				<span class="lgc-sec__num">{{ sprintf('%02d', ++$section) }}</span>
				<h2 class="lgc-sec__heading" id="lgc-firme">{{ PageText::get('cenovnik.firme.naslov') }}</h2>
			</div>

			@if (Pricing::foundingTotal() > 0)
				<div class="lgc-founding">
					<div class="lgc-founding__text">
						<span class="lgc-founding__label">{{ PageText::get('cenovnik.firme.oznaka') }}</span>
						<h3 class="lgc-founding__title">{{ PageText::get('cenovnik.firme.ponuda') }}</h3>
						<ul class="lgc-founding__items">
							@foreach (PageText::list('cenovnik.firme.stavke') as $item)
								<li>{{ $check() }}{{ PageText::replace((string) $item) }}</li>
							@endforeach
						</ul>
						<p class="lgc-founding__note">{{ PageText::get('cenovnik.firme.napomena') }}</p>
					</div>
					<div class="lgc-founding__card">
						@if ($counter)
							<p class="lgc-founding__places">{{ $counter['text'] }}</p>
							<div class="lgc-founding__bar" role="progressbar" aria-label="Zauzeta besplatna mesta" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $counter['percent'] }}"><span style="width: {{ (int) $counter['percent'] }}%"></span></div>
							<p class="lgc-founding__taken">{{ $counter['note'] }}</p>
						@endif
						<a class="lg-btn lg-btn--green" href="{{ Navigation::link('for_firms') }}">Prijavite svoju firmu</a>
					</div>
				</div>
			@endif

			@if ($packages)
				<div class="lgc-sub">
					<h3 class="lgc-sub__title">{{ PageText::get('cenovnik.paketi.naslov') }}</h3>
					<p class="lgc-sub__intro">{{ PageText::get('cenovnik.paketi.uvod') }}</p>
				</div>
				<ul class="lgc-packages">
					@foreach ($packages as $package)
						<li class="lgc-package{{ $package['code'] === $popular ? ' lgc-package--featured' : '' }}" data-proizvod="{{ $package['monthly']->code }}">
							<div class="lgc-package__top">
								<h4 class="lgc-package__name">{{ $package['monthly']->name }}</h4>
								@if ($package['code'] === $popular)
									<span class="lgc-package__label">Najpopularniji</span>
								@endif
							</div>
							<p class="lgc-package__price"><span class="lgc-package__amount">{{ number_format($package['monthly']->price_rsd, 0, ',', '.') }}</span> <span class="lgc-package__period">RSD / mesec</span></p>
							@if ($package['yearly'])
								<p class="lgc-package__yearly">ili {{ Pricing::price($package['yearly']->price_rsd) }} godišnje</p>
							@endif
							@if ($orderUrl($package['monthly']) || $orderUrl($package['yearly']))
								<p class="lgc-package__order">
									@if ($url = $orderUrl($package['monthly']))<a class="lg-btn lg-btn--green" href="{{ $url }}">Mesečno<span class="lg-visually-hidden">, {{ $package['monthly']->name }}</span></a>@endif
									@if ($url = $orderUrl($package['yearly']))<a class="lg-btn lg-btn--outline" href="{{ $url }}">Godišnje<span class="lg-visually-hidden">, {{ $package['monthly']->name }}</span></a>@endif
								</p>
							@endif
							@if ($package['features'])
								<ul class="lgc-package__items">
									@foreach ($package['features'] as $feature)
										<li>{{ $check() }}{{ $feature }}</li>
									@endforeach
								</ul>
							@endif
						</li>
					@endforeach
				</ul>
			@endif
		</section>
	@endif

	{{-- Extra advertising for firms (on request) --}}
	@if ($extras)
		<section class="lgc-extra" aria-labelledby="lgc-extra">
			<div class="lgc-extra__head">
				<h2 class="lgc-sub__title" id="lgc-extra">{{ PageText::get('cenovnik.dodatno.naslov') }}</h2>
				<a href="{{ Navigation::link('contact') }}#forma">Zatražite ponudu</a>
			</div>
			<ul class="lgc-extra__list">
				@foreach ($extras as $extra)
					<li>
						<span class="lgc-extra__name">{{ $extra['naziv'] }}</span>
						@if (! empty($extra['opis']))<span class="lgc-extra__desc">{{ $extra['opis'] }}</span>@endif
						@if (! empty($extra['cena']))<span class="lgc-extra__price">{{ $extra['cena'] }}</span>@endif
					</li>
				@endforeach
			</ul>
		</section>
	@endif

	{{-- Payment --}}
	<section class="lgx lgc-payment" id="uplata" aria-labelledby="lgc-payment">
		<div class="lgc-payment__steps">
			<h2 class="lgc-sec__heading" id="lgc-payment">{{ PageText::get('cenovnik.uplata.naslov') }}</h2>
			<p class="lgc-payment__intro">{{ PageText::get('cenovnik.uplata.uvod') }}</p>
			<ol class="lgc-payment__list">
				@foreach (PageText::list('cenovnik.uplata.koraci') as $step)
					@if (is_array($step))
						<li><strong>{{ PageText::replace((string) ($step['naslov'] ?? '')) }}.</strong> {{ PageText::inline((string) ($step['tekst'] ?? '')) }}</li>
					@endif
				@endforeach
			</ol>
		</div>
		<div class="lgc-payment__card">
			<h3 class="lgc-payment__title">{{ SiteIcons::svg('bank', 22) }} Podaci za uplatu</h3>
			@if ($paymentRows)
				<dl class="lgx-payment__details">
					@foreach ($paymentRows as $key => $row)
						<div class="lgx-payment__row lgx-payment__row--{{ $key }}">
							<dt>{{ $row['label'] }}</dt>
							<dd>
								@if ($key === 'payment_account')
									<span class="lgx-copy">
										<span class="lgx-copy__value">{{ $row['value'] }}</span>
										<button type="button" class="lgx-copy__button" data-lgx-copy="{{ $row['value'] }}" aria-label="Kopiraj broj računa" hidden>{{ SiteIcons::svg('copy', 18) }}<span class="lgx-copy__text">Kopiraj</span></button>
									</span>
								@else
									{!! nl2br(e($row['value'])) !!}
								@endif
							</dd>
						</div>
					@endforeach
					<div class="lgx-payment__row"><dt>Iznos</dt><dd>cena izabrane usluge iz cenovnika</dd></div>
				</dl>
				<p class="lgx-copy__status" role="status" aria-live="polite"></p>
			@else
				<p class="lgx-payment__none">{{ PageText::inline((string) PageText::raw('cenovnik.uplata.nema')) }}</p>
			@endif
		</div>
	</section>

	@if ($faq)
		<section class="lgx lgx-faq lgc-faq" aria-labelledby="lgc-pitanja">
			<h2 class="lgc-sec__heading" id="lgc-pitanja">Česta pitanja</h2>
			@foreach ($faq as $item)
				@include('pages.partials.question', ['question' => $item['pitanje'], 'answer' => $item['odgovor']])
			@endforeach
		</section>
	@endif
</div>
@endsection
