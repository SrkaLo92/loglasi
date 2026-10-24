@extends('layouts.app')
@include('messages.partials.head')
@php
	$title = 'Omiljeni oglasi | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgm lg-wrap">
	<h1>Omiljeni oglasi</h1>
	@include('messages.partials.status')

	@if ($items->isEmpty())
		<div class="lgm-empty">
			@if ($isFirstPage)
				<p>Još niste sačuvali nijedan oglas. Kliknite na srce na oglasu da ga sačuvate ovde.</p>
				<a class="lg-btn lg-btn--green" href="{{ url_slash('oglasi') }}">Pogledajte oglase</a>
			@else
				<p>Nema više sačuvanih oglasa.</p>
			@endif
		</div>
	@else
		<ul class="lgm-favs">
			@foreach ($items as $item)
				<li class="lgm-fav{{ $item['state'] ? ' lgm-fav--expired' : '' }}">
					@if ($item['state'])
						<p class="lgm-fav__state"><span class="lgm-badge">{{ $item['state'] }}</span></p>
					@endif
					<x-listing-card :listing="$item['listing']" heading="h2" />
				</li>
			@endforeach
		</ul>

		<nav class="lgm-pager" aria-label="Stranice omiljenih">
			@unless ($isFirstPage)
				<a class="lg-btn lg-btn--outline" href="{{ url_slash('moj-nalog/omiljeni') }}">Na početak</a>
			@endunless
			@if ($next)
				<a class="lg-btn lg-btn--outline" href="{{ url_slash('moj-nalog/omiljeni') }}?cursor={{ urlencode($next) }}" rel="next">Starije sačuvano</a>
			@endif
		</nav>
	@endif
</div>
@endsection
