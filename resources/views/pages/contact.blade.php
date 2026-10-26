@extends('layouts.app')
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/pages.css') }}">
@endpush

@section('content')
@php
	$fields = ['ime', 'kontakt', 'tema', 'poruka'];
	$fieldErrors = collect($fields)->filter(fn ($p) => $errors->has($p))->values();
	$describedBy = fn (string $field) => $errors->has($field) ? 'greska-'.$field : null;
@endphp
<div class="lg-page lgx">
	<header class="lg-page__header">
		<h1 class="lg-page__title">{{ PageText::get('kontakt.naslov') }}</h1>
		<p class="lg-page__intro">{{ PageText::get('kontakt.uvod') }}</p>
	</header>

	<div class="lgx-contact-page">
		<section class="lgx-form-card" id="forma" aria-labelledby="forma-naslov">
			<h2 class="lgx-form__title" id="forma-naslov">{{ PageText::get('kontakt.forma.naslov') }}</h2>

			@if ($sent)
				<p class="lgx-message lgx-message--success" id="poruka-poslata" role="status" tabindex="-1">{{ PageText::get('kontakt.poslato') }}</p>
			@endif

			@if ($errors->has('forma'))
				<p class="lgx-message lgx-message--error" role="alert">{{ $errors->first('forma') }}</p>
			@elseif ($fieldErrors->isNotEmpty())
				<p class="lgx-message lgx-message--error" role="alert">{{ PageText::get('kontakt.poruka.validacija') }}</p>
			@endif

			<form class="lgx-form" aria-label="Kontakt forma" action="{{ route('pages.contact.send') }}" method="post" novalidate>
				@csrf
				<input type="hidden" name="_t" value="{{ $token }}">

				<div class="lgx-field">
					<label for="k-ime">{{ PageText::get('kontakt.polje.ime') }}</label>
					<input id="k-ime" type="text" name="ime" value="{{ old('ime') }}" autocomplete="name" maxlength="80" required aria-required="true" @if ($errors->has('ime')) aria-invalid="true" aria-describedby="greska-ime" @endif>
					@error('ime')<p class="lgx-field__error" id="greska-ime">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-field">
					<label for="k-kontakt">{{ PageText::get('kontakt.polje.kontakt') }}</label>
					<input id="k-kontakt" type="text" name="kontakt" value="{{ old('kontakt') }}" autocomplete="email" maxlength="120" required aria-required="true" aria-describedby="{{ trim('pomoc-kontakt '.($describedBy('kontakt') ?? '')) }}" @if ($errors->has('kontakt')) aria-invalid="true" @endif>
					<p class="lgx-field__help" id="pomoc-kontakt">{{ PageText::get('kontakt.polje.kontakt_pomoc') }}</p>
					@error('kontakt')<p class="lgx-field__error" id="greska-kontakt">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-field lgx-field--full">
					<label for="k-tema">{{ PageText::get('kontakt.polje.tema') }}</label>
					<select id="k-tema" data-pick name="tema" required aria-required="true" @if ($errors->has('tema')) aria-invalid="true" aria-describedby="greska-tema" @endif>
						<option value="">{{ PageText::get('kontakt.polje.tema_prazno') }}</option>
						@foreach ($topics as $topic)
							<option value="{{ $topic }}" @selected($selectedTopic === $topic)>{{ $topic }}</option>
						@endforeach
					</select>
					@error('tema')<p class="lgx-field__error" id="greska-tema">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-field lgx-field--full">
					<label for="k-poruka">{{ PageText::get('kontakt.polje.poruka') }}</label>
					<textarea id="k-poruka" name="poruka" rows="5" minlength="10" maxlength="2000" required aria-required="true" @if ($errors->has('poruka')) aria-invalid="true" aria-describedby="greska-poruka" @endif>{{ old('poruka') }}</textarea>
					@error('poruka')<p class="lgx-field__error" id="greska-poruka">{{ $message }}</p>@enderror
				</div>

				<div class="lgx-trap" aria-hidden="true">
					<label for="k-sajt">Ne popunjavajte ovo polje</label>
					<input id="k-sajt" type="text" name="sajt" value="" tabindex="-1" autocomplete="off">
				</div>

				<div class="lgx-field lgx-field--full">
					<p class="lgx-field__help">{{ PageText::inline(PageText::get('kontakt.napomena')) }}</p>
					<button class="lgx-btn lgx-btn--green lgx-form__button" type="submit">{{ PageText::get('kontakt.dugme') }}</button>
				</div>
			</form>
		</section>

		@if ($email !== '' || $phone !== '' || $hours)
			<section class="lgx-contact" aria-labelledby="kontakt-podaci">
				<h2 class="lgx-contact__title" id="kontakt-podaci">Kontakt podaci</h2>
				<ul class="lgx-contact__list">
					@if ($email !== '')
						<li class="lgx-contact__row">
							<span class="lgx-contact__icon">{{ SiteIcons::svg('mail', 22) }}</span>
							<span><span class="lgx-contact__label">E-pošta</span><a class="lgx-contact__value" href="mailto:{{ $email }}">{{ $email }}</a></span>
						</li>
					@endif
					@if ($phone !== '')
						<li class="lgx-contact__row">
							<span class="lgx-contact__icon">{{ SiteIcons::svg('phone', 22) }}</span>
							<span><span class="lgx-contact__label">Telefon</span><a class="lgx-contact__value" href="tel:{{ $phone }}">{{ $phoneText }}</a></span>
						</li>
					@endif
					@if ($hours)
						<li class="lgx-contact__row">
							<span class="lgx-contact__icon">{{ SiteIcons::svg('clock', 22) }}</span>
							<span><span class="lgx-contact__label">Radno vreme</span><span class="lgx-contact__value lgx-contact__value--plain">@foreach ($hours as $row){{ $row }}@if (! $loop->last)<br>@endif @endforeach</span></span>
						</li>
					@endif
				</ul>
			</section>
		@endif
	</div>
</div>
@endsection
