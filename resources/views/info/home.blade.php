@extends('info.layout', ['section' => ''])
@use('App\Support\Info\InfoUrl')
@use('Illuminate\Support\Str')
@php
	$jsonld = [[
		'@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'loglasi Info', 'url' => InfoUrl::to('/'),
		'inLanguage' => 'sr-Latn', 'publisher' => ['@type' => 'Organization', 'name' => 'loglasi.rs', 'url' => InfoUrl::main('/')],
		'potentialAction' => ['@type' => 'SearchAction', 'target' => InfoUrl::to('pretraga').'?q={q}', 'query-input' => 'required name=q'],
	]];
	$hmTime = fn ($post) => $post->published_at?->isToday() ? $post->published_at->timezone('Europe/Belgrade')->format('H:i') : $post->published_at?->timezone('Europe/Belgrade')->format('j. n.');
@endphp
@section('content')
<div class="inf-wrap inf-stack">
	<h1 class="inf-visually-hidden">loglasi Info: vesti, dešavanja i servisne informacije za Loznicu i okolinu</h1>
	@include('info.partials.today')

	@if ($featured)
		<section aria-label="Vesti" class="inf-front">
			<div class="inf-front__main">
				<article class="inf-hero">
					<a class="inf-media" href="{{ $featured->url() }}" tabindex="-1" aria-hidden="true"><img src="{{ $featured->imageUrl() }}" alt="" width="640" height="480" fetchpriority="high"></a>
					<div class="inf-hero__body">
						<div class="inf-metaline">
							<span class="inf-pill">{{ $featured->topicLabel() ?? $featured->typeLabel() }}</span>
							<span>{{ $featured->published_at?->diffForHumans() }}@if ($featured->source_name) · izvor: {{ $featured->source_name }}@endif</span>
						</div>
						<h2 class="inf-title-xl"><a href="{{ $featured->url() }}">{{ $featured->title }}</a></h2>
						<p class="inf-lede">{{ Str::limit($featured->summary, 200) }}</p>
					</div>
				</article>
				@if ($cards->isNotEmpty())
					<div class="inf-cards">
						@foreach ($cards as $post)
							<article class="inf-card">
								<a class="inf-media inf-media--card" href="{{ $post->url() }}" tabindex="-1" aria-hidden="true"><img src="{{ $post->imageUrl() }}" alt="" loading="lazy" decoding="async" width="400" height="250"></a>
								<span class="inf-kicker">{{ $post->topicLabel() ?? $post->typeLabel() }}</span>
								<h3 class="inf-title"><a href="{{ $post->url() }}">{{ $post->title }}</a></h3>
								<span class="inf-meta">{{ $post->published_at?->diffForHumans() }}</span>
							</article>
						@endforeach
					</div>
				@endif
			</div>
			<aside class="inf-box" aria-labelledby="inf-najnovije">
				<h2 id="inf-najnovije">Najnovije</h2>
				@forelse ($newest as $post)
					<a class="inf-newest" href="{{ $post->url() }}"><time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $hmTime($post) }}</time><span>{{ $post->title }}</span></a>
				@empty
					<p class="inf-meta">Nove vesti stižu tokom dana.</p>
				@endforelse
				<a class="inf-btn inf-btn--soft" href="{{ InfoUrl::to('vesti') }}">Sve vesti</a>
			</aside>
		</section>
	@else
		<p class="inf-empty">Prve vesti stižu uskoro. Imate vest iz vašeg mesta? <a href="{{ InfoUrl::to('posalji-vest') }}">Pošaljite nam.</a></p>
	@endif

	@if ($homeAd)
		@include('info.partials.ad', ['ad' => $homeAd])
	@endif

	@if ($events->isNotEmpty())
		<section aria-labelledby="inf-vikend">
			<div class="inf-head2"><h2 id="inf-vikend">Uskoro u Loznici</h2><a class="inf-link" href="{{ InfoUrl::to('desavanja') }}">Kalendar dešavanja</a></div>
			<div class="inf-grid4">
				@foreach ($events as $event)
					@include('info.partials.event-card', ['event' => $event])
				@endforeach
			</div>
		</section>
	@endif

	@if ($story)
		<section aria-label="Priča iz kraja" class="inf-story">
			@if ($story->imageUrl())<img src="{{ $story->imageUrl() }}" alt="" loading="lazy" decoding="async">@else<span class="inf-media inf-media--empty" aria-hidden="true">Priče iz kraja</span>@endif
			<div class="inf-story__body">
				<span class="inf-story__kicker">Priče iz kraja</span>
				<h2><a href="{{ $story->url() }}">{{ $story->title }}</a></h2>
				<p>{{ Str::limit($story->summary, 200) }}</p>
				<a class="inf-underline" href="{{ $story->url() }}">Pročitajte priču</a>
			</div>
		</section>
	@endif

	<section aria-labelledby="inf-firme">
		<div class="inf-head2">
			<h2 id="inf-firme">Firme iz kraja</h2>
			<span class="inf-meta">Majstori, servisi, prodavnice i usluge iz Loznice i okoline</span>
			<a class="inf-link" href="{{ InfoUrl::to('firme') }}">Ceo imenik</a>
		</div>
		@if ($categories->isNotEmpty())
			<div class="inf-chips inf-chips--gap">
				@foreach ($categories as $cat)
					<a class="inf-chip" href="{{ InfoUrl::to('firme') }}?kategorija={{ rawurlencode($cat) }}">{{ $cat }}</a>
				@endforeach
			</div>
		@endif
		@if ($businesses->isNotEmpty())
			<div class="inf-grid3">
				@foreach ($businesses as $b)
					@include('info.partials.firm-card', ['b' => $b, 'photo' => true])
				@endforeach
			</div>
		@endif
		<a class="inf-cta-line" href="{{ InfoUrl::to('oglasavanje') }}">
			<strong>Imate firmu u Loznici ili okolini?</strong>
			<span>Osnovni upis u imenik je besplatan.</span>
			<b>Dodajte svoju firmu</b>
		</a>
	</section>

	@include('info.partials.listings', ['listings' => $listings])
</div>
@endsection
