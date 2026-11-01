{{-- "Danas u Loznici" strip: weather, pharmacy on duty, power and water today. $today from InfoToday::get(). --}}
@use('App\Support\Info\InfoUrl')
<section class="inf-today" aria-label="Danas u Loznici">
	<a class="inf-today__item" href="{{ InfoUrl::to('servisne-informacije') }}#vreme">
		<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
		<span><span class="inf-meta">Vreme u Loznici</span><strong>{{ $today['weather'] ? $today['weather']['temp'].'° · '.$today['weather']['label'] : 'Prognoza uskoro' }}</strong></span>
	</a>
	<a class="inf-today__item" href="{{ InfoUrl::to('servisne-informacije') }}#apoteka">
		<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/></svg>
		<span><span class="inf-meta">Dežurna apoteka</span><strong>{{ $today['pharmacy'] ? $today['pharmacy']['name'] : 'Pogledajte raspored' }}</strong></span>
	</a>
	<a @class(['inf-today__item', 'inf-today__item--warn' => $today['power_today'] > 0]) href="{{ InfoUrl::to('servisne-informacije') }}#struja">
		<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>
		<span><span class="inf-meta">Struja danas</span><strong>{{ $today['power_today'] > 0 ? \App\Support\Info\Words::outages($today['power_today']) : 'Bez najavljenih isključenja' }}</strong></span>
	</a>
	<a class="inf-today__item" href="{{ InfoUrl::to('servisne-informacije') }}#voda">
		<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/></svg>
		<span><span class="inf-meta">Voda</span><strong>{{ $today['water_today'] > 0 ? 'Najavljen prekid danas' : 'Bez najavljenih prekida' }}</strong></span>
	</a>
</section>
