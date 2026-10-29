@extends('layouts.app')
@use('App\Support\Pages\Company')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/pages.css') }}">
@endpush

@section('content')
<div class="lg-page lgx">
	<header class="lg-page__header">
		<h1 class="lg-page__title">{{ $heading }}</h1>
	</header>
	@if (in_array($page, ['uslovi', 'privatnost'], true) && Company::name() !== '')
		@include('pages.partials.impresum', ['impresumHeading' => $page === 'uslovi' ? 'Pružalac usluge' : 'Rukovalac podacima'])
	@endif
	<article class="lgx-text lgx-text--{{ $page }}">
		{{ $body }}
	</article>
	@if ($page === 'odustanak')
		@include('pages.partials.withdrawal-form')
	@endif
</div>
@endsection
