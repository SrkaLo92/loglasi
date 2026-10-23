@extends('layouts.app')
@use('App\Support\Stranice\Cenovnik')
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\Navigacija')
@use('App\Support\Stranice\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@section('content')
@php
	$prijavaUrl = Navigacija::link('kontakt').'?tema=firma#forma';
@endphp
<div class="lg-strana">
	<section class="lgx lgx-za-firme" aria-labelledby="firme-naslov">
		<div class="lgx-hero">
			<div class="lgx-hero__tekst">
				<span class="lgx-oznaka">{{ PageText::get('za_firme.oznaka') }}</span>
				<h1 class="lgx-hero__naslov" id="firme-naslov">{{ PageText::get('za_firme.naslov') }}</h1>
				<p class="lgx-hero__uvod">{{ PageText::get('za_firme.uvod') }}</p>
				@if ($counter)
					@include('stranice.partials.brojac', ['counter' => $counter])
				@endif
				@if ($offer !== '')
					<p class="lgx-hero__ponuda">{{ $offer }}</p>
				@endif
				<div class="lgx-hero__dugmad">
					<a class="lgx-btn lgx-btn--orange" href="{{ $prijavaUrl }}">Prijavite firmu {{ Ikonice::svg('arrow', 18) }}</a>
					<a class="lgx-btn lgx-btn--outline" href="{{ Navigacija::link('cenovnik') }}">Pogledajte cenovnik</a>
				</div>
			</div>
			{{-- Ilustracija profila firme (dekorativna). --}}
			<div class="lgx-mock" aria-hidden="true">
				<div class="lgx-mock__naslovna"></div>
				<div class="lgx-mock__telo">
					<span class="lgx-mock__logo">VF</span>
					<p class="lgx-mock__ime">Vaša firma</p>
					<p class="lgx-mock__provereno">{{ Ikonice::svg('badge', 16) }} Proverena firma</p>
					<p class="lgx-mock__info">{{ Ikonice::svg('clock', 16) }} Pon–Sub 08–20h · Loznica</p>
					<div class="lgx-mock__dugmad">
						<span class="lgx-mock__dugme lgx-mock__dugme--poziv">{{ Ikonice::svg('phone', 16) }} Pozovi</span>
						<span class="lgx-mock__dugme">{{ Ikonice::svg('whatsapp', 16) }} WhatsApp</span>
						<span class="lgx-mock__dugme">{{ Ikonice::svg('viber', 16) }} Viber</span>
					</div>
					<div class="lgx-mock__stat"><span><b>24</b> oglasa</span><span><b>1.240</b> pregleda</span><span><b>86</b> poziva</span></div>
				</div>
			</div>
		</div>

		<div class="lgx-blok">
			<h2 class="lgx-naslov">{{ PageText::get('za_firme.prednosti.naslov') }}</h2>
			<ul class="lgx-prednosti">
				@foreach (PageText::list('za_firme.prednosti') as $prednost)
					@continue(! is_array($prednost) || empty($prednost['naslov']))
					<li class="lgx-prednost">
						<span class="lgx-prednost__ikonica">{{ Ikonice::svg((string) ($prednost['ikonica'] ?? 'check'), 26) }}</span>
						<h3 class="lgx-prednost__naslov">{{ $prednost['naslov'] }}</h3>
						<p class="lgx-prednost__tekst">{{ $prednost['tekst'] ?? '' }}</p>
					</li>
				@endforeach
			</ul>
		</div>

		<div class="lgx-blok">
			<h2 class="lgx-naslov">{{ PageText::get('za_firme.poredjenje.naslov') }}</h2>
			@php
				$redovi = [
					['Besplatni novi oglasi', $freeListings > 0 ? $freeListings.' mesečno' : 'da', 'prema paketu'],
					['Oznaka „Firma” na oglasima', false, true],
					['Logo i naslovna slika', false, true],
					['Oznaka „Proverena firma”', false, true],
					['Radno vreme i mapa', false, true],
					['Statistika pregleda i kontakata', false, true],
				];
			@endphp
			<div class="lgx-tabela-omot">
				<table class="lgx-tabela">
					<thead>
						<tr>
							<th scope="col"><span class="lg-visually-hidden">Pogodnost</span></th>
							<th scope="col">Privatni nalog</th>
							<th scope="col" class="lgx-tabela__biznis">Biznis paket</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($redovi as $red)
							<tr>
								<th scope="row">{{ $red[0] }}</th>
								@foreach ([$red[1], $red[2]] as $celija)
									@if ($celija === true)
										<td class="lgx-tabela__da">{{ Ikonice::svg('check', 20) }}<span class="lg-visually-hidden">Da</span></td>
									@elseif ($celija === false)
										<td class="lgx-tabela__ne">{{ Ikonice::svg('minus', 20) }}<span class="lg-visually-hidden">Ne</span></td>
									@else
										<td>{{ $celija }}</td>
									@endif
								@endforeach
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

		<div class="lgx-blok">
			<h2 class="lgx-naslov">{{ PageText::get('za_firme.koraci.naslov') }}</h2>
			@include('stranice.partials.koraci', ['koraci' => PageText::list('za_firme.koraci'), 'mreza' => true])
		</div>

		<div class="lgx-cta">
			<div class="lgx-cta__tekst">
				<h2 class="lgx-cta__naslov">{{ $offerOpen ? PageText::get('za_firme.cta.naslov') : PageText::get('za_firme.cta.naslov_popunjeno') }}</h2>
				<p>{{ PageText::get('za_firme.cta.tekst') }}</p>
			</div>
			<div class="lgx-cta__dugmad">
				<a class="lgx-btn lgx-btn--orange" href="{{ $prijavaUrl }}">Pošaljite prijavu {{ Ikonice::svg('arrow', 18) }}</a>
				@if ($phone !== '')
					<p class="lgx-cta__telefon">ili pozovite <a href="tel:{{ $phone }}">{{ $phoneText }}</a></p>
				@endif
			</div>
		</div>
	</section>
</div>
@endsection
