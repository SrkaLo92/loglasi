@extends('layouts.app')
@include('accounts.partials.head')
@push('scripts')
<script src="{{ asset_v('js/account.js') }}" defer></script>
@endpush
@php
	use App\Support\Pages\SiteIcons;

	$title = 'Podešavanja naloga | loglasi.rs';
	$robots = 'noindex, follow';
	$detailsErrors = $errors->getBag('podaci');
	$passwordErrors = $errors->getBag('lozinka');
	$avatarErrors = $errors->getBag('slika');
@endphp

@section('content')
<div class="lgn lga lga--settings lg-wrap">
	<header class="lga-hero lga-hero--plain">
		<div class="lga-hero__text">
			<h1>Podešavanja naloga</h1>
			<p>Podaci koje kupci vide uz vaše oglase, lozinka i nalog.</p>
		</div>
	</header>

	@include('accounts.partials.status')

	<section class="lga-card" aria-labelledby="lga-profil">
		<h2 id="lga-profil">Profil</h2>

		<div class="lga-photo">
			<span class="lga-photo__img" aria-hidden="true">
				@if ($user->avatarUrl())
					<img src="{{ $user->avatarUrl() }}" alt="" width="88" height="88">
				@else
					{{ $user->initials() }}
				@endif
			</span>
			<div class="lga-photo__body">
				<p class="lga-photo__title">Profilna slika</p>
				<p class="lga-help">Prikazuje se uz vaše oglase i poruke. JPG, PNG ili WebP, do 5 MB.</p>
				<div class="lga-photo__actions">
					<form method="post" action="{{ url_slash('moj-nalog/slika') }}" enctype="multipart/form-data" class="lga-photo__form" data-autosubmit>
						@csrf
						<label class="lga-btn lga-btn--line" for="nalog-slika">{{ SiteIcons::svg('camera', 18) }} {{ $user->avatar_path ? 'Promeni sliku' : 'Dodaj sliku' }}</label>
						<input class="lg-visually-hidden" id="nalog-slika" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required
							@if ($avatarErrors->has('avatar')) aria-invalid="true" aria-describedby="nalog-avatar-greska" @endif>
						<button class="lga-btn lga-btn--line lga-photo__save" type="submit">Sačuvaj sliku</button>
					</form>
					@if ($user->avatar_path)
						<form method="post" action="{{ url_slash('moj-nalog/slika') }}">
							@csrf
							@method('DELETE')
							<button class="lga-link" type="submit">Ukloni</button>
						</form>
					@endif
				</div>
				@include('accounts.partials.error', ['field' => 'avatar', 'bag' => 'slika', 'prefix' => 'nalog-'])
			</div>
		</div>

		<form class="lga-form" method="post" action="{{ url_slash('moj-nalog/podaci') }}" novalidate>
			@csrf
			@method('PUT')
			<div class="lga-form__grid">
				<div class="lgn-field">
					<label for="nalog-ime">Ime i prezime</label>
					<input id="nalog-ime" type="text" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" maxlength="80" required
						@if ($detailsErrors->has('name')) aria-invalid="true" aria-describedby="nalog-name-greska" @endif>
					@include('accounts.partials.error', ['field' => 'name', 'bag' => 'podaci', 'prefix' => 'nalog-'])
				</div>
				<div class="lgn-field">
					<label for="nalog-mesto">Mesto</label>
					<select id="nalog-mesto" name="location_id" data-pick>
						<option value="">Izaberite mesto</option>
						@foreach ($locations as $location)
							<option value="{{ $location->id }}" @selected((int) old('location_id', $user->location_id) === $location->id)>{{ $location->name }}</option>
						@endforeach
					</select>
					@include('accounts.partials.error', ['field' => 'location_id', 'bag' => 'podaci', 'prefix' => 'nalog-'])
				</div>
				<div class="lgn-field">
					<label for="nalog-telefon">Telefon</label>
					<input id="nalog-telefon" type="tel" name="phone" value="{{ old('phone', \App\Support\Phone::national($user->phone) ?: $user->phone) }}" autocomplete="tel" inputmode="tel" required
						aria-describedby="nalog-telefon-pomoc{{ $detailsErrors->has('phone') ? ' nalog-phone-greska' : '' }}" @if ($detailsErrors->has('phone')) aria-invalid="true" @endif>
					<p class="lga-help" id="nalog-telefon-pomoc">
						@if ($user->isPhoneVerified())
							<span class="lga-pill lga-pill--ok">{{ SiteIcons::svg('check', 14) }} Proveren</span> Nov broj ponovo proverava administrator.
						@else
							<span class="lga-pill lga-pill--wait">Čeka proveru</span> Proveru radi administrator.
						@endif
					</p>
					@include('accounts.partials.error', ['field' => 'phone', 'bag' => 'podaci', 'prefix' => 'nalog-'])
				</div>
				<div class="lgn-field">
					<label for="nalog-email">E-pošta</label>
					<input id="nalog-email" type="email" value="{{ $user->email }}" readonly aria-describedby="nalog-email-pomoc">
					<p class="lga-help" id="nalog-email-pomoc">
						@if ($user->hasVerifiedEmail())
							<span class="lga-pill lga-pill--ok">{{ SiteIcons::svg('check', 14) }} Potvrđena</span>
						@endif
						Za promenu adrese <a href="{{ url_slash('kontakt') }}">nam pišite</a>.
					</p>
				</div>
			</div>
			<div class="lga-form__end">
				<button class="lga-btn" type="submit">Sačuvaj promene</button>
			</div>
		</form>
	</section>

	<section class="lga-card" aria-labelledby="lga-password">
		<details class="lga-fold" @if ($passwordErrors->any()) open @endif>
			<summary>
				<span class="lga-task__icon">{{ SiteIcons::svg('lock', 20) }}</span>
				<span><strong id="lga-password">Lozinka</strong><small>Promenom lozinke odjavljujete se sa ostalih uređaja.</small></span>
				{{ SiteIcons::svg('chevron', 18) }}
			</summary>
			<form class="lga-form" method="post" action="{{ url_slash('moj-nalog/lozinka') }}" novalidate>
				@csrf
				@method('PUT')
				<div class="lgn-field">
					<label for="nalog-trenutna">Trenutna lozinka</label>
					<input id="nalog-trenutna" type="password" name="current_password" autocomplete="current-password" required
						@if ($passwordErrors->has('current_password')) aria-invalid="true" aria-describedby="nalog-current_password-greska" @endif>
					@include('accounts.partials.error', ['field' => 'current_password', 'bag' => 'lozinka', 'prefix' => 'nalog-'])
				</div>
				<div class="lga-form__grid">
					<div class="lgn-field">
						<label for="nalog-nova">Nova lozinka</label>
						<input id="nalog-nova" type="password" name="password" autocomplete="new-password" minlength="8" required aria-describedby="nalog-nova-pomoc{{ $passwordErrors->has('password') ? ' nalog-password-greska' : '' }}"
							@if ($passwordErrors->has('password')) aria-invalid="true" @endif>
						<p class="lga-help" id="nalog-nova-pomoc">Najmanje 8 znakova.</p>
						@include('accounts.partials.error', ['field' => 'password', 'bag' => 'lozinka', 'prefix' => 'nalog-'])
					</div>
					<div class="lgn-field">
						<label for="nalog-nova2">Ponovite novu lozinku</label>
						<input id="nalog-nova2" type="password" name="password_confirmation" autocomplete="new-password" required>
					</div>
				</div>
				<div class="lga-form__end">
					<button class="lga-btn" type="submit">Promeni lozinku</button>
				</div>
			</form>
		</details>
	</section>

	<section class="lga-card lga-card--links" aria-label="Ostalo">
		<a class="lga-linkrow" href="{{ url_slash('moj-nalog/obavestenja/podesavanja') }}">
			<span class="lga-task__icon">{{ SiteIcons::svg('bell', 20) }}</span>
			<span><strong>Podešavanja obaveštenja</strong><small>Šta vam šaljemo mejlom i na telefon.</small></span>
			{{ SiteIcons::svg('chevron-r', 18) }}
		</a>
		<a class="lga-linkrow" href="{{ $user->profileUrl() }}">
			<span class="lga-task__icon">{{ SiteIcons::svg('eye', 20) }}</span>
			<span><strong>Moj javni profil</strong><small>Kako kupci vide vas i vaše oglase.</small></span>
			{{ SiteIcons::svg('chevron-r', 18) }}
		</a>
		<form method="post" action="{{ url_slash('odjava') }}">
			@csrf
			<button class="lga-linkrow" type="submit">
				<span class="lga-task__icon">{{ SiteIcons::svg('logout', 20) }}</span>
				<span><strong>Odjavi se</strong><small>Oglasi i poruke ostaju sačuvani.</small></span>
				{{ SiteIcons::svg('chevron-r', 18) }}
			</button>
		</form>
		<a class="lga-linkrow lga-linkrow--danger" href="{{ url_slash('moj-nalog/brisanje') }}">
			<span class="lga-task__icon">{{ SiteIcons::svg('trash', 20) }}</span>
			<span><strong>Obriši nalog</strong><small>Trajno briše nalog, oglase i poruke.</small></span>
			{{ SiteIcons::svg('chevron-r', 18) }}
		</a>
	</section>
</div>
@endsection
