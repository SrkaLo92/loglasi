{{-- Event in the "Ovog vikenda" grid. $event (InfoPost). --}}
@php($ecDate = $event->event_starts_at?->timezone('Europe/Belgrade'))
<a class="inf-event" href="{{ $event->url() }}">
	<span @class(['inf-date', 'inf-date--warm' => (bool) $ecDate?->isSunday()])><small>{{ $ecDate?->translatedFormat('D') }}</small><b>{{ $ecDate?->format('j') }}</b><small>{{ $ecDate?->translatedFormat('M') }}</small></span>
	<span class="inf-event__text"><strong>{{ $event->title }}</strong><span class="inf-meta">{{ trim(($ecDate && $ecDate->format('H:i') !== '00:00' ? $ecDate->format('H:i') : '').', '.($event->event_venue ?: $event->place), ', ') }}</span></span>
</a>
