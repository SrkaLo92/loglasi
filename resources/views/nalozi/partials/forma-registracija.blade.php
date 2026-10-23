<form class="lgn-form" method="post" action="{{ url_slash('registracija') }}" novalidate>
	@csrf
	<div class="lgn-field">
		<label for="reg-ime">Ime i prezime</label>
		<input id="reg-ime" type="text" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="80" required
			@if ($errors->has('name')) aria-invalid="true" aria-describedby="reg-name-greska" @endif>
		@include('nalozi.partials.greska', ['field' => 'name', 'prefix' => 'reg-'])
	</div>
	<div class="lgn-field">
		<label for="reg-email">Email</label>
		<input id="reg-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required
			@if ($errors->has('email')) aria-invalid="true" aria-describedby="reg-email-greska" @endif>
		@include('nalozi.partials.greska', ['field' => 'email', 'prefix' => 'reg-'])
	</div>
	<div class="lgn-field">
		<label for="reg-telefon">Telefon</label>
		<input id="reg-telefon" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" placeholder="065 123 4567" required
			aria-describedby="reg-telefon-pomoc{{ $errors->has('phone') ? ' reg-phone-greska' : '' }}" @if ($errors->has('phone')) aria-invalid="true" @endif>
		<p class="lgn-help" id="reg-telefon-pomoc">Broj se prikazuje u vašim oglasima. Može i strani broj sa pozivnim brojem (+387…).</p>
		@include('nalozi.partials.greska', ['field' => 'phone', 'prefix' => 'reg-'])
	</div>
	<div class="lgn-field">
		<label for="reg-lozinka">Lozinka</label>
		<input id="reg-lozinka" type="password" name="password" autocomplete="new-password" minlength="8" required
			aria-describedby="reg-lozinka-pomoc{{ $errors->has('password') ? ' reg-password-greska' : '' }}" @if ($errors->has('password')) aria-invalid="true" @endif>
		<p class="lgn-help" id="reg-lozinka-pomoc">Najmanje 8 znakova.</p>
		@include('nalozi.partials.greska', ['field' => 'password', 'prefix' => 'reg-'])
	</div>
	<div class="lgn-field">
		<label for="reg-lozinka2">Ponovite lozinku</label>
		<input id="reg-lozinka2" type="password" name="password_confirmation" autocomplete="new-password" required>
	</div>
	<div class="lgn-field">
		<label class="lgn-check">
			<input type="checkbox" name="accept_terms" value="1" @checked(old('accept_terms')) required
				@if ($errors->has('accept_terms')) aria-invalid="true" aria-describedby="reg-accept_terms-greska" @endif>
			<span>Prihvatam <a href="{{ url_slash('uslovi-koriscenja') }}">uslove korišćenja</a> i <a href="{{ url_slash('politika-privatnosti') }}">politiku privatnosti</a>.</span>
		</label>
		@include('nalozi.partials.greska', ['field' => 'accept_terms', 'prefix' => 'reg-'])
	</div>
	<button class="lgn-btn lgn-btn--orange" type="submit">Napravi nalog</button>
	<p class="lgn-note">Registracijom dobijate nalog privatnog lica. Za nalog firme pišite nam preko <a href="{{ url_slash('za-firme') }}">strane za firme</a>.</p>
</form>
