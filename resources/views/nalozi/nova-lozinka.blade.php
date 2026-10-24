@extends('layouts.app')
@include('nalozi.partials.head')
@php
	$title = 'Nova lozinka | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lg-wrap">
	<section class="lgn-card" aria-labelledby="lgn-naslov">
		<h1 id="lgn-naslov">Nova lozinka</h1>
		<form class="lgn-form" method="post" action="{{ url_slash('nova-lozinka') }}" novalidate>
			@csrf
			<input type="hidden" name="token" value="{{ $token }}">
			<div class="lgn-field">
				<label for="nova-email">E-pošta</label>
				<input id="nova-email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required
					@if ($errors->has('email')) aria-invalid="true" aria-describedby="nova-email-greska" @endif>
				@include('nalozi.partials.greska', ['field' => 'email', 'prefix' => 'nova-'])
			</div>
			<div class="lgn-field">
				<label for="nova-lozinka">Nova lozinka</label>
				<input id="nova-lozinka" type="password" name="password" autocomplete="new-password" minlength="8" required
					aria-describedby="nova-lozinka-pomoc{{ $errors->has('password') ? ' nova-password-greska' : '' }}" @if ($errors->has('password')) aria-invalid="true" @endif>
				<p class="lgn-help" id="nova-lozinka-pomoc">Najmanje 8 znakova.</p>
				@include('nalozi.partials.greska', ['field' => 'password', 'prefix' => 'nova-'])
			</div>
			<div class="lgn-field">
				<label for="nova-lozinka2">Ponovite lozinku</label>
				<input id="nova-lozinka2" type="password" name="password_confirmation" autocomplete="new-password" required>
			</div>
			<button class="lgn-btn" type="submit">Sačuvaj lozinku</button>
		</form>
	</section>
</div>
@endsection
