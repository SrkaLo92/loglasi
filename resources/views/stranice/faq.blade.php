@extends('layouts.app')
@use('App\Support\Stranice\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@section('content')
<div class="lg-strana lgx">
	<header class="lg-strana__zaglavlje">
		<h1 class="lg-strana__naslov">{{ PageText::get('faq.naslov') }}</h1>
		<p class="lg-strana__uvod">{{ PageText::inline((string) PageText::raw('faq.uvod')) }}</p>
	</header>
	<div class="lgx-faq lgx-faq--strana">
		@foreach ($items as $item)
			@include('stranice.partials.pitanje', ['pitanje' => $item['pitanje'], 'odgovor' => $item['odgovor']])
		@endforeach
	</div>
</div>
@endsection
