{{-- "Danas u Loznici": one compact strip with today's service info (power, water, roads, pharmacy, weather).
     $today from InfoToday::get(). Each item links to its part of the service page. --}}
@use('App\Support\Info\InfoAds')
@use('App\Support\Info\InfoUrl')
@use('App\Support\Info\Words')
@php
	$tdSponsor = InfoAds::for('today_sponsor');
	$tdServices = InfoUrl::to('servisne-informacije');
	$tdRoads = ($today['roads_now'] ?? 0) > 0;
	$tdItems = [
		['struja', 'Struja', $today['power_today'] > 0 || $today['power_next'],
			$today['power_today'] > 0 ? 'Danas: '.Words::outages($today['power_today']) : ($today['power_next'] ? 'Isključenje: '.$today['power_next'] : 'Bez isključenja'),
			'<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>'],
		['voda', 'Voda', $today['water_today'] > 0, $today['water_today'] > 0 ? 'Prekid danas' : 'Bez prekida',
			'<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>'],
		['putevi', 'Putevi', $tdRoads, $tdRoads ? 'Radovi danas' : 'Bez radova',
			'<path d="M4 21 9 3M20 21 15 3M12 6v2M12 11v2M12 16v2"/>'],
		['apoteka', 'Apoteka', false, $today['pharmacy']['name'] ?? 'Ko dežura danas',
			'<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/>'],
		['vreme', 'Vreme', false, $today['weather'] ? $today['weather']['temp'].'°, '.$today['weather']['label'] : 'Prognoza uskoro',
			'<path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0z"/>'],
	];
@endphp
<section class="inf-svc" aria-labelledby="inf-danas">
	<h2 id="inf-danas" class="inf-svc__label">Danas<span>&nbsp;u Loznici</span></h2>
	<div class="inf-svc__items">
		@foreach ($tdItems as [$tdId, $tdName, $tdWarn, $tdText, $tdIcon])
			<a @class(['inf-svc__item', 'inf-svc__item--warn' => $tdWarn]) href="{{ $tdServices }}#{{ $tdId }}">
				<svg class="inf-svc__ico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $tdIcon !!}</svg>
				<span><strong>{{ $tdName }}</strong><small>{{ $tdText }}</small></span>
			</a>
		@endforeach
	</div>
	@if ($tdSponsor)
		<p class="inf-svc__sponsor">Uz podršku <a href="{{ $tdSponsor->clickUrl() }}" rel="sponsored noopener">{{ $tdSponsor->title }}</a></p>
	@endif
</section>
