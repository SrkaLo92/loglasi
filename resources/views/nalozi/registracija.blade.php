@extends('layouts.app')
@include('nalozi.partials.head')
@php
	$title = 'Registracija | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lg-wrap">
	<section class="lgn-card" aria-labelledby="lgn-naslov">
		<h1 id="lgn-naslov">Registracija</h1>
		<p class="lgn-lead">Nalog je besplatan. Besplatno možete da imate do {{ (int) \App\Support\Settings::get('private_active_limit') }} aktivnih oglasa.</p>
		@include('nalozi.partials.status')
		@include('nalozi.partials.forma-registracija')
		<p class="lgn-switch">Već imate nalog? <a href="{{ url_slash('prijava') }}">Prijavite se</a></p>
	</section>
</div>
@endsection
