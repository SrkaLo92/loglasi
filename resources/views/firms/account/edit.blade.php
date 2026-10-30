@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/firms.css') }}">
@endpush
@push('scripts')
<script src="{{ asset_v('js/firm-account.js') }}" defer></script>
@endpush
@php
	$title = 'Profil firme | loglasi.rs';
	$robots = 'noindex, follow';
	// Field attributes: aria-invalid + described-by error id.
	$invalid = fn (string $field, ?string $help = null) => new \Illuminate\Support\HtmlString(
		($errors->has($field) ? 'aria-invalid="true" ' : '')
		.(($help || $errors->has($field)) ? 'aria-describedby="'.e(trim(($help ?? '').' '.($errors->has($field) ? 'firma-'.$field.'-greska' : ''))).'"' : '')
	);
	$coordinates = $firm->lat !== null && $firm->lng !== null ? rtrim(rtrim(number_format($firm->lat, 7, '.', ''), '0'), '.').', '.rtrim(rtrim(number_format($firm->lng, 7, '.', ''), '0'), '.') : '';
	$logo = \App\Support\Firms\FirmImages::url($firm->logo_path);
	$cover = \App\Support\Firms\FirmImages::url($firm->cover_path);
@endphp

@section('content')
<div class="lgf lgf--account lg-wrap">
	<h1>Profil firme</h1>
	<p class="lgf-lead">{{ $firm->name ?: $user->name }} <span class="lgf-badge lgf-badge--firm">Firma</span></p>

	@include('firms.partials.account-nav', ['active' => 'profile'])

	@if (session('status'))
		<p class="lgf-alert lgf-alert--ok" role="status">{{ session('status') }}</p>
	@endif
	@if ($errors->any())
		<div class="lgf-alert lgf-alert--err" role="alert">
			<p>Podaci nisu sačuvani:</p>
			<ul>
				@foreach (array_unique($errors->all()) as $message)
					<li>{{ $message }}</li>
				@endforeach
			</ul>
		</div>
	@endif

	@include('firms.partials.package-status', ['status' => $status])

	<form class="lgf-form" method="post" action="{{ url_slash('moj-nalog/firma') }}" enctype="multipart/form-data" novalidate>
		@csrf
		@method('PUT')

		<section class="lgf-card lgf-section" aria-labelledby="firma-osnovno">
			<h2 id="firma-osnovno">Osnovni podaci</h2>
			<div class="lgf-field">
				<label for="firma-name">Naziv firme</label>
				<input id="firma-name" type="text" name="name" value="{{ old('name', $firm->name) }}" maxlength="{{ \App\Support\Firms\FirmProfileUpdater::NAME_MAX }}" required autocomplete="organization" {{ $invalid('name') }}>
				@error('name')<p class="lgf-error" id="firma-name-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field">
				<label for="firma-activity">Delatnost</label>
				<input id="firma-activity" type="text" name="activity" value="{{ old('activity', $firm->activity) }}" maxlength="{{ \App\Support\Firms\FirmProfileUpdater::ACTIVITY_MAX }}" placeholder="npr. Auto servis" {{ $invalid('activity', 'firma-activity-pomoc') }}>
				<p class="lgf-help" id="firma-activity-pomoc">Kratko, do {{ \App\Support\Firms\FirmProfileUpdater::ACTIVITY_MAX }} znakova. Prikazuje se uz naziv i u pretrazi Google-a.</p>
				@error('activity')<p class="lgf-error" id="firma-activity-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field lgf-wide">
				<label for="firma-description">Opis firme</label>
				<textarea id="firma-description" name="description" maxlength="{{ \App\Support\Firms\FirmProfileUpdater::DESCRIPTION_MAX }}" rows="5" {{ $invalid('description', 'firma-description-pomoc') }}>{{ old('description', $firm->description) }}</textarea>
				<p class="lgf-help" id="firma-description-pomoc">Šta radite, za koga i od kada. Do {{ \App\Support\Firms\FirmProfileUpdater::DESCRIPTION_MAX }} znakova, bez HTML-a.</p>
				@error('description')<p class="lgf-error" id="firma-description-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field">
				<label for="firma-pib">PIB</label>
				<input id="firma-pib" type="text" name="pib" value="{{ old('pib', $firm->pib) }}" inputmode="numeric" maxlength="9" {{ $invalid('pib') }}>
				@error('pib')<p class="lgf-error" id="firma-pib-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field">
				<label for="firma-mb">Matični broj</label>
				<input id="firma-mb" type="text" name="mb" value="{{ old('mb', $firm->mb) }}" inputmode="numeric" maxlength="8" {{ $invalid('mb') }}>
				@error('mb')<p class="lgf-error" id="firma-mb-greska">{{ $message }}</p>@enderror
			</div>
		</section>

		<section class="lgf-card lgf-section" aria-labelledby="firma-adresa-naslov">
			<h2 id="firma-adresa-naslov">Adresa i mapa</h2>
			<div class="lgf-field">
				<label for="firma-address">Adresa</label>
				<input id="firma-address" type="text" name="address" value="{{ old('address', $firm->address) }}" maxlength="{{ \App\Support\Firms\FirmProfileUpdater::ADDRESS_MAX }}" autocomplete="street-address" placeholder="npr. Kneza Miloša 12" {{ $invalid('address') }}>
				@error('address')<p class="lgf-error" id="firma-address-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field">
				<label for="firma-location">Mesto</label>
				<select id="firma-location" data-pick name="location_id" {{ $invalid('location_id') }}>
					<option value="">Loznica (podrazumevano)</option>
					@foreach ($locations as $location)
						<option value="{{ $location->id }}" @selected((string) old('location_id', $firm->location_id) === (string) $location->id)>{{ $location->name }}</option>
					@endforeach
				</select>
				@error('location_id')<p class="lgf-error" id="firma-location_id-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field lgf-wide">
				<label for="firma-coordinates">Koordinate na mapi</label>
				<input id="firma-coordinates" type="text" name="coordinates" value="{{ old('coordinates', $coordinates) }}" inputmode="decimal" placeholder="44.5331, 19.2258" {{ $invalid('coordinates', 'firma-coordinates-pomoc') }}>
				<p class="lgf-help" id="firma-coordinates-pomoc">Nije obavezno. U Google mapama kliknite desnim tasterom na ulaz firme i kopirajte prvi red (dva broja). Bez koordinata mapa se traži po adresi.</p>
				@error('coordinates')<p class="lgf-error" id="firma-coordinates-greska">{{ $message }}</p>@enderror
				@if ($coordinates !== '')
					<p class="lgf-help"><a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($coordinates) }}" target="_blank" rel="noopener nofollow">Proverite tačku na mapi</a></p>
				@endif
			</div>
		</section>

		<section class="lgf-card lgf-section" aria-labelledby="firma-kontakt">
			<h2 id="firma-kontakt">Kontakt i mreže</h2>
			<div class="lgf-field">
				<label for="firma-phone">Telefon firme</label>
				<input id="firma-phone" type="tel" name="phone" value="{{ old('phone', \App\Support\Phone::national($firm->phone) ?: $firm->phone) }}" inputmode="tel" autocomplete="tel" {{ $invalid('phone', 'firma-phone-pomoc') }}>
				<p class="lgf-help" id="firma-phone-pomoc">Ako je prazno, prikazuje se telefon naloga.</p>
				@error('phone')<p class="lgf-error" id="firma-phone-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field">
				<span class="lgf-label">Dugmad na profilu</span>
				<input type="hidden" name="whatsapp" value="0">
				<label class="lgf-check"><input type="checkbox" name="whatsapp" value="1" @checked(old('whatsapp', $firm->whatsapp ?? true))> WhatsApp</label>
				<input type="hidden" name="viber" value="0">
				<label class="lgf-check"><input type="checkbox" name="viber" value="1" @checked(old('viber', $firm->viber ?? true))> Viber</label>
				<p class="lgf-help">Za fiksni broj ova dugmad se ne prikazuju.</p>
			</div>
			<div class="lgf-field">
				<label for="firma-website">Sajt</label>
				<input id="firma-website" type="url" name="website" value="{{ old('website', $firm->website) }}" inputmode="url" placeholder="npr. vulkanizer.rs" {{ $invalid('website') }}>
				@error('website')<p class="lgf-error" id="firma-website-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field">
				<label for="firma-facebook">Facebook</label>
				<input id="firma-facebook" type="text" name="facebook" value="{{ old('facebook', $firm->facebook) }}" placeholder="link ili @ime" {{ $invalid('facebook') }}>
				@error('facebook')<p class="lgf-error" id="firma-facebook-greska">{{ $message }}</p>@enderror
			</div>
			<div class="lgf-field">
				<label for="firma-instagram">Instagram</label>
				<input id="firma-instagram" type="text" name="instagram" value="{{ old('instagram', $firm->instagram) }}" placeholder="link ili @ime" {{ $invalid('instagram') }}>
				@error('instagram')<p class="lgf-error" id="firma-instagram-greska">{{ $message }}</p>@enderror
			</div>
		</section>

		<section class="lgf-card lgf-section" aria-labelledby="firma-radno-vreme">
			<h2 id="firma-radno-vreme">Radno vreme</h2>
			<p class="lgf-help lgf-wide" id="firma-hours-pomoc">Upišite po danima, npr. „08–16”, „8–12, 13–17” ili „Zatvoreno”. Prazan dan se prikazuje kao neradni ako je upisan bar jedan dan.</p>
			<div class="lgf-hours">
				@foreach (\App\Support\Listing\WorkingHours::DAYS as $day => [$label])
					<label for="firma-hours-{{ $day }}">{{ $label }}</label>
					<div>
						<input id="firma-hours-{{ $day }}" type="text" name="hours[{{ $day }}]" value="{{ old('hours.'.$day, $hours[$day] ?? '') }}" maxlength="{{ \App\Support\Firms\WorkingHoursInput::MAX_LENGTH }}" placeholder="{{ $day <= 5 ? '08–16' : ($day === 6 ? '08–13' : 'Zatvoreno') }}" aria-describedby="firma-hours-pomoc" @error('hours.'.$day) aria-invalid="true" @enderror>
						@error('hours.'.$day)<p class="lgf-error">{{ $message }}</p>@enderror
					</div>
				@endforeach
			</div>
		</section>

		<section class="lgf-card lgf-section" aria-labelledby="firma-slike">
			<h2 id="firma-slike">Logo i naslovna slika</h2>
			<div class="lgf-field">
				<label for="firma-logo">Logo</label>
				<div class="lgf-image">
					@if ($logo)
						<img class="lgf-image__preview" src="{{ $logo }}" alt="Trenutni logo" width="96" height="96">
					@endif
					<input id="firma-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" {{ $invalid('logo', 'firma-logo-pomoc') }}>
				</div>
				<p class="lgf-help" id="firma-logo-pomoc">JPG, PNG ili WebP, najmanje 100 × 100 piksela, do 5 MB.</p>
				@error('logo')<p class="lgf-error" id="firma-logo-greska">{{ $message }}</p>@enderror
				@if ($logo)
					<label class="lgf-check"><input type="checkbox" name="remove_logo" value="1"> Ukloni logo</label>
				@endif
			</div>
			<div class="lgf-field">
				<label for="firma-cover">Naslovna slika</label>
				<div class="lgf-image">
					@if ($cover)
						<img class="lgf-image__preview lgf-image__preview--cover" src="{{ $cover }}" alt="Trenutna naslovna slika" width="240" height="75">
					@endif
					<input id="firma-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" {{ $invalid('cover', 'firma-cover-pomoc') }}>
				</div>
				<p class="lgf-help" id="firma-cover-pomoc">Široka slika, najmanje 800 × 250 piksela, do 8 MB. Seče se na sredini.</p>
				@error('cover')<p class="lgf-error" id="firma-cover-greska">{{ $message }}</p>@enderror
				@if ($cover)
					<label class="lgf-check"><input type="checkbox" name="remove_cover" value="1"> Ukloni naslovnu sliku</label>
				@endif
			</div>
		</section>

		<div class="lgf-actions">
			<button class="lg-btn lg-btn--green" type="submit">Sačuvaj podatke firme</button>
			<a href="{{ url_slash('korisnik/'.$user->slug) }}">Pogledaj javni profil</a>
		</div>
	</form>
</div>
@endsection
