@extends('layouts.app')
@include('nalozi.partials.head')
@php
	$title = 'Prijava i registracija | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lg-wrap">
	<h1 class="lgn-title">Prijava i registracija</h1>
	@include('nalozi.partials.status')
	<div class="lgn-dual">
		<section class="lgn-card" aria-labelledby="lgn-prijava">
			<h2 id="lgn-prijava">Prijava</h2>
			@include('nalozi.partials.forma-prijava')
		</section>
		<section class="lgn-card" aria-labelledby="lgn-registracija">
			<h2 id="lgn-registracija">Novi nalog</h2>
			@include('nalozi.partials.forma-registracija')
		</section>
	</div>
</div>
@endsection
