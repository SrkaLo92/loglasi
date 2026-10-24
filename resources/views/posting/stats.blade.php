@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/posting.css') }}">
@endpush

@section('content')
<div class="pst lg-wrap">
	<header class="pst-head">
		<p class="pst-head__back"><a href="{{ url_slash('moj-nalog/oglasi') }}">Moji oglasi</a></p>
		<h1>{{ \App\Support\Seo::get()->getH1() }}</h1>
		<p class="pst-head__sub">{{ $listing->title }}</p>
	</header>
	<div class="pst-kpis">
		<div class="pst-card pst-kpi"><p class="pst-kpi__value">{{ number_format($listing->views, 0, ',', '.') }}</p><p class="pst-help">pregleda ukupno</p></div>
		<div class="pst-card pst-kpi"><p class="pst-kpi__value">{{ number_format($listing->phone_reveals, 0, ',', '.') }}</p><p class="pst-help">prikaza broja telefona</p></div>
		<div class="pst-card pst-kpi"><p class="pst-kpi__value">{{ number_format($days->sum('views'), 0, ',', '.') }}</p><p class="pst-help">pregleda za 30 dana</p></div>
	</div>
	<section class="pst-card" aria-labelledby="pst-days">
		<h2 id="pst-days">Poslednjih 30 dana</h2>
		@if ($days->isEmpty())
			<p class="pst-help">Još nema podataka po danima.</p>
		@else
			<div class="pst-table-wrap">
				<table class="pst-table">
					<thead><tr><th scope="col">Dan</th><th scope="col">Pregledi</th><th scope="col">Broj telefona</th><th scope="col">Poruke</th><th scope="col">Sačuvali</th></tr></thead>
					<tbody>
						@foreach ($days as $day)
							<tr><th scope="row">{{ \Illuminate\Support\Carbon::parse($day->date)->format('j. n.') }}</th><td>{{ $day->views }}</td><td>{{ $day->phone_reveals }}</td><td>{{ $day->messages }}</td><td>{{ $day->favorites }}</td></tr>
						@endforeach
					</tbody>
				</table>
			</div>
		@endif
	</section>
</div>
@endsection
