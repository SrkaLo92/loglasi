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
	$crumbSection = ['vesti' => 'Vesti', 'desavanja' => 'Dešavanja', 'price' => 'Priče iz kraja', 'vodici' => 'Vodiči'][$post->section()];
@endphp
@extends('info.layout', ['section' => $post->section(), 'title' => $post->title, 'description' => \Illuminate\Support\Str::limit($post->summary, 155), 'canonical' => $post->url(), 'noindex' => $noindex, 'ogType' => 'article', 'ogImage' => $post->imageUrl(), 'jsonld' => [$schema]])
@section('content')
@php
	$pcEmail = \App\Support\Settings::get('contact_email', 'kontakt@loglasi.rs');
	$pcParagraphs = preg_split("/\n\s*\n/", trim($post->summary));
	$pcLede = $post->body ? array_shift($pcParagraphs) : null;
@endphp
<div class="inf-wrap inf-stack">
	<div class="inf-article">
		<article class="inf-article__main">
			<nav class="inf-crumbs" aria-label="Putanja"><a href="{{ InfoUrl::to('/') }}">Naslovna</a><span aria-hidden="true">/</span><a href="{{ InfoUrl::to($post->section()) }}">{{ $crumbSection }}</a>@if ($post->topicLabel())<span aria-hidden="true">/</span><span>{{ $post->topicLabel() }}</span>@endif</nav>
			@if ($post->sponsored)<span class="inf-tag inf-tag--warm">Promo{{ $post->sponsor_name ? ' · '.$post->sponsor_name : '' }}</span>@endif
			<h1>{{ $post->title }}</h1>
			@if ($pcLede)<p class="inf-article__lede">{{ $pcLede }}</p>@endif
			<div class="inf-byline">
				<b>{{ $post->origin === 'citizen' && $post->submitter_name ? 'Poslao čitalac' : 'Redakcija loglasi Info' }}</b>
				<span>{{ $post->published_at?->timezone('Europe/Belgrade')->translatedFormat('j. F Y, H:i') }}</span>
				<span class="inf-share">
					<a href="viber://forward?text={{ rawurlencode($post->title.' '.$post->url()) }}" aria-label="Podeli na Viberu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.6A8.5 8.5 0 1 1 21 11.5z"/></svg></a>
					<a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($post->url()) }}" rel="noopener" target="_blank" aria-label="Podeli na Facebooku"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h-2a4 4 0 0 0-4 4v3H7v4h2v7h4v-7h2.5l.5-4h-3V7a1 1 0 0 1 1-1h2z"/></svg></a>
				</span>
			</div>

			@if ($isEvent)
				<div class="inf-eventbox">
					<b>Kada i gde</b>
					<span>{{ ucfirst($post->event_starts_at?->timezone('Europe/Belgrade')->translatedFormat('l, j. F Y') ?? '') }}@if ($post->event_starts_at && $post->event_starts_at->timezone('Europe/Belgrade')->format('H:i') !== '00:00'), {{ $post->event_starts_at->timezone('Europe/Belgrade')->format('H:i') }}@endif</span>
					<span>{{ $post->event_venue ?: 'Mesto će biti objavljeno' }}@if ($post->place), {{ $post->place }}@endif</span>
				</div>
			@endif

			@if ($post->imageUrl())
				<figure class="inf-figure"><img src="{{ $post->imageUrl() }}" alt="" width="960" height="540">@if (! $post->hasOwnImage())<figcaption>{{ 'Ilustracija'.($post->themeImageCredit() ? ', foto: '.$post->themeImageCredit() : '') }}</figcaption>@elseif ($post->image_credit)<figcaption>Foto: {{ $post->image_credit }}</figcaption>@endif</figure>
			@endif

			<div class="inf-prose">
				@foreach ($pcParagraphs as $paragraph)<p>{{ $paragraph }}</p>@endforeach
				@if ($post->body)
					@foreach (preg_split("/\n\s*\n/", trim($post->body)) as $paragraph)<p>{{ $paragraph }}</p>@endforeach
				@endif
			</div>

			@if ($post->source_url)
				<div class="inf-source">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7M21 3l-9 9M19 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/></svg>
					<span>Izvor: <b>{{ $post->source_name ?: parse_url($post->source_url, PHP_URL_HOST) }}</b></span>
					<a href="{{ $post->source_url }}" rel="noopener" target="_blank">Ceo tekst na izvoru</a>
				</div>
			@endif
			@if ($post->sponsored && $post->sponsor_url)
				<a class="inf-btn inf-btn--warm" href="{{ $post->sponsor_url }}" rel="sponsored noopener">Pogledajte ponudu</a>
			@endif

			@if ($ad)
				@include('info.partials.ad', ['ad' => $ad, 'slim' => true])
			@endif

			<p class="inf-meta">Uočili ste grešku? Pišite nam na <a class="inf-link" href="mailto:{{ $pcEmail }}?subject={{ rawurlencode('Ispravka: '.$post->title) }}">{{ $pcEmail }}</a>.</p>
		</article>

		<aside class="inf-side" aria-label="Bočna kolona">
			<div class="inf-side__box">
				<h2>Danas u Loznici</h2>
				<a class="inf-kv" href="{{ InfoUrl::to('servisne-informacije') }}#vreme"><span>Vreme</span><b>{{ $today['weather'] ? $today['weather']['temp'].'°, '.$today['weather']['label'] : 'uskoro' }}</b></a>
				<a class="inf-kv" href="{{ InfoUrl::to('servisne-informacije') }}#struja"><span>Struja</span><b @class(['inf-warm' => $today['power_today'] > 0])>{{ $today['power_today'] > 0 ? \App\Support\Info\Words::outages($today['power_today']) : 'bez isključenja' }}</b></a>
				<a class="inf-kv" href="{{ InfoUrl::to('servisne-informacije') }}#apoteka"><span>Apoteka</span><b>{{ $today['pharmacy']['name'] ?? 'raspored' }}</b></a>
			</div>
			@if ($newest->isNotEmpty())
				<div class="inf-side__box">
					<h2>Najnovije</h2>
					@foreach ($newest as $n)
						<a class="inf-numbered" href="{{ $n->url() }}"><b>{{ $loop->iteration }}</b><span>{{ $n->title }}</span></a>
					@endforeach
				</div>
			@endif
			@if ($nextEvent)
				<a class="inf-nextev" href="{{ $nextEvent->url() }}">
					<small>Sledeće dešavanje</small>
					<strong>{{ $nextEvent->title }}</strong>
					<span>{{ ucfirst($nextEvent->event_starts_at?->timezone('Europe/Belgrade')->translatedFormat('l j. F, H:i') ?? '') }}@if ($nextEvent->event_venue), {{ $nextEvent->event_venue }}@endif</span>
				</a>
			@endif
		</aside>
	</div>

	@if ($more->isNotEmpty())
		<section aria-labelledby="inf-jos">
			<div class="inf-head2"><h2 id="inf-jos">{{ $isEvent ? 'Još dešavanja' : 'Još iz Loznice' }}</h2></div>
			<div class="inf-grid3">
				@foreach ($more as $m)
					<article class="inf-card">
						<a class="inf-media inf-media--card @if (! $m->imageUrl()) inf-media--empty @endif" href="{{ $m->url() }}" tabindex="-1" aria-hidden="true">@if ($m->imageUrl())<img src="{{ $m->imageUrl() }}" alt="" loading="lazy" decoding="async">@else {{ $m->topicLabel() ?? 'Vesti' }} @endif</a>
						<span class="inf-kicker">{{ $m->topicLabel() ?? $m->typeLabel() }}</span>
						<h3 class="inf-title"><a href="{{ $m->url() }}">{{ $m->title }}</a></h3>
					</article>
				@endforeach
			</div>
		</section>
	@endif
</div>
@endsection
