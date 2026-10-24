{{-- Price list in sections: private users, promotions, firms (first 30 free, then packages), extras, payment. Prices from products. --}}
@extends('layouts.app')
@use('App\Services\ListingImages')
@use('App\Support\Stranice\Cenovnik')
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\Navigacija')
@use('App\Support\Stranice\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/cenovnik.css') }}">
@endpush

@if ($paymentRows)
	@push('scripts')
	<script src="{{ asset_v('js/stranice.js') }}" defer></script>
	@endpush
@endif

@php
	$counter = Cenovnik::counter();
	$slots = Cenovnik::listedOf(Cenovnik::SLOTS);
	$promotions = Cenovnik::listedOf(Cenovnik::PROMOTIONS);
	$packages = Cenovnik::packages();
	$popular = PageText::get('cenovnik.paketi.istaknut');
	$extras = array_values(array_filter(PageText::list('cenovnik.dodatno'), fn ($e) => is_array($e) && ! empty($e['naziv'])));
	$free = Cenovnik::freeListings();
	$maxImages = app(ListingImages::class)->maxImages();
	$check = fn () => Ikonice::svg('check', 18, '2.5');
	$promoIcons = [
		'bump' => 'M12 19V5M5 12l7-7 7 7',
		'bump_10' => 'M12 19V5M5 12l7-7 7 7M5 21h14',
		'featured_7' => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
		'priority_3' => 'M13 2L3 14h9l-1 8 10-12h-9z',
		'top_7' => 'M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0z',
		'premium_xl_7' => 'M3 3h18v14H3zM3 21h8',
		'premium_xl_home_7' => 'M3 11l9-8 9 8M5 10v10h14V10',
	];
	$section = 0;
@endphp

