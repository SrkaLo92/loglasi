@extends('layouts.app')
@include('accounts.partials.head')
@php
	$title = 'Brisanje naloga | loglasi.rs';
	$robots = 'noindex, follow';
	$bag = $errors->getBag('brisanje');
@endphp

@section('content')
<div class="lgn lg-wrap">
	<section class="lgn-card lgn-danger" aria-labelledby="lgn-title">
		<h1 id="lgn-title">Brisanje naloga</h1>
		<p>Trajno se brišu:</p>
		<ul>
			<li>nalog <strong>{{ $user->email }}</strong> i podaci (ime, telefon, slika),</li>
			<li>svi vaši oglasi,</li>
			<li>razgovori i poruke,</li>
			<li>sačuvani oglasi i pretrage.</li>
		</ul>
		<p>Ovo ne može da se vrati. Plaćene usluge se ne vraćaju.</p>
		<form class="lgn-form" method="post" action="{{ url_slash('moj-nalog/brisanje') }}" novalidate>
			@csrf
			@method('DELETE')
			<div class="lgn-field">
				<label for="brisanje-lozinka">Lozinka</label>
				<input id="brisanje-lozinka" type="password" name="password" autocomplete="current-password" required
					@if ($bag->has('password')) aria-invalid="true" aria-describedby="brisanje-password-greska" @endif>
				@include('accounts.partials.error', ['field' => 'password', 'bag' => 'brisanje', 'prefix' => 'brisanje-'])
			</div>
			<div class="lgn-field">
				<label class="lgn-check"><input type="checkbox" name="confirm" value="1" required> <span>Razumem da se nalog trajno briše.</span></label>
				@include('accounts.partials.error', ['field' => 'confirm', 'bag' => 'brisanje', 'prefix' => 'brisanje-'])
			</div>
			<button class="lgn-btn lgn-btn--danger" type="submit">Trajno obriši nalog</button>
		</form>
		<p class="lgn-switch"><a href="{{ url_slash('moj-nalog') }}">Odustani</a></p>
	</section>
</div>
@endsection
