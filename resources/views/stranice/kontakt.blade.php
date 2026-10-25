@extends('layouts.app')
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@section('content')
@php
	$polja = ['ime', 'kontakt', 'tema', 'poruka'];
	$greske = collect($polja)->filter(fn ($p) => $errors->has($p))->values();
	$opis = fn (string $polje) => $errors->has($polje) ? 'greska-'.$polje : null;
@endphp
<div class="lg-strana lgx">
	<header class="lg-strana__zaglavlje">
		<h1 class="lg-strana__naslov">{{ PageText::get('kontakt.naslov') }}</h1>
		<p class="lg-strana__uvod">{{ PageText::get('kontakt.uvod') }}</p>
	</header>

	<div class="lgx-kontakt-strana">
		<section class="lgx-forma-kartica" id="forma" aria-labelledby="forma-naslov">
			<h2 class="lgx-forma__naslov" id="forma-naslov">{{ PageText::get('kontakt.forma.naslov') }}</h2>

			@if ($poslato)
				<p class="lgx-poruka lgx-poruka--uspeh" id="poruka-poslata" role="status" tabindex="-1">{{ PageText::get('kontakt.poslato') }}</p>
			@endif

			@if ($errors->has('forma'))
				<p class="lgx-poruka lgx-poruka--greska" role="alert">{{ $errors->first('forma') }}</p>
			@elseif ($greske->isNotEmpty())
				<p class="lgx-poruka lgx-poruka--greska" role="alert">{{ PageText::get('kontakt.poruka.validacija') }}</p>
			@endif

			<form class="lgx-forma" aria-label="Kontakt forma" action="{{ route('stranice.kontakt.posalji') }}" method="post" novalidate>
				@csrf
				<input type="hidden" name="_t" value="{{ $token }}">

				<div class="lgx-polje">
					<label for="k-ime">{{ PageText::get('kontakt.polje.ime') }}</label>
					<input id="k-ime" type="text" name="ime" value="{{ old('ime') }}" autocomplete="name" maxlength="80" required aria-required="true" @if ($errors->has('ime')) aria-invalid="true" aria-describedby="greska-ime" @endif>
					@error('ime')<p class="lgx-polje__greska" id="greska-ime">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-polje">
					<label for="k-kontakt">{{ PageText::get('kontakt.polje.kontakt') }}</label>
					<input id="k-kontakt" type="text" name="kontakt" value="{{ old('kontakt') }}" autocomplete="email" maxlength="120" required aria-required="true" aria-describedby="{{ trim('pomoc-kontakt '.($opis('kontakt') ?? '')) }}" @if ($errors->has('kontakt')) aria-invalid="true" @endif>
					<p class="lgx-polje__pomoc" id="pomoc-kontakt">{{ PageText::get('kontakt.polje.kontakt_pomoc') }}</p>
					@error('kontakt')<p class="lgx-polje__greska" id="greska-kontakt">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-polje lgx-polje--puno">
					<label for="k-tema">{{ PageText::get('kontakt.polje.tema') }}</label>
					<select id="k-tema" data-pick name="tema" required aria-required="true" @if ($errors->has('tema')) aria-invalid="true" aria-describedby="greska-tema" @endif>
						<option value="">{{ PageText::get('kontakt.polje.tema_prazno') }}</option>
						@foreach ($teme as $tema)
							<option value="{{ $tema }}" @selected($izabranaTema === $tema)>{{ $tema }}</option>
						@endforeach
					</select>
					@error('tema')<p class="lgx-polje__greska" id="greska-tema">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-polje lgx-polje--puno">
					<label for="k-poruka">{{ PageText::get('kontakt.polje.poruka') }}</label>
					<textarea id="k-poruka" name="poruka" rows="5" minlength="10" maxlength="2000" required aria-required="true" @if ($errors->has('poruka')) aria-invalid="true" aria-describedby="greska-poruka" @endif>{{ old('poruka') }}</textarea>
					@error('poruka')<p class="lgx-polje__greska" id="greska-poruka">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-zamka" aria-hidden="true">
					<label for="k-sajt">Ne popunjavajte ovo polje</label>
					<input id="k-sajt" type="text" name="sajt" value="" tabindex="-1" autocomplete="off">
				</div>

				<div class="lgx-polje lgx-polje--puno">
					<p class="lgx-polje__pomoc">{{ PageText::inline(PageText::get('kontakt.napomena')) }}</p>
					<button class="lgx-btn lgx-btn--green lgx-forma__dugme" type="submit">{{ PageText::get('kontakt.dugme') }}</button>
				</div>
			</form>
		</section>

		@if ($email !== '' || $phone !== '' || $hours)
			<section class="lgx-kontakt" aria-labelledby="kontakt-podaci">
				<h2 class="lgx-kontakt__naslov" id="kontakt-podaci">Kontakt podaci</h2>
				<ul class="lgx-kontakt__lista">
					@if ($email !== '')
						<li class="lgx-kontakt__red">
							<span class="lgx-kontakt__ikonica">{{ Ikonice::svg('mail', 22) }}</span>
							<span><span class="lgx-kontakt__oznaka">E-pošta</span><a class="lgx-kontakt__vrednost" href="mailto:{{ $email }}">{{ $email }}</a></span>
						</li>
					@endif
					@if ($phone !== '')
						<li class="lgx-kontakt__red">
							<span class="lgx-kontakt__ikonica">{{ Ikonice::svg('phone', 22) }}</span>
							<span><span class="lgx-kontakt__oznaka">Telefon</span><a class="lgx-kontakt__vrednost" href="tel:{{ $phone }}">{{ $phoneText }}</a></span>
						</li>
					@endif
					@if ($hours)
						<li class="lgx-kontakt__red">
							<span class="lgx-kontakt__ikonica">{{ Ikonice::svg('clock', 22) }}</span>
							<span><span class="lgx-kontakt__oznaka">Radno vreme</span><span class="lgx-kontakt__vrednost lgx-kontakt__vrednost--obicno">@foreach ($hours as $red){{ $red }}@if (! $loop->last)<br>@endif @endforeach</span></span>
						</li>
					@endif
				</ul>
			</section>
		@endif
	</div>
</div>
@endsection
