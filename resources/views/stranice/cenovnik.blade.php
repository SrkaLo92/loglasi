@extends('layouts.app')
@use('App\Support\Stranice\Cenovnik')
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\Navigacija')
@use('App\Support\Stranice\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@if ($paymentRows)
	@push('scripts')
	<script src="{{ asset_v('js/stranice.js') }}" defer></script>
	@endpush
@endif

@section('content')
@php($counter = Cenovnik::counter())
<div class="lg-strana">
	<header class="lg-strana__zaglavlje">
		<h1 class="lg-strana__naslov">{{ PageText::get('cenovnik.naslov') }}</h1>
	</header>

	<section class="lgx lgx-cenovnik" aria-labelledby="cenovnik-naslov">
		<div class="lgx-blok">
			<header class="lgx-zaglavlje">
				<h2 class="lgx-naslov" id="cenovnik-naslov">{{ PageText::get('cenovnik.podnaslov') }}</h2>
				<p class="lgx-uvod">{{ PageText::get('cenovnik.uvod') }}</p>
			</header>
			<ul class="lgx-cene">
				<li class="lgx-cena lgx-cena--besplatno">
					<h3 class="lgx-cena__naziv">{{ PageText::get('cenovnik.besplatno.naziv') }}</h3>
					<p class="lgx-cena__iznos"><span class="lgx-cena__broj">Besplatno</span></p>
					<p class="lgx-cena__opis">{{ Cenovnik::freeListings() > 0 ? 'Do '.Cenovnik::freeListings().' '.Cenovnik::plural(Cenovnik::freeListings(), 'novog oglasa', 'nova oglasa', 'novih oglasa').' mesečno za privatne korisnike.' : 'Za privatne korisnike.' }}</p>
					<ul class="lgx-cena__stavke">
						@foreach (PageText::list('cenovnik.besplatno.stavke') as $stavka)
							<li>{{ Ikonice::svg('check', 18) }} {{ PageText::replace((string) $stavka) }}</li>
						@endforeach
					</ul>
					<a class="lgx-btn lgx-btn--outline" href="{{ Navigacija::link('postavi') }}">Postavite oglas</a>
				</li>
				@foreach ($products as $product)
					@php($isBiz = $product->code === $biznisCode)
					<li class="lgx-cena{{ $isBiz ? ' lgx-cena--biznis' : '' }}" data-proizvod="{{ $product->code }}">
						@if ($isBiz)<span class="lgx-oznaka">Za firme</span>@endif
						<h3 class="lgx-cena__naziv">{{ $product->name }}</h3>
						<p class="lgx-cena__iznos"><span class="lgx-cena__broj">{{ Cenovnik::price($product->price_rsd) }}</span> <span class="lgx-cena__trajanje">{{ Cenovnik::durationLabel($product) }}</span></p>
						<p class="lgx-cena__opis">{{ Cenovnik::description($product) }}</p>
						@if ($isBiz)
							@if (Cenovnik::offerOpen() && $counter)
								<p class="lgx-cena__ponuda">{{ Cenovnik::offerShort() }}</p>
								@include('stranice.partials.brojac', ['counter' => $counter, 'mali' => true])
							@endif
							<a class="lgx-btn lgx-btn--green" href="{{ Navigacija::link('za_firme') }}">Saznajte više {{ Ikonice::svg('arrow', 18) }}</a>
						@else
							<a class="lgx-btn lgx-btn--outline" href="#uplata">Kako da platim</a>
						@endif
					</li>
				@endforeach
			</ul>
		</div>

		<div class="lgx-uplata" id="uplata">
			<div class="lgx-uplata__koraci">
				<h2 class="lgx-naslov">{{ PageText::get('cenovnik.uplata.naslov') }}</h2>
				<p class="lgx-uvod">{{ PageText::get('cenovnik.uplata.uvod') }}</p>
				@include('stranice.partials.koraci', ['koraci' => PageText::list('cenovnik.uplata.koraci'), 'mreza' => false])
			</div>
			<div class="lgx-uplata__kartica">
				<h3 class="lgx-uplata__naslov">{{ Ikonice::svg('bank', 22) }} Podaci za uplatu</h3>
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
		</div>

		@if ($faq)
			<div class="lgx-faq">
				<h2 class="lgx-naslov">Česta pitanja</h2>
				@foreach ($faq as $item)
					@include('stranice.partials.pitanje', ['pitanje' => $item['pitanje'], 'odgovor' => $item['odgovor']])
				@endforeach
			</div>
		@endif

		<div class="lgx-cta">
			<div class="lgx-cta__tekst">
				<h2 class="lgx-cta__naslov">{{ PageText::get('cenovnik.cta.naslov') }}</h2>
				<p>{{ PageText::get('cenovnik.cta.tekst') }}</p>
			</div>
			<div class="lgx-cta__dugmad">
				<a class="lgx-btn lgx-btn--orange" href="{{ Navigacija::link('postavi') }}">Postavite oglas</a>
				<a class="lgx-btn lgx-btn--white" href="{{ Navigacija::link('kontakt') }}">Pišite nam</a>
			</div>
		</div>
	</section>
</div>
@endsection
