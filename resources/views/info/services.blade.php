@extends('info.layout', ['section' => 'servisne-informacije', 'title' => 'Servisne informacije za Loznicu', 'description' => 'Isključenja struje, prekidi vode, dežurna apoteka i vreme u Loznici i okolini, svakog dana na jednom mestu.', 'canonical' => \App\Support\Info\InfoUrl::to('servisne-informacije')])
@use('App\Support\Info\InfoUrl')
@section('content')
<div class="inf-band">
	<div class="inf-wrap">
		<h1>Servisne informacije</h1>
		<p>{{ ucfirst(now()->translatedFormat('l, j. F')) }} · Loznica i okolina</p>
	</div>
</div>
<div class="inf-wrap inf-stack inf-narrow">
	<section id="vreme" class="inf-card inf-weather" aria-label="Vreme">
		<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
		@if ($weather)
			<div><p class="inf-weather__temp">{{ $weather['temp'] }}°</p><p class="inf-meta">{{ ucfirst($weather['label']) }} · danas {{ $weather['today_min'] }}° / {{ $weather['today_max'] }}° · sutra {{ $weather['tomorrow'] }}</p></div>
		@else
			<p class="inf-meta">Prognoza se trenutno osvežava.</p>
		@endif
	</section>

	<section id="struja" class="inf-card inf-card--flush" aria-labelledby="h-struja">
		<h2 id="h-struja" class="inf-card__h"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#9a4215" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>Isključenja struje</h2>
		@forelse ($power as $n)
			<div class="inf-notice"><strong>{{ $n->whenLabel() }}@if ($n->place) · {{ $n->place }}@endif</strong><span>{{ $n->area }}</span></div>
		@empty
			<div class="inf-notice"><strong>Nema najavljenih isključenja</strong><span class="inf-meta">Proveravamo tri puta dnevno.</span></div>
		@endforelse
		<p class="inf-src">Izvor: Elektrodistribucija Srbije, planirana isključenja</p>
	</section>

	<section id="voda" class="inf-card inf-card--flush" aria-labelledby="h-voda">
		<h2 id="h-voda" class="inf-card__h"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2b7a64" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/></svg>Voda</h2>
		@forelse ($water as $n)
			<div class="inf-notice"><strong>{{ $n->whenLabel() }}@if ($n->place) · {{ $n->place }}@endif</strong><span>{{ $n->area }}</span></div>
		@empty
			<div class="inf-notice"><strong>Nema najavljenih prekida</strong>@if ($lastWater)<span class="inf-meta">Poslednje obaveštenje: {{ $lastWater->starts_at->format('j. n.') }}@if ($lastWater->place), {{ $lastWater->place }}@endif</span>@endif</div>
		@endforelse
		<p class="inf-src">Izvor: JKP Vodovod Loznica</p>
	</section>

	<section id="apoteka" class="inf-card inf-card--flush" aria-labelledby="h-apoteka">
		<h2 id="h-apoteka" class="inf-card__h"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2b7a64" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/></svg>Dežurna apoteka</h2>
		@if ($pharmacy)
			<div class="inf-notice"><strong>{{ $pharmacy->name }}@if ($pharmacy->hours) · {{ $pharmacy->hours }}@endif</strong><span>{{ $pharmacy->address }}</span></div>
			<div class="inf-actions">
				@if ($pharmacy->phone)<a class="inf-btn inf-btn--soft" href="tel:{{ preg_replace('/[^0-9+]/', '', $pharmacy->phone) }}">Pozovi</a>@endif
				<a class="inf-btn inf-btn--soft" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($pharmacy->name.' '.$pharmacy->address.' Loznica') }}" rel="noopener" target="_blank">Mapa</a>
			</div>
		@else
			<div class="inf-notice"><strong>Raspored za danas još nije unet</strong><span class="inf-meta">Hitna pomoć: 194</span></div>
		@endif
	</section>

	@if ($roads->isNotEmpty())
		<section class="inf-card inf-card--flush" aria-labelledby="h-putevi">
			<h2 id="h-putevi" class="inf-card__h">Putevi i radovi</h2>
			@foreach ($roads as $n)
				<div class="inf-notice"><strong>{{ $n->whenLabel() }}@if ($n->place) · {{ $n->place }}@endif</strong><span>{{ $n->area }}</span></div>
			@endforeach
		</section>
	@endif

	<section class="inf-card inf-stack-sm" aria-labelledby="h-tel">
		<h2 id="h-tel" class="inf-card__title">Važni telefoni</h2>
		<ul class="inf-phones">
			<li><span>Policija</span><a href="tel:192">192</a></li>
			<li><span>Vatrogasci</span><a href="tel:193">193</a></li>
			<li><span>Hitna pomoć</span><a href="tel:194">194</a></li>
			<li><span>Jedinstveni broj za hitne slučajeve</span><a href="tel:112">112</a></li>
			<li><span>Pomoć na putu (AMSS)</span><a href="tel:1987">1987</a></li>
		</ul>
	</section>
</div>
@endsection
