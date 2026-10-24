@extends('layouts.app')
@include('nalozi.partials.head')
@php
	$title = 'Moj nalog | loglasi.rs';
	$robots = 'noindex, follow';
	$podaci = $errors->getBag('podaci');
	$lozinka = $errors->getBag('lozinka');
	$slika = $errors->getBag('slika');
@endphp

@section('content')
<div class="lgn lgn--nalog lg-wrap">
	<header class="lgn-head">
		<div class="lgn-avatar" aria-hidden="true">
			@if ($user->avatarUrl())
				<img src="{{ $user->avatarUrl() }}" alt="" width="72" height="72">
			@else
				<span>{{ $user->initials() }}</span>
			@endif
		</div>
		<div class="lgn-head__text">
			<h1>Moj nalog</h1>
			<p class="lgn-head__name">{{ $user->name }}
				@if ($user->isFirm())
					<span class="lgn-badge lgn-badge--firma">Firma</span>
				@else
					<span class="lgn-badge">Privatno lice</span>
				@endif
			</p>
			<p class="lgn-head__meta">Na sajtu od {{ $user->created_at->format('j. n. Y.') }}</p>
		</div>
		<form method="post" action="{{ url_slash('odjava') }}" class="lgn-head__logout">
			@csrf
			<button class="lgn-btn lgn-btn--line" type="submit">Odjavi se</button>
		</form>
	</header>

	@include('nalozi.partials.status')

	@unless ($user->hasVerifiedEmail())
		<div class="lgn-alert lgn-alert--warn">
			<p>Email adresa još nije potvrđena. Kliknite na link iz mejla koji smo poslali na <strong>{{ $user->email }}</strong>.</p>
			<form method="post" action="{{ url_slash('potvrda-emaila/ponovo') }}">
				@csrf
				<button class="lgn-link" type="submit">Pošalji link ponovo</button>
			</form>
		</div>
	@endunless

	<section class="lgn-card lgn-limit" aria-labelledby="lgn-limit">
		<h2 id="lgn-limit">Aktivni oglasi</h2>
		@if ($limits['unlimited'])
			<p>Vaš paket nema ograničenje broja oglasa. Aktivnih oglasa: {{ $limits['used_this_month'] }}.</p>
		@else
			<p>Imate <strong>{{ $limits['used_this_month'] }} od {{ $limits['monthly_limit'] }}</strong> besplatnih aktivnih oglasa. Preostalo: {{ $limits['remaining'] }}.</p>
			<p class="lgn-help">Mesto se oslobađa kad oglas istekne, kad ga označite kao prodat ili obrišete.</p>
		@endif
		<p><a href="{{ url_slash('moj-nalog/oglasi') }}">Moji oglasi</a></p>
	</section>

	<div class="lgn-grid">
		<section class="lgn-card" aria-labelledby="lgn-podaci">
			<h2 id="lgn-podaci">Podaci</h2>
			<form class="lgn-form" method="post" action="{{ url_slash('moj-nalog/podaci') }}" novalidate>
				@csrf
				@method('PUT')
				<div class="lgn-field">
					<label for="nalog-ime">Ime i prezime</label>
					<input id="nalog-ime" type="text" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" maxlength="80" required
						@if ($podaci->has('name')) aria-invalid="true" aria-describedby="nalog-name-greska" @endif>
					@include('nalozi.partials.greska', ['field' => 'name', 'bag' => 'podaci', 'prefix' => 'nalog-'])
				</div>
				<div class="lgn-field">
					<label for="nalog-email">Email</label>
					<input id="nalog-email" type="email" value="{{ $user->email }}" readonly aria-describedby="nalog-email-pomoc">
					<p class="lgn-help" id="nalog-email-pomoc">Email se ne menja ovde. Ako vam treba promena, pišite nam.</p>
				</div>
				<div class="lgn-field">
					<label for="nalog-telefon">Telefon</label>
					<input id="nalog-telefon" type="tel" name="phone" value="{{ old('phone', \App\Support\Phone::national($user->phone) ?: $user->phone) }}" autocomplete="tel" inputmode="tel" required
						aria-describedby="nalog-telefon-pomoc{{ $podaci->has('phone') ? ' nalog-phone-greska' : '' }}" @if ($podaci->has('phone')) aria-invalid="true" @endif>
					<p class="lgn-help" id="nalog-telefon-pomoc">
						@if ($user->isPhoneVerified())
							<span class="lgn-badge lgn-badge--ok">Telefon proveren</span> Promena broja skida oznaku provere.
						@else
							Broj još nije proveren. Proveru radi administrator.
						@endif
					</p>
					@include('nalozi.partials.greska', ['field' => 'phone', 'bag' => 'podaci', 'prefix' => 'nalog-'])
				</div>
				<div class="lgn-field">
					<label for="nalog-mesto">Mesto</label>
					<select id="nalog-mesto" name="location_id">
						<option value="">Nije izabrano</option>
						@foreach ($locations as $location)
							<option value="{{ $location->id }}" @selected((int) old('location_id', $user->location_id) === $location->id)>{{ $location->name }}</option>
						@endforeach
					</select>
					@include('nalozi.partials.greska', ['field' => 'location_id', 'bag' => 'podaci', 'prefix' => 'nalog-'])
				</div>
				<button class="lgn-btn" type="submit">Sačuvaj podatke</button>
			</form>
		</section>

		<section class="lgn-card" aria-labelledby="lgn-slika">
			<h2 id="lgn-slika">Profilna slika</h2>
			<form class="lgn-form" method="post" action="{{ url_slash('moj-nalog/slika') }}" enctype="multipart/form-data" novalidate>
				@csrf
				<div class="lgn-field">
					<label for="nalog-slika">Izaberite sliku (JPG, PNG ili WebP, do 5 MB)</label>
					<input id="nalog-slika" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required
						@if ($slika->has('avatar')) aria-invalid="true" aria-describedby="nalog-avatar-greska" @endif>
					@include('nalozi.partials.greska', ['field' => 'avatar', 'bag' => 'slika', 'prefix' => 'nalog-'])
				</div>
				<button class="lgn-btn" type="submit">Sačuvaj sliku</button>
			</form>
			@if ($user->avatar_path)
				<form method="post" action="{{ url_slash('moj-nalog/slika') }}" class="lgn-inline">
					@csrf
					@method('DELETE')
					<button class="lgn-link" type="submit">Ukloni sliku</button>
				</form>
			@endif
		</section>

		<section class="lgn-card" aria-labelledby="lgn-lozinka">
			<h2 id="lgn-lozinka">Lozinka</h2>
			<form class="lgn-form" method="post" action="{{ url_slash('moj-nalog/lozinka') }}" novalidate>
				@csrf
				@method('PUT')
				<div class="lgn-field">
					<label for="nalog-trenutna">Trenutna lozinka</label>
					<input id="nalog-trenutna" type="password" name="current_password" autocomplete="current-password" required
						@if ($lozinka->has('current_password')) aria-invalid="true" aria-describedby="nalog-current_password-greska" @endif>
					@include('nalozi.partials.greska', ['field' => 'current_password', 'bag' => 'lozinka', 'prefix' => 'nalog-'])
				</div>
				<div class="lgn-field">
					<label for="nalog-nova">Nova lozinka</label>
					<input id="nalog-nova" type="password" name="password" autocomplete="new-password" minlength="8" required
						@if ($lozinka->has('password')) aria-invalid="true" aria-describedby="nalog-password-greska" @endif>
					@include('nalozi.partials.greska', ['field' => 'password', 'bag' => 'lozinka', 'prefix' => 'nalog-'])
				</div>
				<div class="lgn-field">
					<label for="nalog-nova2">Ponovite novu lozinku</label>
					<input id="nalog-nova2" type="password" name="password_confirmation" autocomplete="new-password" required>
				</div>
				<button class="lgn-btn" type="submit">Promeni lozinku</button>
			</form>
		</section>

		<section class="lgn-card lgn-danger" aria-labelledby="lgn-brisanje">
			<h2 id="lgn-brisanje">Brisanje naloga</h2>
			<p>Brišu se nalog, svi vaši oglasi, poruke i sačuvani oglasi. Ovo ne može da se vrati.</p>
			<a class="lgn-btn lgn-btn--danger" href="{{ url_slash('moj-nalog/brisanje') }}">Obriši nalog</a>
		</section>
	</div>
</div>
@endsection
