{{-- "Danas u Loznici": five tiles (weather, power, water, pharmacy, next event). $today from InfoToday::get(). --}}
@use('App\Support\Info\InfoAds')
@use('App\Support\Info\InfoUrl')
@use('App\Support\Info\Words')
@php
	$tdSponsor = InfoAds::for('today_sponsor');
	$tdServices = InfoUrl::to('servisne-informacije');
@endphp
<section aria-labelledby="inf-danas">
	<div class="inf-head2">
		<h2 id="inf-danas" class="inf-h-sm">Danas u Loznici</h2>
		<a class="inf-link" href="{{ $tdServices }}">Sve servisne informacije</a>
	</div>
	<div class="inf-today">
		<a class="inf-tile" href="{{ $tdServices }}#vreme">
			<span class="inf-tile__label">Vreme</span>
			@if ($today['weather'])
				<span class="inf-tile__big"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#b8501a" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>{{ $today['weather']['temp'] }}°</span>
				<span class="inf-tile__sub">{{ ucfirst($today['weather']['label']) }}, do {{ $today['weather']['today_max'] }}°</span>
			@else
				<strong>Prognoza uskoro</strong>
			@endif
		</a>
		<a @class(['inf-tile', 'inf-tile--warn' => $today['power_today'] > 0 || $today['power_next']]) href="{{ $tdServices }}#struja">
			<span class="inf-tile__label">Struja</span>
			@if ($today['power_today'] > 0)
				<strong>Danas {{ Words::outages($today['power_today']) }}</strong>
				<span class="inf-tile__sub">Pogledajte ulice i vreme</span>
			@elseif ($today['power_next'])
				<strong>Najavljeno isključenje</strong>
				<span class="inf-tile__sub">{{ $today['power_next'] }}</span>
			@else
				<strong>Bez najavljenih isključenja</strong>
				<span class="inf-tile__sub">Proveravamo 3 puta dnevno</span>
			@endif
		</a>
		<a class="inf-tile" href="{{ $tdServices }}#voda">
			<span class="inf-tile__label">Voda</span>
			<strong>{{ $today['water_today'] > 0 ? 'Najavljen prekid danas' : 'Nema najavljenih prekida' }}</strong>
			<span class="inf-tile__sub">Vodovod Loznica</span>
		</a>
		<a class="inf-tile" href="{{ $tdServices }}#apoteka">
			<span class="inf-tile__label">Dežurna apoteka</span>
			<strong>{{ $today['pharmacy']['name'] ?? 'Pogledajte raspored' }}</strong>
			@if ($today['pharmacy'])<span class="inf-tile__sub">{{ trim(($today['pharmacy']['address'] ?? '').($today['pharmacy']['hours'] ? ', '.$today['pharmacy']['hours'] : ''), ', ') }}</span>@endif
		</a>
		@if ($today['next_event'])
			<a class="inf-tile" href="{{ $today['next_event']['url'] }}">
				<span class="inf-tile__label">Sledeći događaj</span>
				<strong>{{ $today['next_event']['title'] }}</strong>
				<span class="inf-tile__sub">{{ $today['next_event']['when'] }}</span>
			</a>
		@else
			<a class="inf-tile" href="{{ InfoUrl::to('desavanja') }}">
				<span class="inf-tile__label">Dešavanja</span>
				<strong>Kalendar dešavanja</strong>
				<span class="inf-tile__sub">Dodajte svoj događaj</span>
			</a>
		@endif
	</div>
	@if ($tdSponsor)
		<p class="inf-sponsor">Uz podršku <a href="{{ $tdSponsor->clickUrl() }}" rel="sponsored noopener">{{ $tdSponsor->title }}</a></p>
	@endif
</section>
