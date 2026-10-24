@extends('layouts.app')
@include('nalozi.partials.head')
@php
	$title = 'Zaboravljena lozinka | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lg-wrap">
	<section class="lgn-card" aria-labelledby="lgn-naslov">
		<h1 id="lgn-naslov">Zaboravljena lozinka</h1>
		<p class="lgn-lead">Upišite email adresu naloga. Poslaćemo vam link za novu lozinku.</p>
		@include('nalozi.partials.status')
		<form class="lgn-form" method="post" action="{{ url_slash('zaboravljena-lozinka') }}" novalidate>
			@csrf
			<div class="lgn-field">
				<label for="zab-email">E-pošta</label>
				<input id="zab-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required
					@if ($errors->has('email')) aria-invalid="true" aria-describedby="zab-email-greska" @endif>
				@include('nalozi.partials.greska', ['field' => 'email', 'prefix' => 'zab-'])
			</div>
			<button class="lgn-btn" type="submit">Pošalji link</button>
		</form>
		<p class="lgn-switch"><a href="{{ url_slash('prijava') }}">Nazad na prijavu</a></p>
	</section>
</div>
@endsection
