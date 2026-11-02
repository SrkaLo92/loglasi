{{-- "Danas u Loznici": a dark service panel (power, water, pharmacy, weather) and the next events beside it.
     $today from InfoToday::get(); optional $soon = upcoming events (InfoPost). --}}
@use('App\Support\Info\InfoAds')
@use('App\Support\Info\InfoUrl')
@use('App\Support\Info\Words')
@php
	$tdSponsor = InfoAds::for('today_sponsor');
	$tdServices = InfoUrl::to('servisne-informacije');
	$tdSoon = collect($soon ?? []);
	$tdPowerWarn = $today['power_today'] > 0 || $today['power_next'];
	$tdDate = now('Europe/Belgrade');
@endphp
<section class="inf-svc" aria-labelledby="inf-danas">
	<div class="inf-svc__panel">
		<div class="inf-svc__head">
			<span class="inf-svc__kicker">Danas u Loznici · {{ $tdDate->translatedFormat('l, j. F') }}</span>
			<h2 id="inf-danas" class="inf-svc__title">Servisne informacije za danas</h2>
		</div>
		<div class="inf-svc__tiles">
			<a @class(['inf-svc__tile', 'inf-svc__tile--warn' => $tdPowerWarn]) href="{{ $tdServices }}#struja">
				<svg class="inf-svc__ico" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>
				<strong>Struja</strong>
				@if ($today['power_today'] > 0)
					<span>Danas {{ Words::outages($today['power_today']) }}. Pogledajte ulice.</span>
				@elseif ($today['power_next'])
					<span>Najavljeno isključenje: {{ $today['power_next'] }}</span>
				@else
					<span>Bez najavljenih isključenja</span>
				@endif
			</a>
			<a @class(['inf-svc__tile', 'inf-svc__tile--warn' => $today['water_today'] > 0]) href="{{ $tdServices }}#voda">
				<svg class="inf-svc__ico" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/></svg>
				<strong>Voda</strong>
				<span>{{ $today['water_today'] > 0 ? 'Najavljen prekid danas' : 'Bez najavljenih prekida' }}</span>
			</a>
			<a class="inf-svc__tile" href="{{ $tdServices }}#apoteka">
				<svg class="inf-svc__ico" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/></svg>
				<strong>Dežurna apoteka</strong>
				@if ($today['pharmacy'])
					<span>{{ $today['pharmacy']['name'] }}@if ($today['pharmacy']['address']), {{ $today['pharmacy']['address'] }}@endif</span>
				@else
					<span>Pogledajte raspored dežurstava</span>
				@endif
			</a>
			<a class="inf-svc__tile" href="{{ $tdServices }}#vreme">
				<svg class="inf-svc__ico" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0z"/></svg>
				<strong>Vreme</strong>
				@if ($today['weather'])
					<span><b class="inf-svc__temp">{{ $today['weather']['temp'] }}°</b> {{ ucfirst($today['weather']['label']) }}, do {{ $today['weather']['today_max'] }}°</span>
				@else
					<span>Prognoza stiže uskoro</span>
				@endif
			</a>
		</div>
		@if ($tdSponsor)
			<p class="inf-svc__sponsor">Uz podršku <a href="{{ $tdSponsor->clickUrl() }}" rel="sponsored noopener">{{ $tdSponsor->title }}</a></p>
		@endif
	</div>
	<aside class="inf-svc__soon" aria-labelledby="inf-uskoro">
		<h2 id="inf-uskoro" class="inf-svc__kicker inf-svc__kicker--dark">Uskoro u Loznici</h2>
		@forelse ($tdSoon->take(3) as $ev)
			@php($evAt = $ev->event_starts_at?->timezone('Europe/Belgrade'))
			<a class="inf-svc__ev" href="{{ $ev->url() }}">
				<span class="inf-svc__date"><small>{{ $evAt?->translatedFormat('D') }}</small><b>{{ $evAt?->format('j') }}</b></span>
				<span><strong>{{ $ev->title }}</strong><span class="inf-meta">{{ trim(($ev->event_venue ?: $ev->place).($evAt && $evAt->format('H:i') !== '00:00' ? ' · '.$evAt->format('H:i') : ''), ' ·') }}</span></span>
			</a>
		@empty
			@if ($today['next_event'])
				<a class="inf-svc__ev" href="{{ $today['next_event']['url'] }}"><span><strong>{{ $today['next_event']['title'] }}</strong><span class="inf-meta">{{ $today['next_event']['when'] }}</span></span></a>
			@else
				<p class="inf-meta">Kalendar se puni. Organizujete nešto? Prijavite događaj besplatno.</p>
			@endif
		@endforelse
		<a class="inf-link" href="{{ InfoUrl::to('desavanja') }}">Sva dešavanja →</a>
	</aside>
</section>
