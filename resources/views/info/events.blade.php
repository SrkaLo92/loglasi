@extends('info.layout', ['section' => 'desavanja', 'title' => 'Dešavanja u Loznici', 'description' => 'Koncerti, predstave, sport, sajmovi i događaji u Loznici, Banji Koviljači i okolnim mestima.', 'canonical' => \App\Support\Info\InfoUrl::to('desavanja'), 'noindex' => $noindex])
@use('App\Support\Info\InfoUrl')
@use('Illuminate\Support\Carbon')
@section('content')
<div class="inf-band">
	<div class="inf-wrap">
		<h1>Dešavanja u Loznici</h1>
		<p>Koncerti, sport, sajmovi i događaji u Loznici, Banji Koviljači i okolnim mestima</p>
	</div>
</div>
<div class="inf-wrap inf-stack inf-narrow">
	@forelse ($days as $date => $events)
		@php($day = Carbon::parse($date))
		<h2 class="inf-day">{{ $day->isToday() ? 'Danas' : ($day->isTomorrow() ? 'Sutra' : '') }}{{ $day->isToday() || $day->isTomorrow() ? ', ' : '' }}{{ $day->translatedFormat('l, j. F') }}</h2>
		<div class="inf-card inf-card--flush">
			@foreach ($events as $event)
				<a @class(['inf-ev', 'inf-ev--warm' => $event->sponsored]) href="{{ $event->url() }}">
					<span class="inf-ev__time">{{ $event->event_starts_at && $event->event_starts_at->format('H:i') !== '00:00' ? $event->event_starts_at->format('H:i') : 'ceo dan' }}</span>
					<span><strong>{{ $event->title }}</strong><span class="inf-meta">{{ $event->sponsored ? 'Sponzorisano · '.$event->sponsor_name : trim(($event->event_venue ?: '').($event->place ? ' · '.$event->place : ''), ' ·') }}</span></span>
				</a>
			@endforeach
		</div>
	@empty
		<p class="inf-empty">Kalendar se tek puni.</p>
	@endforelse
	<a class="inf-card inf-cta" href="{{ InfoUrl::to('posalji-vest') }}">
		<strong>Organizujete događaj?</strong>
		<span class="inf-meta">Dodajte ga besplatno, objavljujemo posle pregleda →</span>
	</a>
</div>
@endsection
