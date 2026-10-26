@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/firms.css') }}">
@endpush
@php
	$title = 'Uvoz oglasa | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgf lgf--account lg-wrap">
	<h1>Uvoz oglasa iz tabele</h1>
	<p class="lgf-lead">{{ $firm->name ?: $user->name }} <span class="lgf-badge lgf-badge--firm">Firma</span></p>

	@include('firms.partials.account-nav', ['active' => 'import'])

	@if ($error)
		<div class="lgf-alert lgf-alert--err" role="alert"><p>{{ $error }}</p></div>
	@endif

	<section class="lgf-card">
		<h2>Kako izgleda tabela</h2>
		<p>Prvi red su nazivi kolona, a svaki sledeći red je jedan oglas.</p>
		<ul>
			<li><strong>Obavezno:</strong> {{ implode(', ', $help['required']) }}</li>
			<li><strong>Po potrebi:</strong> {{ implode(', ', $help['optional']) }}</li>
			<li>Kolona <strong>slike</strong> prima adrese slika, razdvojene razmakom ili zarezom.</li>
			<li>Prazna cena znači „Dogovor”. Valuta može biti EUR ili RSD.</li>
			<li>Dodatna polja zavise od kategorije, na primer: {{ implode(', ', array_slice($help['fields'], 0, 12)) }}.</li>
		</ul>
		<p>Prihvatamo .csv i .xlsx, do 20 MB i najviše 5.000 redova.</p>
	</section>

	<form class="lgf-form" method="post" action="{{ url_slash('moj-nalog/firma/uvoz-oglasa/pregled') }}" enctype="multipart/form-data">
		@csrf
		<div class="lgf-field">
			<label for="uvoz-tabela">Tabela sa oglasima</label>
			<input id="uvoz-tabela" type="file" name="tabela" accept=".csv,.xlsx,.xlsm,text/csv" required>
			@error('tabela')<p class="lgf-err">{{ $message }}</p>@enderror
		</div>
		<label class="lgf-check"><input type="checkbox" name="images" value="1" checked> Preuzmi slike sa adresa iz kolone „slike”</label>
		<div class="lgf-actions">
			<button class="lg-btn lg-btn--ghost" type="submit">Pregledaj tabelu</button>
			<button class="lg-btn" type="submit" formaction="{{ url_slash('moj-nalog/firma/uvoz-oglasa') }}">Uvezi oglase</button>
		</div>
		<p class="lgf-help">Pregled ništa ne upisuje. Uvoz upisuje oglase odmah, a redovi sa greškom se preskaču.</p>
	</form>

	@if ($preview)
		<section class="lgf-card">
			<h2>Pregled pre uvoza</h2>
			@if ($preview['unknown'])
				<p class="lgf-err">Ove kolone ne prepoznajemo i preskačemo ih: {{ implode(', ', $preview['unknown']) }}.</p>
			@endif
			<table class="lgf-table lgf-table--uvoz">
				<thead><tr><th>Red</th><th>Naslov</th><th>Kategorija</th><th>Status</th></tr></thead>
				<tbody>
				@foreach ($preview['rows'] as $row)
					<tr>
						<td>{{ $row['line'] }}</td>
						<td>{{ $row['title'] ?: '—' }}</td>
						<td>{{ $row['category'] ?? '—' }}</td>
						<td>{{ $row['errors'] ? implode(' ', $row['errors']) : 'Spremno' }}</td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</section>
	@endif

	@if ($report)
		<section class="lgf-card">
			<h2>Izveštaj</h2>
			<p>Uvezeno oglasa: <strong>{{ $report['created'] }}</strong>, preskočeno: <strong>{{ $report['failed'] }}</strong>.</p>
			@if ($report['failed'] > 0)
				<table class="lgf-table lgf-table--uvoz">
					<thead><tr><th>Red</th><th>Naslov</th><th>Greška</th></tr></thead>
					<tbody>
					@foreach ($report['rows'] as $row)
						@if ($row['errors'])
							<tr><td>{{ $row['line'] }}</td><td>{{ $row['title'] ?: '—' }}</td><td>{{ implode(' ', $row['errors']) }}</td></tr>
						@endif
					@endforeach
					</tbody>
				</table>
			@endif
			<p><a href="{{ url_slash('moj-nalog/oglasi') }}">Moji oglasi</a></p>
		</section>
	@endif
</div>
@endsection
