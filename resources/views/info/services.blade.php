@extends('info.layout', ['section' => 'servisne-informacije', 'title' => 'Servisne informacije za Loznicu', 'description' => 'Isključenja struje, prekidi vode, dežurna apoteka i vreme u Loznici i okolini, svakog dana na jednom mestu.', 'canonical' => \App\Support\Info\InfoUrl::to('servisne-informacije'), 'noindex' => $noindex])
@use('App\Support\Info\InfoUrl')
@section('content')
@php($svDays = ['Danas', 'Sutra', 'Prekosutra'])
<div class="inf-wrap">
	<div class="inf-pagehead">
		<div>
			<h1>Servisne informacije</h1>
			<p>Struja, voda, dežurna apoteka i vreme za Loznicu i okolinu. Struja i vreme se osvežavaju sami, više puta dnevno.</p>
		</div>
	</div>

	<section class="inf-svtop" aria-label="Danas">
		<div class="inf-panel" id="vreme">
			<span class="inf-panel__label">Vreme u Loznici</span>
			@if ($weather)
				<span class="inf-weather"><svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#b8501a" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><b>{{ $weather['temp'] }}°</b><span>{{ ucfirst($weather['label']) }}</span></span>
				<div class="inf-wdays">
					<span><strong>Danas</strong>{{ $weather['today_min'] }}° / {{ $weather['today_max'] }}°</span>
					<span><strong>Sutra</strong>{{ $weather['tomorrow'] }}</span>
				</div>
			@else
				<p class="inf-meta">Prognoza se trenutno osvežava.</p>
			@endif
		</div>
		<div class="inf-panel inf-panel--green" id="apoteka">
			<span class="inf-panel__label">Dežurna apoteka</span>
			@if ($pharmacy)
				<strong>{{ $pharmacy->name }}</strong>
				<p>{{ $pharmacy->address }}@if ($pharmacy->hours)<br>{{ $pharmacy->hours }}@endif</p>
				@if ($pharmacy->phone)<a class="inf-btn inf-btn--white" href="tel:{{ preg_replace('/[^0-9+]/', '', $pharmacy->phone) }}">Pozovi apoteku</a>@endif
			@else
				<strong>Raspored nije unet</strong>
				<p>Hitna pomoć: 194</p>
			@endif
		</div>
		<div class="inf-panel">
			<span class="inf-panel__label">Važni telefoni</span>
			<a class="inf-kv" href="tel:194"><span>Hitna pomoć</span><b>194</b></a>
			<a class="inf-kv" href="tel:192"><span>Policija</span><b>192</b></a>
			<a class="inf-kv" href="tel:193"><span>Vatrogasci</span><b>193</b></a>
			<a class="inf-kv" href="tel:1987"><span>Pomoć na putu (AMSS)</span><b>1987</b></a>
		</div>
	</section>

	<section class="inf-svsec" id="struja" aria-labelledby="inf-struja">
		<div class="inf-svhead">
			<h2 id="inf-struja">Planirana isključenja struje</h2>
			<nav class="inf-seg" aria-label="Dan">
				@foreach ($svDays as $i => $label)
					<a href="{{ InfoUrl::to('servisne-informacije') }}{{ $i ? '?dan='.$i : '' }}#struja" @if ($day === $i) aria-current="true" @endif>{{ $label }}@if ($powerCounts[$i] > 0) <small>{{ $powerCounts[$i] }}</small>@endif</a>
				@endforeach
			</nav>
		</div>
		<div class="inf-table">
			@forelse ($powerDay as $n)
				<div class="inf-trow">
					<strong>{{ $n->place ?: $n->municipality }}</strong>
					<span>{{ $n->area }}</span>
					<b>{{ $n->starts_at->timezone('Europe/Belgrade')->format('H:i') }}@if ($n->ends_at)–{{ $n->ends_at->timezone('Europe/Belgrade')->format('H:i') }}@endif</b>
				</div>
			@empty
				<p class="inf-trow--ok">{{ $svDays[$day] }} nema najavljenih isključenja u Loznici i okolini.</p>
			@endforelse
		</div>
		<p class="inf-src">Izvor: Elektrodistribucija Srbije, planirana isključenja. Proverava se tri puta dnevno.</p>
	</section>

	<section class="inf-svsec" id="voda" aria-labelledby="inf-voda">
		<div class="inf-svhead"><h2 id="inf-voda">Voda</h2></div>
		@if ($water->isNotEmpty())
			<div class="inf-table">
				@foreach ($water as $n)
					<div class="inf-trow"><strong>{{ $n->place ?: 'Loznica' }}</strong><span>{{ $n->area }}</span><b>{{ $n->whenLabel() }}</b></div>
				@endforeach
			</div>
		@else
			<div class="inf-note"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2b7a64" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/></svg><p><strong>Nema najavljenih prekida vode.</strong> <span>Obaveštenja Vodovoda objavljujemo čim stignu.</span></p></div>
		@endif
	</section>

	@if ($roads->isNotEmpty())
		<section class="inf-svsec" aria-labelledby="inf-putevi">
			<div class="inf-svhead"><h2 id="inf-putevi">Putevi i radovi</h2></div>
			<div class="inf-table">
				@foreach ($roads as $n)
					<div class="inf-trow"><strong>{{ $n->place ?: 'Loznica' }}</strong><span>{{ $n->area }}</span><b>{{ $n->whenLabel() }}</b></div>
				@endforeach
			</div>
		</section>
	@endif

	@if ($sponsor)
		<p class="inf-sponsor">Servisne informacije uz podršku <a href="{{ $sponsor->clickUrl() }}" rel="sponsored noopener">{{ $sponsor->title }}</a></p>
	@endif
	<a class="inf-link" href="{{ InfoUrl::to('posalji-vest') }}">Primetili ste kvar ili radove? Javite nam</a>
</div>
@endsection
