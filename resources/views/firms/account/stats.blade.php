@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/firms.css') }}">
@endpush
@php
	$title = 'Statistika firme | loglasi.rs';
	$robots = 'noindex, follow';
	$metrics = [
		'views' => ['Pregledi oglasa', 'pregleda'],
		'phone_reveals' => ['Otkriveni telefoni', 'otkrivanja'],
		'messages' => ['Poruke', 'poruka'],
		'favorites' => ['Dodato u omiljene', 'puta'],
	];
	$num = fn ($n) => number_format((int) $n, 0, ',', '.');
@endphp

@section('content')
<div class="lgf lgf--account lg-wrap">
	<h1>Statistika firme</h1>
	<p class="lgf-lead">{{ $firm->name ?: $user->name }} <span class="lgf-badge lgf-badge--firm">Firma</span></p>

	@include('firms.partials.account-nav', ['active' => 'stats'])

	@if (! $allowed)
		<div class="lgf-alert lgf-alert--err" role="status"><p>Statistika je deo paketa za firme. Podaci se i dalje beleže i biće vidljivi čim firma ponovo ima paket.</p></div>
		@include('firms.partials.package-status', ['status' => $status])
	@else
		@if (count($periods) > 1)
		<nav class="lgf-periods" aria-label="Period">
			@foreach ($periods as $p)
				<a class="lg-btn {{ $p === $period ? 'lg-btn--green' : 'lg-btn--outline' }}" href="{{ url_slash('moj-nalog/firma/statistika') }}{{ $p === (int) config('firms.stats.default_period', 30) ? '' : '?period='.$p }}" @if ($p === $period) aria-current="page" @endif>Poslednjih {{ $p }} dana</a>
			@endforeach
		</nav>
		@else
			<p class="lgf-help">Poslednjih {{ $period }} dana.</p>
		@endif

		<ul class="lgf-tiles">
			@foreach ($metrics as $key => [$label])
				<li class="lgf-tile"><span class="lgf-tile__label">{{ $label }}</span><span class="lgf-tile__value">{{ $num($stats['totals'][$key]) }}</span></li>
			@endforeach
		</ul>

		<div class="lgf-charts">
			@foreach ($metrics as $key => [$label, $unit])
				@include('firms.partials.chart', [
					'id' => 'grafik-'.$key,
					'label' => $label,
					'unit' => $unit,
					'points' => array_map(fn ($d) => ['date' => $d['date'], 'value' => $d[$key]], $stats['daily']),
				])
			@endforeach
		</div>

		<section class="lgf-card" aria-labelledby="po-oglasu">
			<h2 id="po-oglasu">Po oglasu</h2>
			@if (! $advanced)
				<p class="lgf-muted">Statistika po oglasu i za 90 dana je deo paketa Business Pro i Business Max. <a href="{{ url_slash('cenovnik') }}#lgc-firme">Paketi i cene</a></p>
			@elseif ($listings === [])
				<p class="lgf-muted">U ovom periodu oglasi firme još nemaju preglede.</p>
			@else
				<div class="lgf-table-wrap">
					<table class="lgf-table">
						<thead>
							<tr><th scope="col">Oglas</th><th scope="col">Pregledi</th><th scope="col">Telefon</th><th scope="col">Poruke</th><th scope="col">Omiljeno</th></tr>
						</thead>
						<tbody>
							@foreach ($listings as $row)
								<tr>
									<td>
										@if (! $row['deleted'] && $row['status'] === \App\Enums\ListingStatus::Published->value)
											<a href="{{ url_slash('oglas/'.$row['slug']) }}">{{ $row['title'] }}</a>
										@else
											{{ $row['title'] }} <span class="lgf-muted">({{ $row['deleted'] ? 'obrisan' : (\App\Enums\ListingStatus::tryFrom($row['status'])?->label() ?? $row['status']) }})</span>
										@endif
									</td>
									<td>{{ $num($row['views']) }}</td>
									<td>{{ $num($row['phone_reveals']) }}</td>
									<td>{{ $num($row['messages']) }}</td>
									<td>{{ $num($row['favorites']) }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@if (count($listings) >= (int) config('firms.stats.top_listings', 50))
					<p class="lgf-help">Prikazano je {{ count($listings) }} oglasa sa najviše pregleda.</p>
				@endif
			@endif

			<details class="lgf-details">
				<summary>Tabela po danima</summary>
				<div class="lgf-table-wrap">
					<table class="lgf-table">
						<thead>
							<tr><th scope="col">Dan</th><th scope="col">Pregledi</th><th scope="col">Telefon</th><th scope="col">Poruke</th><th scope="col">Omiljeno</th></tr>
						</thead>
						<tbody>
							@foreach (array_reverse($stats['daily']) as $day)
								<tr>
									<td>{{ \Carbon\CarbonImmutable::parse($day['date'])->format('j. n. Y.') }}</td>
									<td>{{ $num($day['views']) }}</td>
									<td>{{ $num($day['phone_reveals']) }}</td>
									<td>{{ $num($day['messages']) }}</td>
									<td>{{ $num($day['favorites']) }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</details>
		</section>
	@endif
</div>
@endsection
