@extends('info.layout', ['section' => 'posalji-vest', 'title' => 'Pošaljite vest ili događaj', 'noindex' => true])
@section('content')
<div class="inf-wrap">
	<div class="inf-pagehead">
		<div>
			<h1>Pošaljite vest ili događaj</h1>
			<p>Besplatno. Pregledamo svaku poruku pre objave, obično isti dan.</p>
		</div>
	</div>
	<form class="inf-form inf-formcard" method="post" action="{{ \App\Support\Info\InfoUrl::to('posalji-vest') }}">
		@csrf
		<div class="inf-hp" aria-hidden="true"><label>Sajt <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
		<fieldset class="inf-radios">
			<legend class="inf-visually-hidden">Šta šaljete</legend>
			@foreach (['event' => 'Događaj', 'news' => 'Vest', 'notice' => 'Obaveštenje'] as $type => $label)
				<label><input type="radio" name="type" value="{{ $type }}" @checked(old('type', 'event') === $type)><span>{{ $label }}</span></label>
			@endforeach
		</fieldset>
		<label>Naslov<input type="text" name="title" value="{{ old('title') }}" required minlength="5" maxlength="160" placeholder="npr. Koncert u Vukovom domu kulture"></label>
		<div class="inf-form__two">
			<label>Datum događaja<input type="date" name="event_date" value="{{ old('event_date') }}"></label>
			<label>Vreme<input type="time" name="event_time" value="{{ old('event_time') }}"></label>
		</div>
		<label>Mesto održavanja<input type="text" name="event_venue" value="{{ old('event_venue') }}" maxlength="160" placeholder="npr. Vukov dom kulture"></label>
		<label>Mesto<input type="text" name="place" value="{{ old('place') }}" maxlength="80" placeholder="Loznica, Banja Koviljača, Lešnica…"></label>
		<label>Tekst<textarea name="summary" rows="5" required minlength="20" maxlength="3000" placeholder="Šta, kada, za koga, cena ulaznice, kontakt">{{ old('summary') }}</textarea></label>
		<label>Vaše ime<input type="text" name="submitter_name" value="{{ old('submitter_name') }}" required maxlength="120" autocomplete="name"></label>
		<label>Vaš mejl (ne objavljujemo ga)<input type="email" name="submitter_email" value="{{ old('submitter_email') }}" required maxlength="190" autocomplete="email"></label>
		<label class="inf-check"><input type="checkbox" name="consent" value="1" @checked(old('consent'))> Potvrđujem da je tekst tačan i da sme da se objavi na loglasi Info.</label>
		@if ($errors->any())<p class="inf-error" role="alert">{{ $errors->first() }}</p>@endif
		<button class="inf-btn" type="submit">Pošalji na pregled</button>
		<p class="inf-meta">Fotografije za sada šaljite na <a class="inf-link" href="mailto:{{ \App\Support\Settings::get('contact_email', 'kontakt@loglasi.rs') }}">{{ \App\Support\Settings::get('contact_email', 'kontakt@loglasi.rs') }}</a>, samo one koje ste sami napravili ili imate pravo da objavite.</p>
	</form>
</div>
@endsection
