@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@section('content')
<div class="lg-strana lgx">
	<header class="lg-strana__zaglavlje">
		<h1 class="lg-strana__naslov">{{ $heading }}</h1>
	</header>
	<article class="lgx-tekst lgx-tekst--{{ $page }}">
		{{ $body }}
	</article>
	@if ($page === 'odustanak')
		@include('pages.partials.withdrawal-form')
	@endif
</div>
@endsection
