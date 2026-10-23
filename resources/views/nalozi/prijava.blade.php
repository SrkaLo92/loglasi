@extends('layouts.app')
@include('nalozi.partials.head')
@php
	$title = 'Prijava | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lg-wrap">
	<section class="lgn-card" aria-labelledby="lgn-naslov">
		<h1 id="lgn-naslov">Prijava</h1>
		@include('nalozi.partials.status')
		@include('nalozi.partials.forma-prijava')
		<p class="lgn-switch">Nemate nalog? <a href="{{ url_slash('registracija') }}">Registrujte se besplatno</a></p>
	</section>
</div>
@endsection
