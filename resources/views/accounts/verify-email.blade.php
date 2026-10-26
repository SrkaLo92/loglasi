@extends('layouts.app')
@include('accounts.partials.head')
@php
	$title = 'Potvrda email adrese | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lg-wrap">
	<section class="lgn-card" aria-labelledby="lgn-naslov">
		<h1 id="lgn-naslov">Potvrdite email adresu</h1>
		@include('accounts.partials.status')
		<p>Poslali smo link na <strong>{{ auth()->user()->email }}</strong>. Otvorite mejl i kliknite na link. Ako mejl ne stigne za nekoliko minuta, proverite folder za neželjenu poštu.</p>
		<form method="post" action="{{ url_slash('potvrda-emaila/ponovo') }}">
			@csrf
			<button class="lgn-btn" type="submit">Pošalji link ponovo</button>
		</form>
		<p class="lgn-switch"><a href="{{ url_slash('moj-nalog') }}">Nazad na moj nalog</a></p>
	</section>
</div>
@endsection
