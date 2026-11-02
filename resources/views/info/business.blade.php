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
@php
	$bpPhotos = $business->photoUrls();
	$bpFeatured = $business->isFeatured();
	$bpPhone = $business->phones()[0] ?? null;
	$bpTel = $bpPhone ? preg_replace('/[^0-9+]/', '', $bpPhone) : null;
	$bpViber = $business->viberNumber();
	$bpMaps = $business->mapsUrl();
	$bpListings = $business->listings(6);
	$bpCount = $bpListings->isNotEmpty() ? $business->listingsCount() : 0;
	$bpHours = $business->hoursLines();
	$bpAbout = $business->description ?: $business->firm?->description;
@endphp
<div class="inf-wrap">
	<nav class="inf-crumbs" aria-label="Putanja"><a href="{{ InfoUrl::to('firme') }}">Firme</a>@if ($business->category)<span aria-hidden="true">/</span><a href="{{ InfoUrl::to('firme') }}?kategorija={{ rawurlencode($business->category) }}">{{ $business->category }}</a>@endif<span aria-hidden="true">/</span><span>{{ $business->name }}</span></nav>

	@if ($bpFeatured && $bpPhotos)
		<div @class(['inf-gal', 'inf-gal--1' => count($bpPhotos) === 1, 'inf-gal--2' => count($bpPhotos) === 2])>
			@foreach (array_slice($bpPhotos, 0, 3) as $photo)<img src="{{ $photo }}" alt="" @if (! $loop->first) loading="lazy" @endif>@endforeach
		</div>
	@endif

	<div class="inf-profile">
		<div class="inf-profile__main">
			<div class="inf-profile__intro">
				@if ($bpFeatured || $business->firm_id)
					<div class="inf-chips">
						@if ($bpFeatured)<span class="inf-tag">Istaknuta firma</span>@endif
						@if ($business->firm_id)<span class="inf-tag inf-tag--grey">Ima nalog na loglasi.rs</span>@endif
					</div>
				@endif
				<h1>{{ $business->name }}</h1>
				<p>{{ trim(($business->category ?: '').', '.($business->place ?: 'Loznica i okolina'), ', ') }}</p>
			</div>

			@if ($bpAbout)
				<section aria-labelledby="inf-o"><h2 id="inf-o">O firmi</h2><p class="inf-text">{{ $bpAbout }}</p></section>
			@endif

			@if ($bpListings->isNotEmpty())
				<section aria-labelledby="inf-fo">
					<div class="inf-head2"><h2 id="inf-fo" class="inf-h-sm">Oglasi ove firme na loglasi.rs</h2>@if ($business->profileUrl())<a class="inf-link" href="{{ $business->profileUrl() }}">Svi oglasi ({{ $bpCount }})</a>@endif</div>
					<div class="inf-grid3">
						@foreach ($bpListings as $l)
							<a class="inf-listing" href="{{ InfoUrl::main('oglas/'.$l->slug) }}">
								@if ($l->coverImage)<img src="{{ \App\Support\Search\ImageUrls::url($l->coverImage, 'thumb') }}" alt="" loading="lazy" decoding="async">
								@else<span class="inf-listing__ph" aria-hidden="true">{{ \App\Support\Search\CategoryIcons::forCategory($l->category, 32) }}<span>{{ $l->category?->name ?? 'Bez fotografije' }}</span></span>@endif
								<span class="inf-listing__body"><strong>{{ $l->title }}</strong><span class="inf-listing__price">{{ $l->priceLabel() }}</span></span>
							</a>
						@endforeach
					</div>
				</section>
			@endif

			<details class="inf-reqform" id="zahtev" @if ($errors->any()) open @endif>
				<summary>Ovo je vaša firma? Preuzmite profil, ispravite podatke ili zatražite uklanjanje</summary>
				<form class="inf-form" method="post" action="{{ InfoUrl::to('firme/'.$business->slug.'/zahtev') }}">
					@csrf
					<div class="inf-hp" aria-hidden="true"><label>Sajt <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
					<fieldset class="inf-radios">
						<legend class="inf-visually-hidden">Šta želite</legend>
						@foreach (InfoBusinessRequest::KINDS as $kind => $label)
							<label><input type="radio" name="kind" value="{{ $kind }}" @checked(old('kind', 'claim') === $kind)><span>{{ $label }}</span></label>
						@endforeach
					</fieldset>
					<div class="inf-form__two">
						<label>Ime i prezime<input type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>
						<label>Mejl<input type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email"></label>
					</div>
					<label>Telefon (nije obavezno)<input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel"></label>
					<label>Poruka (nije obavezno)<textarea name="message" rows="3" maxlength="2000">{{ old('message') }}</textarea></label>
					@if ($errors->any())<p class="inf-error" role="alert">{{ $errors->first() }}</p>@endif
					<button class="inf-btn" type="submit">Pošalji zahtev</button>
				</form>
			</details>
			<p class="inf-meta">Osnovni podaci su preuzeti iz javno dostupnih poslovnih izvora. Ako nešto nije tačno, javite nam preko forme iznad.</p>
		</div>

		<aside class="inf-contact" aria-label="Kontakt">
			@if ($bpTel)
				<a class="inf-btn" href="tel:{{ $bpTel }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>Pozovi {{ $bpPhone }}</a>
			@endif
			@if ($bpViber || $bpMaps)
				<div class="inf-contact__row">
					@if ($bpViber)<a class="inf-btn inf-btn--line" href="viber://chat?number=%2B{{ $bpViber }}">Viber</a>@endif
					@if ($bpMaps)<a class="inf-btn inf-btn--line" href="{{ $bpMaps }}" rel="noopener" target="_blank">Navigacija</a>@endif
				</div>
			@endif
			<dl>
				@if ($business->address)<dt>Adresa</dt><dd>{{ $business->address }}</dd>@endif
				@foreach (array_slice($business->phones(), 1) as $phone)<dt>Telefon</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></dd>@endforeach
				@if ($bpHours)<dt>Radno vreme</dt><dd>@foreach ($bpHours as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</dd>@endif
				@if ($business->website)<dt>Sajt</dt><dd><a href="{{ $business->website }}" rel="noopener nofollow" target="_blank">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($business->website, '/')) }}</a></dd>@endif
				@if ($business->facebook)<dt>Facebook</dt><dd><a href="{{ $business->facebook }}" rel="noopener nofollow" target="_blank">Facebook strana</a></dd>@endif
				@if ($business->instagram)<dt>Instagram</dt><dd><a href="{{ $business->instagram }}" rel="noopener nofollow" target="_blank">Instagram profil</a></dd>@endif
			</dl>
			<div class="inf-contact__own">Vi ste vlasnik? <a href="#zahtev">Preuzmite profil</a> ili pogledajte <a href="{{ InfoUrl::to('oglasavanje') }}">Istaknutu firmu</a>.</div>
		</aside>
	</div>
</div>
@endsection
