@extends('info.layout', ['section' => 'desavanja', 'title' => 'Dešavanja u Loznici', 'description' => 'Koncerti, predstave, sport, sajmovi i događaji u Loznici, Banji Koviljači i okolnim mestima.', 'canonical' => \App\Support\Info\InfoUrl::to('desavanja'), 'noindex' => $noindex])
@use('App\Support\Info\InfoUrl')
@section('content')
@php($evWhen = fn ($e) => $e->event_starts_at?->timezone('Europe/Belgrade'))
<div class="inf-wrap">
	<div class="inf-pagehead">
		<div>
			<h1>Dešavanja</h1>
			<p>Koncerti, sajmovi, utakmice i radionice u Loznici i okolini.</p>
		</div>
		<a class="inf-btn inf-btn--warm" href="{{ InfoUrl::to('posalji-vest') }}">Prijavite događaj, besplatno</a>
	</div>

	@if ($featured && ! $topic)
		@php($fw = $evWhen($featured))
		<article class="inf-evfeat">
			@if ($featured->hasOwnImage())<img src="{{ $featured->imageUrl() }}" alt="" width="720" height="480">@else<span class="inf-media inf-media--empty" aria-hidden="true">Dešavanja</span>@endif
			<div class="inf-evfeat__body">
				<span class="inf-evfeat__kicker">Izdvajamo</span>
				<h2><a href="{{ $featured->url() }}">{{ $featured->title }}</a></h2>
				<p>{{ ucfirst($fw?->translatedFormat('l j. F') ?? '') }}@if ($fw && $fw->format('H:i') !== '00:00') u {{ $fw->format('H:i') }}@endif<br>{{ trim(($featured->event_venue ?: '').($featured->place ? ', '.$featured->place : ''), ', ') }}</p>
				<a class="inf-btn inf-btn--white" href="{{ $featured->url() }}">Detalji</a>
			</div>
		</article>
	@endif

	@if ($topics->isNotEmpty())
		<nav class="inf-chips" aria-label="Vrsta">
			<a class="inf-chip" href="{{ InfoUrl::to('desavanja') }}" @if (! $topic) aria-current="true" @endif>Sve</a>
			@foreach ($topics as $key => $label)
				<a class="inf-chip" href="{{ InfoUrl::to('desavanja') }}?tema={{ $key }}" @if ($topic === $key) aria-current="true" @endif>{{ $label }}</a>
			@endforeach
		</nav>
	@endif

	@if ($events->isEmpty())
		<p class="inf-empty inf-empty--gap">Kalendar se tek puni. Organizujete događaj? <a href="{{ InfoUrl::to('posalji-vest') }}">Dodajte ga besplatno.</a></p>
	@else
		<section class="inf-evlist" aria-label="Spisak dešavanja">
			@foreach ($events as $e)
				@php($w = $evWhen($e))
				<div class="inf-evrow">
					<span @class(['inf-date', 'inf-date--warm' => (bool) $w?->isSunday()])><small>{{ $w?->translatedFormat('D') }}</small><b>{{ $w?->format('j') }}</b><small>{{ $w?->translatedFormat('M') }}</small></span>
					<span class="inf-evrow__text">
						<span class="inf-kicker">{{ $e->sponsored ? 'Promo' : ($e->topicLabel() ?? 'Događaj') }}</span>
						<strong><a href="{{ $e->url() }}">{{ $e->title }}</a></strong>
						<span class="inf-meta">{{ trim(($w && $w->format('H:i') !== '00:00' ? $w->format('H:i') : 'ceo dan').', '.($e->event_venue ?: '').($e->place ? ', '.$e->place : ''), ', ') }}</span>
					</span>
					<a class="inf-btn inf-btn--line" href="{{ $e->url() }}">Detalji</a>
				</div>
			@endforeach
		</section>
	@endif
</div>
@endsection
