{{-- FAQ, structured like the old site: breadcrumbs, the questions, "Niste pronašli odgovor?" box. --}}
@extends('layouts.app')
@use('App\Support\Phone')
@use('App\Support\Settings')
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/stranice.css') }}">
@endpush

@php
	$faqPhone = (string) Settings::get('contact_phone');
	$faqEmail = (string) Settings::get('contact_email');
@endphp

@section('content')
<div class="lg-strana lgx">
	<nav class="lgx-putanja" aria-label="Putanja">
		<ol>
			<li><a href="{{ url_slash('/') }}">Početna</a></li>
			<li><span aria-current="page">{{ PageText::get('faq.naslov') }}</span></li>
		</ol>
	</nav>
	<header class="lg-strana__zaglavlje">
		<h1 class="lg-strana__naslov">{{ PageText::get('faq.naslov') }}</h1>
		<p class="lg-strana__uvod">{{ PageText::inline((string) PageText::raw('faq.uvod')) }}</p>
	</header>
	<div class="lgx-faq-strana">
		<div class="lgx-faq lgx-faq--strana">
			@foreach ($items as $item)
				@include('stranice.partials.pitanje', ['pitanje' => $item['pitanje'], 'odgovor' => $item['odgovor'], 'otvoreno' => $loop->first])
			@endforeach
		</div>
		<aside class="lgx-faq-pomoc" aria-labelledby="faq-pomoc-naslov">
			<span class="lgx-faq-pomoc__ikona">{{ Ikonice::svg('chat', 26) }}</span>
			<h2 class="lgx-faq-pomoc__naslov" id="faq-pomoc-naslov">Niste pronašli odgovor?</h2>
			<p>Pišite nam i odgovorićemo vam u najkraćem roku, obično istog dana.</p>
			<a class="lgx-btn lgx-btn--green" href="{{ url_slash('/kontakt') }}#forma">Pošaljite pitanje</a>
			<ul class="lgx-faq-pomoc__kontakt">
				@if ($faqPhone !== '')
					<li><a href="tel:{{ $faqPhone }}">{{ Ikonice::svg('phone', 18) }}{{ Phone::display($faqPhone) }}</a></li>
				@endif
				@if ($faqEmail !== '')
					<li><a href="mailto:{{ $faqEmail }}">{{ Ikonice::svg('mail', 18) }}{{ $faqEmail }}</a></li>
				@endif
			</ul>
		</aside>
	</div>
</div>
@endsection
