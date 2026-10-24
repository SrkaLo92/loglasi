@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/firms.css') }}">
@endpush

@php
	$plural = fn (int $n, string $one, string $few, string $many) => $n % 10 === 1 && $n % 100 !== 11 ? $one : (in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true) ? $few : $many);
	$found = $firms->total();
@endphp

@section('content')
<div class="lgf lg-wrap">
	<h1>{{ \App\Support\Seo::get()->getH1() }}</h1>
	<p class="lgf-lead">Lokalne firme sa profilom na loglasi.rs: delatnost, adresa, telefon, radno vreme i svi aktivni oglasi firme na jednom mestu.</p>

	@if ($total > 0)
		<form class="lgf-filter" method="get" action="{{ url_slash('firme') }}" role="search" aria-label="Pretraga firmi">
			<div class="lgf-field">
				<label for="firme-pojam">Naziv ili delatnost</label>
				<input id="firme-pojam" type="search" name="keyword" value="{{ $keyword }}" maxlength="80" placeholder="npr. auto servis, frizer, pekara" autocomplete="off">
			</div>
			<div class="lgf-field">
				<label for="firme-mesto">Mesto</label>
				<select id="firme-mesto" name="mesto">
					<option value="">Sva mesta</option>
					@foreach ($places as $p)
						<option value="{{ $p['slug'] }}" @selected($place === $p['slug'])>{{ $p['name'] }} ({{ $p['count'] }})</option>
					@endforeach
				</select>
			</div>
			<button class="lg-btn lg-btn--green" type="submit">Pretraži</button>
		</form>

		<p class="lgf-count" role="status">
			@if ($filtered)
				{{ $found }} {{ $plural($found, 'firma', 'firme', 'firmi') }}{{ $placeName ? ' u mestu '.$placeName : '' }}{{ $keyword !== '' ? ' za „'.$keyword.'”' : '' }}
				<a href="{{ url_slash('firme') }}">Prikaži sve firme</a>
			@else
				{{ $total }} {{ $plural($total, 'firma', 'firme', 'firmi') }} iz Loznice i okoline
			@endif
		</p>
	@endif

	@if ($found > 0)
		<ul class="lgf-grid">
			@foreach ($firms as $firm)
				<li>
					<article class="lgf-firm">
						<div class="lgf-logo" aria-hidden="true">
							@if ($firm['logo'])
								<img src="{{ $firm['logo'] }}" alt="" width="64" height="64" loading="lazy" decoding="async">
							@else
								<span>{{ mb_strtoupper(mb_substr($firm['name'], 0, 1)) }}</span>
							@endif
						</div>
						<div class="lgf-firm__body">
							<h2 class="lgf-firm__name"><a href="{{ url_slash('korisnik/'.$firm['slug']) }}">{{ $firm['name'] }}</a></h2>
							@if ($firm['activity'])
								<p class="lgf-firm__activity">{{ $firm['activity'] }}</p>
							@endif
							@if ($firm['description'])
								<p class="lgf-firm__text">{{ $firm['description'] }}</p>
							@endif
							<p class="lgf-firm__meta">
								<span>{{ $firm['location'] ?: 'Loznica' }}</span>
								<span>{{ $firm['listings'] }} {{ $plural($firm['listings'], 'aktivan oglas', 'aktivna oglasa', 'aktivnih oglasa') }}</span>
							</p>
							@if ($firm['verified'])
								<p class="lgf-firm__badges"><span class="lgf-badge lgf-badge--ok">Proverena firma</span></p>
							@endif
						</div>
					</article>
				</li>
			@endforeach
		</ul>
		@include('firms.partials.pagination', ['paginator' => $firms, 'base' => url_slash('firme'), 'query' => ['mesto' => $place, 'keyword' => $keyword]])
	@else
		<div class="lgf-card lgf-empty">
			@if ($filtered)
				<p>Nema firmi za ovu pretragu.</p>
				<a class="lg-btn lg-btn--outline" href="{{ url_slash('firme') }}">Prikaži sve firme</a>
			@else
				<p>Imenik firmi se tek puni. Budite među prvim firmama iz Loznice na loglasi.rs.</p>
			@endif
		</div>
	@endif

	<aside class="lgf-cta" aria-labelledby="firme-cta">
		<div>
			<h2 id="firme-cta">Imate firmu u Loznici?</h2>
			<p>Sa paketom za firme dobijate profil sa logom, radnim vremenom i mapom, i mesto u ovom imeniku. Prvih 30 firmi prvu godinu ne plaća ništa.</p>
		</div>
		<a class="lg-btn lg-btn--orange" href="{{ url_slash('za-firme') }}">Prijavite svoju firmu</a>
	</aside>
</div>
@endsection
