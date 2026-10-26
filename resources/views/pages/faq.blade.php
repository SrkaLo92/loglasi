{{-- FAQ, structured like the old site: breadcrumbs, the questions, "Niste pronašli odgovor?" box. --}}
@extends('layouts.app')
@use('App\Support\Phone')
@use('App\Support\Settings')
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\PageText')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/pages.css') }}">
@endpush

@php
	$faqPhone = (string) Settings::get('contact_phone');
	$faqEmail = (string) Settings::get('contact_email');
@endphp

@section('content')
<div class="lg-page lgx">
	<nav class="lgx-path" aria-label="Putanja">
		<ol>
			<li><a href="{{ url_slash('/') }}">Početna</a></li>
			<li><span aria-current="page">{{ PageText::get('faq.naslov') }}</span></li>
		</ol>
	</nav>
	<header class="lg-page__header">
		<h1 class="lg-page__title">{{ PageText::get('faq.naslov') }}</h1>
		<p class="lg-page__intro">{{ PageText::inline((string) PageText::raw('faq.uvod')) }}</p>
	</header>
	<div class="lgx-faq-page">
		<div class="lgx-faq lgx-faq--page">
			@foreach ($items as $item)
				@include('pages.partials.question', ['pitanje' => $item['pitanje'], 'odgovor' => $item['odgovor'], 'otvoreno' => $loop->first])
			@endforeach
		</div>
		<aside class="lgx-faq-help" aria-labelledby="faq-pomoc-naslov">
			<span class="lgx-faq-help__icon">{{ SiteIcons::svg('chat', 26) }}</span>
			<h2 class="lgx-faq-help__title" id="faq-pomoc-naslov">Niste pronašli odgovor?</h2>
			<p>Pišite nam i odgovorićemo vam u najkraćem roku, obično istog dana.</p>
			<a class="lgx-btn lgx-btn--green" href="{{ url_slash('/kontakt') }}#forma">Pošaljite pitanje</a>
			<ul class="lgx-faq-help__contact">
				@if ($faqPhone !== '')
					<li><a href="tel:{{ $faqPhone }}">{{ SiteIcons::svg('phone', 18) }}{{ Phone::display($faqPhone) }}</a></li>
				@endif
				@if ($faqEmail !== '')
					<li><a href="mailto:{{ $faqEmail }}">{{ SiteIcons::svg('mail', 18) }}{{ $faqEmail }}</a></li>
				@endif
			</ul>
		</aside>
	</div>
</div>
@endsection