@section('content')
<div class="lg-strana lgc">
	<header class="lgc-head">
		<h1 class="lg-strana__naslov">{{ PageText::get('cenovnik.naslov') }}</h1>
		<p class="lgc-head__uvod">{{ PageText::get('cenovnik.uvod') }}</p>
	</header>

	{{-- 1. Private users --}}
	<section class="lgc-sec" aria-labelledby="lgc-privatni">
		<div class="lgc-sec__head">
			<span class="lgc-sec__broj">{{ sprintf('%02d', ++$section) }}</span>
			<h2 class="lgc-sec__naslov" id="lgc-privatni">{{ PageText::get('cenovnik.privatni.naslov') }}</h2>
		</div>
		<div class="lgc-free" data-proizvod="free">
			<div class="lgc-free__cena">
				<span class="lgc-free__naziv">{{ PageText::get('cenovnik.besplatno.naziv') }}</span>
				<span class="lgc-free__iznos">Besplatno</span>
				@if ($free > 0)
					<span class="lgc-free__opis">{{ Cenovnik::freeListingsText() }}</span>
				@endif
			</div>
			<ul class="lgc-free__stavke">
				@foreach (PageText::list('cenovnik.besplatno.stavke') as $item)
					<li>{{ $check() }}{{ PageText::replace((string) $item) }}</li>
				@endforeach
				<li>{{ $check() }}Do {{ $maxImages }} {{ Cenovnik::plural($maxImages, 'slike', 'slike', 'slika') }} po oglasu</li>
			</ul>
			<a class="lg-btn lg-btn--orange lgc-free__dugme" href="{{ Navigacija::link('postavi') }}">Postavite oglas</a>
		</div>

		@if ($slots)
			<div class="lgc-sub">
				<h3 class="lgc-sub__naslov">{{ PageText::get('cenovnik.mesta.naslov') }}</h3>
				<p class="lgc-sub__uvod">{{ PageText::get('cenovnik.mesta.uvod') }}</p>
			</div>
			<ul class="lgc-slots">
				@foreach ($slots as $product)
					<li class="lgc-slot" data-proizvod="{{ $product->code }}">
						<span class="lgc-slot__tekst">
							<span class="lgc-slot__naziv">{{ $product->name }}</span>
							<span class="lgc-slot__opis">{{ Cenovnik::description($product) }}</span>
						</span>
						<span class="lgc-slot__cena">{{ Cenovnik::price($product->price_rsd) }}</span>
					</li>
				@endforeach
			</ul>
		@endif
	</section>

	{{-- 2. Promotions --}}
	@if ($promotions)
		<section class="lgc-sec" aria-labelledby="lgc-promocije">
			<div class="lgc-sec__head">
				<span class="lgc-sec__broj">{{ sprintf('%02d', ++$section) }}</span>
				<h2 class="lgc-sec__naslov" id="lgc-promocije">{{ PageText::get('cenovnik.promocije.naslov') }}</h2>
				<span class="lgc-sec__uvod">{{ PageText::get('cenovnik.promocije.uvod') }}</span>
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
								<th scope="row" class="lgc-promo__usluga">
									<span class="lgc-promo__ikona"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $promoIcons[$product->code] ?? $promoIcons['bump'] }}"/></svg></span>
									<span class="lgc-promo__naziv">{{ $product->name }}</span>
								</th>
								<td class="lgc-promo__opis">{{ Cenovnik::description($product) }}</td>
								<td class="lgc-promo__trajanje">{{ Cenovnik::promotionDuration($product) }}</td>
								<td class="lgc-promo__cena">{{ Cenovnik::price($product->price_rsd) }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
				<div class="lgc-table__dno">
					<span>{{ PageText::get('cenovnik.promocije.napomena') }}</span>
					<a href="#uplata">Kako da platim</a>
				</div>
			</div>
		</section>
	@endif

	{{-- 3. Firms: first 30 free for a year, then packages --}}
	@if (Cenovnik::foundingTotal() > 0 || $packages)
		<section class="lgc-sec" aria-labelledby="lgc-firme">
			<div class="lgc-sec__head">
				<span class="lgc-sec__broj">{{ sprintf('%02d', ++$section) }}</span>
				<h2 class="lgc-sec__naslov" id="lgc-firme">{{ PageText::get('cenovnik.firme.naslov') }}</h2>
			</div>

			@if (Cenovnik::foundingTotal() > 0)
				<div class="lgc-prvih">
					<div class="lgc-prvih__tekst">
						<span class="lgc-prvih__oznaka">{{ PageText::get('cenovnik.firme.oznaka') }}</span>
						<h3 class="lgc-prvih__naslov">{{ PageText::get('cenovnik.firme.ponuda') }}</h3>
						<ul class="lgc-prvih__stavke">
							@foreach (PageText::list('cenovnik.firme.stavke') as $item)
								<li>{{ $check() }}{{ PageText::replace((string) $item) }}</li>
							@endforeach
						</ul>
						<p class="lgc-prvih__napomena">{{ PageText::get('cenovnik.firme.napomena') }}</p>
					</div>
					<div class="lgc-prvih__kartica">
						@if ($counter)
							<p class="lgc-prvih__mesta">{{ $counter['text'] }}</p>
							<div class="lgc-prvih__traka" role="progressbar" aria-label="Zauzeta besplatna mesta" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $counter['percent'] }}"><span style="width: {{ (int) $counter['percent'] }}%"></span></div>
							<p class="lgc-prvih__zauzeto">{{ $counter['note'] }}</p>
						@endif
						<a class="lg-btn lg-btn--green" href="{{ Navigacija::link('za_firme') }}">Prijavite svoju firmu</a>
					</div>
				</div>
			@endif

			@if ($packages)
				<div class="lgc-sub">
					<h3 class="lgc-sub__naslov">{{ PageText::get('cenovnik.paketi.naslov') }}</h3>
					<p class="lgc-sub__uvod">{{ PageText::get('cenovnik.paketi.uvod') }}</p>
				</div>
				<ul class="lgc-paketi">
					@foreach ($packages as $package)
						<li class="lgc-paket{{ $package['code'] === $popular ? ' lgc-paket--istaknut' : '' }}" data-proizvod="{{ $package['monthly']->code }}">
							<div class="lgc-paket__vrh">
								<h4 class="lgc-paket__naziv">{{ $package['monthly']->name }}</h4>
								@if ($package['code'] === $popular)
									<span class="lgc-paket__oznaka">Najčešći izbor</span>
								@endif
							</div>
							<p class="lgc-paket__cena"><span class="lgc-paket__iznos">{{ number_format($package['monthly']->price_rsd, 0, ',', '.') }}</span> <span class="lgc-paket__period">RSD / mesec</span></p>
							@if ($package['yearly'])
								<p class="lgc-paket__godisnje">ili {{ Cenovnik::price($package['yearly']->price_rsd) }} godišnje</p>
							@endif
							@if ($package['features'])
								<ul class="lgc-paket__stavke">
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
		<section class="lgc-dodatno" aria-labelledby="lgc-dodatno">
			<div class="lgc-dodatno__head">
				<h2 class="lgc-sub__naslov" id="lgc-dodatno">{{ PageText::get('cenovnik.dodatno.naslov') }}</h2>
				<a href="{{ Navigacija::link('kontakt') }}#forma">Zatražite ponudu</a>
			</div>
			<ul class="lgc-dodatno__lista">
				@foreach ($extras as $extra)
					<li>
						<span class="lgc-dodatno__naziv">{{ $extra['naziv'] }}</span>
						@if (! empty($extra['opis']))<span class="lgc-dodatno__opis">{{ $extra['opis'] }}</span>@endif
						@if (! empty($extra['cena']))<span class="lgc-dodatno__cena">{{ $extra['cena'] }}</span>@endif
					</li>
				@endforeach
			</ul>
		</section>
	@endif

	{{-- Payment --}}
	<section class="lgx lgc-uplata" id="uplata" aria-labelledby="lgc-uplata">
		<div class="lgc-uplata__koraci">
			<h2 class="lgc-sec__naslov" id="lgc-uplata">{{ PageText::get('cenovnik.uplata.naslov') }}</h2>
			<p class="lgc-uplata__uvod">{{ PageText::get('cenovnik.uplata.uvod') }}</p>
			<ol class="lgc-uplata__lista">
				@foreach (PageText::list('cenovnik.uplata.koraci') as $step)
					@if (is_array($step))
						<li><strong>{{ PageText::replace((string) ($step['naslov'] ?? '')) }}.</strong> {{ PageText::inline((string) ($step['tekst'] ?? '')) }}</li>
					@endif
				@endforeach
			</ol>
		</div>
		<div class="lgc-uplata__kartica">
			<h3 class="lgc-uplata__naslov">{{ Ikonice::svg('bank', 22) }} Podaci za uplatu</h3>
			@if ($paymentRows)
				<dl class="lgx-uplata__podaci">
					@foreach ($paymentRows as $key => $row)
						<div class="lgx-uplata__red lgx-uplata__red--{{ $key }}">
							<dt>{{ $row['label'] }}</dt>
							<dd>
								@if ($key === 'payment_account')
									<span class="lgx-kopiraj">
										<span class="lgx-kopiraj__vrednost">{{ $row['value'] }}</span>
										<button type="button" class="lgx-kopiraj__dugme" data-lgx-kopiraj="{{ $row['value'] }}" aria-label="Kopiraj broj računa" hidden>{{ Ikonice::svg('copy', 18) }}<span class="lgx-kopiraj__tekst">Kopiraj</span></button>
									</span>
								@else
									{!! nl2br(e($row['value'])) !!}
								@endif
							</dd>
						</div>
					@endforeach
					<div class="lgx-uplata__red"><dt>Iznos</dt><dd>cena izabrane usluge iz cenovnika</dd></div>
				</dl>
				<p class="lgx-kopiraj__status" role="status" aria-live="polite"></p>
			@else
				<p class="lgx-uplata__nema">{{ PageText::inline((string) PageText::raw('cenovnik.uplata.nema')) }}</p>
			@endif
		</div>
	</section>

	@if ($faq)
		<section class="lgx lgx-faq lgc-faq" aria-labelledby="lgc-pitanja">
			<h2 class="lgc-sec__naslov" id="lgc-pitanja">Česta pitanja</h2>
			@foreach ($faq as $item)
				@include('stranice.partials.pitanje', ['pitanje' => $item['pitanje'], 'odgovor' => $item['odgovor']])
			@endforeach
		</section>
	@endif
</div>
@endsection
