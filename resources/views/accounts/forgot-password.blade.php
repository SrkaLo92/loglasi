@extends('layouts.app')
@include('accounts.partials.head')
@php
	$title = 'Zaboravljena lozinka | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lg-wrap">
	<section class="lgn-card" aria-labelledby="lgn-title">
		<h1 id="lgn-title">Zaboravljena lozinka</h1>
		<p class="lgn-lead">Upišite email adresu naloga. Poslaćemo vam link za novu lozinku.</p>
		@include('accounts.partials.status')
		<form class="lgn-form" method="post" action="{{ url_slash('zaboravljena-lozinka') }}" novalidate>
			@csrf
			<div class="lgn-field">
				<label for="zab-email">E-pošta</label>
				<input id="zab-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required
					@if ($errors->has('email')) aria-invalid="true" aria-describedby="zab-email-greska" @endif>
				@include('accounts.partials.error', ['field' => 'email', 'prefix' => 'zab-'])
			</div>
			<button class="lgn-btn" type="submit">Pošalji link</button>
		</form>
		<p class="lgn-switch"><a href="{{ \App\Support\Auth\LoginPage::url() }}">Nazad na prijavu</a></p>
	</section>
</div>
@endsection
