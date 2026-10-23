<form class="lgn-form" method="post" action="{{ url_slash('prijava') }}" novalidate>
	@csrf
	<div class="lgn-field">
		<label for="prijava-email">Email</label>
		<input id="prijava-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required
			@if ($errors->has('email')) aria-invalid="true" aria-describedby="prijava-email-greska" @endif>
		@include('nalozi.partials.greska', ['field' => 'email', 'prefix' => 'prijava-'])
	</div>
	<div class="lgn-field">
		<label for="prijava-lozinka">Lozinka</label>
		<input id="prijava-lozinka" type="password" name="password" autocomplete="current-password" required
			@if ($errors->has('password')) aria-invalid="true" aria-describedby="prijava-password-greska" @endif>
		@include('nalozi.partials.greska', ['field' => 'password', 'prefix' => 'prijava-'])
	</div>
	<div class="lgn-row">
		<label class="lgn-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Zapamti me</label>
		<a href="{{ url_slash('zaboravljena-lozinka') }}">Zaboravili ste lozinku?</a>
	</div>
	<button class="lgn-btn" type="submit">Prijavi se</button>
</form>
