@extends('layouts.app')
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@section('content')
@php
	$polja = ['ime', 'email', 'telefon', 'tema', 'poruka', 'saglasnost'];
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
				<p class="lgx-poruka lgx-poruka--greska" role="alert">Neka polja nisu dobro popunjena. Proverite označena polja i pošaljite ponovo.</p>
			@endif

			<form class="lgx-forma" action="{{ route('stranice.kontakt.posalji') }}" method="post" novalidate>
				@csrf
				<input type="hidden" name="_t" value="{{ $token }}">

				<div class="lgx-polje">
					<label for="k-ime">Ime i prezime</label>
					<input id="k-ime" type="text" name="ime" value="{{ old('ime') }}" autocomplete="name" maxlength="80" required aria-required="true" @if ($errors->has('ime')) aria-invalid="true" aria-describedby="greska-ime" @endif>
					@error('ime')<p class="lgx-polje__greska" id="greska-ime">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-polje">
					<label for="k-email">E-pošta</label>
					<input id="k-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" maxlength="190" required aria-required="true" @if ($errors->has('email')) aria-invalid="true" aria-describedby="greska-email" @endif>
					@error('email')<p class="lgx-polje__greska" id="greska-email">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-polje">
					<label for="k-telefon">Telefon <span class="lgx-polje__opciono">(nije obavezno)</span></label>
					<span class="lgx-tel">
						<span class="lgx-tel__prefiks" aria-hidden="true">+381</span>
						<input id="k-telefon" type="tel" name="telefon" value="{{ old('telefon') }}" autocomplete="tel-national" inputmode="tel" placeholder="65 123 4567" maxlength="30" aria-describedby="{{ trim('pomoc-telefon '.($opis('telefon') ?? '')) }}" @if ($errors->has('telefon')) aria-invalid="true" @endif>
					</span>
					<p class="lgx-polje__pomoc" id="pomoc-telefon">Može i sa nulom: 065 123 4567.</p>
					@error('telefon')<p class="lgx-polje__greska" id="greska-telefon">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-polje">
					<label for="k-tema">Tema</label>
					<select id="k-tema" name="tema" required aria-required="true" @if ($errors->has('tema')) aria-invalid="true" aria-describedby="greska-tema" @endif>
						@foreach ($teme as $tema)
							<option value="{{ $tema }}" @selected($izabranaTema === $tema)>{{ $tema }}</option>
						@endforeach
					</select>
					@error('tema')<p class="lgx-polje__greska" id="greska-tema">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-polje lgx-polje--puno">
					<label for="k-poruka">Poruka</label>
					<textarea id="k-poruka" name="poruka" rows="5" minlength="10" maxlength="2000" required aria-required="true" @if ($errors->has('poruka')) aria-invalid="true" aria-describedby="greska-poruka" @endif>{{ old('poruka') }}</textarea>
					@error('poruka')<p class="lgx-polje__greska" id="greska-poruka">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-zamka" aria-hidden="true">
					<label for="k-sajt">Ne popunjavajte ovo polje</label>
					<input id="k-sajt" type="text" name="sajt" value="" tabindex="-1" autocomplete="off">
				</div>

				<div class="lgx-polje lgx-polje--puno">
					<label class="lgx-saglasnost" for="k-saglasnost">
						<input id="k-saglasnost" type="checkbox" name="saglasnost" value="1" @checked(old('saglasnost')) required aria-required="true" @if ($errors->has('saglasnost')) aria-invalid="true" aria-describedby="greska-saglasnost" @endif>
						<span>Slažem se da se moji podaci koriste samo za odgovor na ovu poruku.</span>
					</label>
					@error('saglasnost')<p class="lgx-polje__greska" id="greska-saglasnost">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-polje lgx-polje--puno">
					<button class="lgx-btn lgx-btn--green lgx-forma__dugme" type="submit">Pošalji poruku</button>
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
