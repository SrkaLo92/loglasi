@extends('layouts.app')
@include('messages.partials.head')
@php
	$title = 'Omiljeni oglasi | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgm lg-wrap">
	@include('messages.partials.tabs', ['active' => 'favorites'])
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
				@php($listing = $item['listing'])
				<li class="lgm-fav{{ $item['expired'] ? ' lgm-fav--expired' : '' }}">
					<a class="lgm-fav__link" href="{{ $listing->url() }}">
						<span class="lgm-fav__img" aria-hidden="true">
							@if ($item['thumb'])<img src="{{ $item['thumb'] }}" alt="" width="320" height="240" loading="lazy">@endif
						</span>
						<span class="lgm-fav__title">{{ $listing->title }}</span>
					</a>
					<span class="lgm-fav__price">{{ $listing->priceLabel() }}</span>
					<span class="lgm-fav__meta">
						@if ($listing->location){{ $listing->location->name }}@endif
						@if ($item['expired'])<span class="lgm-badge">Istekao</span>@endif
					</span>
					<form method="post" action="{{ url_slash('moj-nalog/omiljeni/'.$listing->id) }}">
						@csrf
						@method('DELETE')
						<button class="lgm-link" type="submit" aria-label="Ukloni iz omiljenih: {{ $listing->title }}">Ukloni</button>
					</form>
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
