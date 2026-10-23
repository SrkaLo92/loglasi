@extends('layouts.app')
@include('messages.partials.head')
@php
	$title = 'Poruka prodavcu: '.$listing->title.' | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgm lgm--chat lg-wrap">
	<p class="lgm-back"><a href="{{ $listing->url() }}">← Nazad na oglas</a></p>
	<h1>Poruka prodavcu</h1>
	<p class="lgm-lead">Pišete korisniku <strong>{{ $listing->user->name }}</strong>@if ($listing->user->isFirm()) <span class="lgm-badge lgm-badge--firm">Firma</span>@endif o oglasu:</p>
	@include('messages.partials.listing', ['listing' => $listing, 'thumb' => $thumb])

	<form class="lgm-form" method="post" action="{{ url_slash('moj-nalog/poruke/novo') }}" novalidate>
		@csrf
		<input type="hidden" name="oglas" value="{{ $listing->id }}">
		<label for="lgm-body">Vaša poruka</label>
		<textarea id="lgm-body" name="body" rows="5" maxlength="{{ config('messages.max_length') }}" required
			placeholder="Zdravo, da li je oglas još aktuelan?"
			@error('body') aria-invalid="true" aria-describedby="lgm-body-error" @enderror>{{ old('body') }}</textarea>
		@error('body')
			<p class="lgm-error" id="lgm-body-error" role="alert">{{ $message }}</p>
		@enderror
		<p class="lgm-help">Do {{ number_format((int) config('messages.max_length'), 0, ',', '.') }} znakova. Prodavac dobija obaveštenje mejlom.</p>
		<button class="lg-btn lg-btn--green" type="submit">Pošalji poruku</button>
	</form>

	<p class="lgm-safety">Ne šaljite novac unapred i ne plaćajte preko linkova koje dobijete porukom. Stvar pogledajte uživo.</p>
</div>
@endsection
