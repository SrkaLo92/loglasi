@extends('info.layout', ['section' => ''])
@use('App\Support\Info\InfoUrl')
@use('Illuminate\Support\Str')
@php
	$jsonld = [[
		'@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'loglasi Info', 'url' => InfoUrl::to('/'),
		'inLanguage' => 'sr-Latn', 'publisher' => ['@type' => 'Organization', 'name' => 'loglasi.rs', 'url' => InfoUrl::main('/')],
		'potentialAction' => ['@type' => 'SearchAction', 'target' => InfoUrl::to('pretraga').'?q={q}', 'query-input' => 'required name=q'],
	]];
	$hmTime = fn ($post) => ($at = $post->published_at?->copy()->timezone('Europe/Belgrade')) ? ($at->isSameDay(now('Europe/Belgrade')) ? $at->format('H:i') : $at->format('j. n.')) : '';
@endphp
@section('content')
<div class="inf-wrap inf-stack">
	<h1 class="inf-visually-hidden">loglasi Info: vesti, dešavanja i servisne informacije za Loznicu i okolinu</h1>
	@include('info.partials.today')

	<form class="inf-findfirm" method="get" action="{{ InfoUrl::to('firme') }}" role="search">
		<label for="inf-ff"><strong>Tražite majstora, servis ili prodavnicu?</strong></label>
		<div class="inf-findfirm__row">
			<input id="inf-ff" type="search" name="q" placeholder="npr. vodoinstalater, auto servis, frizer" maxlength="80">
			<button class="inf-btn" type="submit">Nađi firmu</button>
		</div>
		@if ($categories->isNotEmpty())
			<div class="inf-findfirm__cats">
				@foreach ($categories as $cat)
					<a class="inf-chip" href="{{ InfoUrl::to('firme') }}?kategorija={{ rawurlencode($cat) }}">{{ $cat }}</a>
				@endforeach
			</div>
		@endif
	</form>

	@if ($featured)
		<div class="inf-front">
			<section class="inf-front__main" aria-label="Vesti">
				<article class="inf-hero">
					@include('info.partials.thumb', ['post' => $featured, 'class' => 'inf-hero__media', 'eager' => true])
					<div class="inf-hero__body">
						<div class="inf-metaline">
							<span class="inf-pill">{{ $featured->topicLabel() ?? $featured->typeLabel() }}</span>
							<span>{{ $featured->published_at?->diffForHumans() }}@if ($featured->source_name) · izvor: {{ $featured->source_name }}@endif</span>
						</div>
						<h2 class="inf-title-xl"><a href="{{ $featured->url() }}">{{ $featured->title }}</a></h2>
						<p class="inf-lede">{{ Str::limit($featured->summary, 200) }}</p>
					</div>
				</article>
				<h2 class="inf-visually-hidden">Najnovije vesti</h2>
				<div class="inf-list">
					@foreach ($newest as $post)
						<article class="inf-li">
							@include('info.partials.thumb', ['post' => $post, 'class' => 'inf-li__media'])
							<div>
								<span class="inf-kicker">{{ $post->topicLabel() ?? $post->typeLabel() }}<time datetime="{{ $post->published_at?->toIso8601String() }}"> · {{ $hmTime($post) }}</time></span>
								<h3 class="inf-li__title"><a href="{{ $post->url() }}">{{ $post->title }}</a></h3>
							</div>
						</article>
					@endforeach
				</div>
				<a class="inf-btn inf-btn--soft inf-front__more" href="{{ InfoUrl::to('vesti') }}">Sve vesti</a>
			</section>
			<aside class="inf-front__side" aria-label="Firme i oglasi">
				@if ($homeAd)
					@include('info.partials.ad', ['ad' => $homeAd, 'slim' => true])
				@endif
				@if ($businesses->isNotEmpty())
					<div class="inf-box">
						<h2>Firme iz kraja</h2>
						@foreach ($businesses as $b)
							<a class="inf-sidefirm" href="{{ $b->url() }}">
								<span class="inf-initials" aria-hidden="true">{{ $b->initials() }}</span>
								<span><strong>{{ $b->name }}</strong><small>{{ trim($b->category.' · '.($b->place ?: 'Loznica'), ' ·') }}</small></span>
							</a>
						@endforeach
						<a class="inf-btn inf-btn--soft" href="{{ InfoUrl::to('firme') }}">Ceo imenik</a>
					</div>
				@endif
				<a class="inf-cta-line inf-cta-line--side" href="{{ InfoUrl::to('oglasavanje') }}">
					<strong>Imate firmu u Loznici?</strong>
					<span>Osnovni upis u imenik je besplatan.</span>
					<b>Dodajte svoju firmu</b>
				</a>
			</aside>
		</div>
	@else
		<p class="inf-empty">Prve vesti stižu uskoro. Imate vest iz vašeg mesta? <a href="{{ InfoUrl::to('posalji-vest') }}">Pošaljite nam.</a></p>
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
			@include('info.partials.thumb', ['post' => $story, 'class' => 'inf-story__media'])
			<div class="inf-story__body">
				<span class="inf-story__kicker">Priče iz kraja</span>
				<h2><a href="{{ $story->url() }}">{{ $story->title }}</a></h2>
				<p>{{ Str::limit($story->summary, 200) }}</p>
				<a class="inf-underline" href="{{ $story->url() }}">Pročitajte priču</a>
			</div>
		</section>
	@endif

	@include('info.partials.listings', ['listings' => $listings])
</div>
@endsection
