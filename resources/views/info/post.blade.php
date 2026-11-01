@php
	use App\Support\Info\InfoUrl;
	/** @var \App\Models\InfoPost $post */
	$isEvent = $post->type === 'event';
	$schema = $isEvent ? array_filter([
		'@context' => 'https://schema.org', '@type' => 'Event', 'name' => $post->title, 'description' => $post->summary,
		'startDate' => $post->event_starts_at?->toIso8601String(), 'endDate' => $post->event_ends_at?->toIso8601String(),
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode', 'eventStatus' => 'https://schema.org/EventScheduled',
		'location' => ['@type' => 'Place', 'name' => $post->event_venue ?: ($post->place ?: 'Loznica'), 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $post->place ?: 'Loznica', 'addressCountry' => 'RS']],
		'image' => $post->imageUrl(), 'url' => $post->url(),
	]) : array_filter([
		'@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $post->title, 'description' => $post->summary,
		'datePublished' => $post->published_at?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(),
		'image' => $post->imageUrl(), 'mainEntityOfPage' => $post->url(), 'inLanguage' => 'sr-Latn',
		'publisher' => ['@type' => 'Organization', 'name' => 'loglasi.rs', 'url' => InfoUrl::main('/')],
	]);
	$crumbSection = ['vesti' => 'Vesti', 'desavanja' => 'Dešavanja', 'price' => 'Priče iz Loznice', 'vodici' => 'Vodiči'][$post->section()];
@endphp
@extends('info.layout', ['section' => $post->section(), 'title' => $post->title, 'description' => \Illuminate\Support\Str::limit($post->summary, 155), 'canonical' => $post->url(), 'noindex' => $noindex, 'ogType' => 'article', 'ogImage' => $post->imageUrl(), 'jsonld' => [$schema]])
@section('content')
<div class="inf-wrap inf-article-wrap">
	<article @class(['inf-article', 'inf-article--story' => $post->type === 'story'])>
		<nav class="inf-crumbs inf-meta" aria-label="Putanja"><a href="{{ InfoUrl::to('/') }}">Info</a> › <a href="{{ InfoUrl::to($post->section()) }}">{{ $crumbSection }}</a>@if ($post->topicLabel()) › {{ $post->topicLabel() }}@endif</nav>
		@if ($post->sponsored)<p class="inf-label inf-label--warm">Sponzorisano{{ $post->sponsor_name ? ' · '.$post->sponsor_name : '' }}</p>@endif
		<h1>{{ $post->title }}</h1>
		<p class="inf-meta">{{ $post->published_at?->translatedFormat('j. F Y, H:i') }} · loglasi Info</p>

		@if ($isEvent)
			<dl class="inf-facts">
				<div><dt>Kada</dt><dd>{{ $post->event_starts_at?->translatedFormat('l, j. F Y') }}@if ($post->event_starts_at && $post->event_starts_at->format('H:i') !== '00:00'), {{ $post->event_starts_at->format('H:i') }}@endif @if ($post->event_ends_at) – {{ $post->event_ends_at->isSameDay($post->event_starts_at) ? $post->event_ends_at->format('H:i') : $post->event_ends_at->translatedFormat('j. F, H:i') }}@endif</dd></div>
				<div><dt>Gde</dt><dd>{{ $post->event_venue ?: '—' }}@if ($post->place), {{ $post->place }}@endif</dd></div>
			</dl>
		@endif

		@if ($post->imageUrl())
			<figure class="inf-figure"><img src="{{ $post->imageUrl() }}" alt="" width="800" height="500">@if ($post->image_credit)<figcaption class="inf-meta">Foto: {{ $post->image_credit }}</figcaption>@endif</figure>
		@endif

		<div class="inf-text">
			@foreach (preg_split("/\n\s*\n/", trim($post->summary)) as $paragraph)
				<p @class(['inf-lead' => $loop->first && $post->body])>{{ $paragraph }}</p>
			@endforeach
			@if ($post->body)
				@foreach (preg_split("/\n\s*\n/", trim($post->body)) as $paragraph)
					<p>{{ $paragraph }}</p>
				@endforeach
			@endif
		</div>

		@if ($post->source_url)
			<a class="inf-source" href="{{ $post->source_url }}" rel="noopener" target="_blank">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg>
				<span><span class="inf-meta">Izvor</span><strong>Pročitajte celu vest na {{ $post->source_name ?: parse_url($post->source_url, PHP_URL_HOST) }}</strong></span>
			</a>
		@endif
		@if ($post->sponsored && $post->sponsor_url)
			<a class="inf-btn inf-btn--warm" href="{{ $post->sponsor_url }}" rel="sponsored noopener">Pogledajte ponudu</a>
		@endif

		<p class="inf-meta inf-fix">Uočili ste grešku? Pišite nam na <a href="mailto:{{ \App\Support\Settings::get('contact_email', 'kontakt@loglasi.rs') }}?subject={{ rawurlencode('Ispravka: '.$post->title) }}">{{ \App\Support\Settings::get('contact_email', 'kontakt@loglasi.rs') }}</a>.</p>
	</article>

	<aside class="inf-stack">
		@include('info.partials.listings', ['listings' => $listings, 'heading' => 'Novi oglasi iz Loznice i okoline'])
		@if ($more->isNotEmpty())
			<section class="inf-block">
				<div class="inf-block__head"><h2>{{ $isEvent ? 'Još dešavanja' : 'Još iz Loznice' }}</h2></div>
				<div class="inf-list">@foreach ($more as $m) @include('info.partials.post-row', ['post' => $m]) @endforeach</div>
			</section>
		@endif
	</aside>
</div>
@endsection
