@php
	use App\Models\InfoBusinessRequest;
	use App\Support\Info\InfoUrl;
	/** @var \App\Models\InfoBusiness $business */
	$schema = array_filter([
		'@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $business->name,
		'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => $business->address, 'addressLocality' => $business->place ?: 'Loznica', 'addressCountry' => 'RS']),
		'telephone' => $business->phones()[0] ?? null, 'url' => $business->website ?: $business->url(),
	]);
@endphp
@extends('info.layout', ['section' => 'firme', 'title' => $business->name.($business->place ? ', '.$business->place : ''), 'description' => trim(($business->category ? $business->category.' · ' : '').($business->address ?: 'Loznica').'. Telefon, adresa i ponuda na loglasi Info.'), 'canonical' => $business->url(), 'jsonld' => [$schema]])
@section('content')
<div class="inf-wrap inf-article-wrap">
	<article class="inf-article">
		<nav class="inf-crumbs inf-meta" aria-label="Putanja"><a href="{{ InfoUrl::to('firme') }}">Firme</a>@if ($business->category) › <a href="{{ InfoUrl::to('firme') }}?kategorija={{ rawurlencode($business->category) }}">{{ $business->category }}</a>@endif</nav>
		<h1>{{ $business->name }}</h1>
		@if ($business->category)<p class="inf-label">{{ $business->category }}</p>@endif
		@if ($business->description)<p class="inf-lead">{{ $business->description }}</p>@endif

		<dl class="inf-facts">
			@if ($business->address)<div><dt>Adresa</dt><dd>{{ $business->address }}</dd></div>@endif
			@foreach ($business->phones() as $phone)
				<div><dt>Telefon</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></dd></div>
			@endforeach
			@if ($business->website)<div><dt>Sajt</dt><dd><a href="{{ $business->website }}" rel="noopener nofollow" target="_blank">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($business->website, '/')) }}</a></dd></div>@endif
			@if ($business->facebook)<div><dt>Facebook</dt><dd><a href="{{ $business->facebook }}" rel="noopener nofollow" target="_blank">Facebook strana</a></dd></div>@endif
			@if ($business->instagram)<div><dt>Instagram</dt><dd><a href="{{ $business->instagram }}" rel="noopener nofollow" target="_blank">Instagram profil</a></dd></div>@endif
		</dl>

		<div class="inf-actions">
			@if ($business->phones())<a class="inf-btn" href="tel:{{ preg_replace('/[^0-9+]/', '', $business->phones()[0]) }}">Pozovi</a>@endif
			@if ($business->address)<a class="inf-btn inf-btn--soft" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($business->name.' '.$business->address) }}" rel="noopener" target="_blank">Mapa</a>@endif
			@if ($business->profileUrl())<a class="inf-btn inf-btn--warm" href="{{ $business->profileUrl() }}">Oglasi ove firme na loglasi.rs</a>@endif
		</div>

		<section id="zahtev" class="inf-card inf-stack-sm">
			<h2 class="inf-card__title">Ovo je vaša firma?</h2>
			<p class="inf-meta">Preuzmite profil besplatno, ispravite podatke ili zatražite uklanjanje. Javljamo se u roku od jednog radnog dana.</p>
			<form class="inf-form" method="post" action="{{ InfoUrl::to('firme/'.$business->slug.'/zahtev') }}">
				@csrf
				<div class="inf-hp" aria-hidden="true"><label>Sajt <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
				<fieldset class="inf-seg">
					<legend class="inf-visually-hidden">Šta želite</legend>
					@foreach (InfoBusinessRequest::KINDS as $kind => $label)
						<label><input type="radio" name="kind" value="{{ $kind }}" @checked(old('kind', 'claim') === $kind)> {{ $label }}</label>
					@endforeach
				</fieldset>
				<label>Ime i prezime<input type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>
				<label>Mejl<input type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email"></label>
				<label>Telefon (nije obavezno)<input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel"></label>
				<label>Poruka (nije obavezno)<textarea name="message" rows="3" maxlength="2000">{{ old('message') }}</textarea></label>
				@if ($errors->any())<p class="inf-error" role="alert">{{ $errors->first() }}</p>@endif
				<button class="inf-btn" type="submit">Pošalji zahtev</button>
			</form>
		</section>
		<p class="inf-meta">Podaci su preuzeti iz javno dostupnih poslovnih izvora. Ako nešto nije tačno, javite nam preko forme iznad.</p>
	</article>
	<aside class="inf-stack">
		<div class="inf-card inf-card--green inf-stack-sm">
			<h2 class="inf-card__title">Oglasite se besplatno</h2>
			<p>Firme na loglasi.rs postavljaju oglase besplatno, a kupci su iz Loznice i okoline.</p>
			<a class="inf-btn inf-btn--light" href="{{ InfoUrl::main('za-firme') }}">Saznajte više</a>
		</div>
	</aside>
</div>
@endsection
