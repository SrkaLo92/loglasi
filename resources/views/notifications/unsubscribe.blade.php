@extends('layouts.app')
@include('notifications.partials.head')
@php
	$title = 'Odjava od obaveštenja | loglasi.rs';
	$robots = 'noindex, nofollow';
@endphp

@section('content')
<div class="lgn lg-wrap">
	<section class="lgn-card lgt-done">
		<h1>Odjava od obaveštenja</h1>
		@if ($done)
			<p role="status">Više vam ne šaljemo obaveštenja „{{ $label }}”.</p>
			<p class="lgn-help">Možete ih ponovo uključiti u <a href="{{ url_slash('moj-nalog/obavestenja/podesavanja') }}">podešavanjima obaveštenja</a>.</p>
		@else
			<p>Da li želite da vam više ne šaljemo obaveštenja „{{ $label }}”?</p>
			<form method="post" action="{{ $action }}">
				<button class="lgn-btn" type="submit">Odjavi me</button>
			</form>
			<p class="lgn-help">Ostala obaveštenja ostaju kako ste ih podesili.</p>
		@endif
	</section>
</div>
@endsection
