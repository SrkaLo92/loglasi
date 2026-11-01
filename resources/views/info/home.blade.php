@extends('info.layout', ['section' => ''])
@use('App\Support\Info\InfoUrl')
@php
	$jsonld = [[
		'@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'loglasi Info', 'url' => InfoUrl::to('/'),
		'inLanguage' => 'sr-Latn', 'publisher' => ['@type' => 'Organization', 'name' => 'loglasi.rs', 'url' => InfoUrl::main('/')],
	]];
@endphp
@section('content')
<div class="inf-wrap inf-stack">
	<h1 class="inf-visually-hidden">loglasi Info: vesti, dešavanja i servisne informacije za Loznicu i okolinu</h1>
	@include('info.partials.today')

	<div class="inf-cols">
		<section class="inf-cols__main inf-stack" aria-label="Najnovije">
			@if ($featured)
				<a class="inf-hero" href="{{ $featured->url() }}">
					<span class="inf-hero__media">@if ($featured->imageUrl())<img src="{{ $featured->imageUrl() }}" alt="" width="640" height="420" fetchpriority="high">@endif</span>
					<span class="inf-hero__body">
						<span class="inf-label">{{ $featured->typeLabel() }}@if ($featured->place) · {{ $featured->place }}@endif</span>
						<strong class="inf-hero__title">{{ $featured->title }}</strong>
						<span class="inf-hero__text">{{ \Illuminate\Support\Str::limit($featured->summary, 180) }}</span>
						<span class="inf-meta">{{ $featured->published_at?->diffForHumans() }} · {{ $featured->source_name ?: 'loglasi Info' }}</span>
					</span>
				</a>
			@endif

			<div class="inf-block__head"><h2>Najnovije iz Loznice i okoline</h2><a class="inf-more" href="{{ InfoUrl::to('vesti') }}">Sve vesti <span aria-hidden="true">→</span></a></div>
			@if ($latest->isEmpty() && ! $featured)
				<p class="inf-empty">Prve vesti stižu uskoro. Imate vest iz vašeg mesta? <a href="{{ InfoUrl::to('posalji-vest') }}">Pošaljite nam.</a></p>
			@else
				<div class="inf-list">
					@foreach ($latest as $post)
						@include('info.partials.post-row', ['post' => $post])
					@endforeach
				</div>
			@endif
		</section>

		<aside class="inf-cols__side inf-stack" aria-label="Uskoro u Loznici">
			<div class="inf-card inf-stack-sm">
				<h2 class="inf-card__title">Uskoro u Loznici</h2>
				@forelse ($events as $event)
					<a class="inf-event" href="{{ $event->url() }}">
						<span @class(['inf-date', 'inf-date--warm' => $event->sponsored])><span>{{ mb_strtoupper($event->event_starts_at?->translatedFormat('D') ?? '') }}</span><strong>{{ $event->event_starts_at?->format('j') }}</strong></span>
						<span><strong>{{ $event->title }}</strong><span class="inf-meta">{{ $event->sponsored ? 'Sponzorisano · '.$event->sponsor_name : trim($event->event_starts_at?->format('H:i').' · '.($event->event_venue ?: $event->place), ' ·') }}</span></span>
					</a>
				@empty
					<p class="inf-meta">Kalendar se puni. Organizujete nešto? <a href="{{ InfoUrl::to('posalji-vest') }}">Dodajte događaj.</a></p>
				@endforelse
				<a class="inf-more" href="{{ InfoUrl::to('desavanja') }}">Ceo kalendar <span aria-hidden="true">→</span></a>
			</div>

			<div class="inf-card inf-card--green inf-stack-sm">
				<h2 class="inf-card__title">Imate vest ili događaj?</h2>
				<p>Pošaljite nam, objavljujemo posle pregleda. Besplatno za građane i firme iz Loznice.</p>
				<a class="inf-btn inf-btn--light" href="{{ InfoUrl::to('posalji-vest') }}">Pošalji vest</a>
			</div>

			<div class="inf-card inf-stack-sm">
				<h2 class="inf-card__title">Firme iz Loznice</h2>
				@foreach ($businesses as $b)
					<a class="inf-firm-mini" href="{{ $b->url() }}"><strong>{{ $b->name }}</strong><span class="inf-meta">{{ $b->category }}</span></a>
				@endforeach
				<a class="inf-more" href="{{ InfoUrl::to('firme') }}">{{ $businessCount > 0 ? 'Sve firme ('.$businessCount.')' : 'Imenik firmi' }} <span aria-hidden="true">→</span></a>
			</div>
		</aside>
	</div>

	@include('info.partials.listings', ['listings' => $listings])
</div>
@endsection
