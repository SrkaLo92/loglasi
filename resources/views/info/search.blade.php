@extends('info.layout', ['section' => null, 'title' => $q !== '' ? 'Pretraga: '.$q : 'Pretraga', 'noindex' => true])
@use('App\Support\Info\InfoUrl')
@section('content')
<div class="inf-wrap">
	<div class="inf-pagehead"><div><h1>{{ $q !== '' ? 'Rezultati za „'.$q.'“' : 'Pretraga' }}</h1><p>Vesti, dešavanja i firme iz Loznice i okoline.</p></div></div>
	@if ($q === '')
		<p class="inf-empty">Upišite šta tražite u polje za pretragu iznad.</p>
	@elseif ($posts->isEmpty() && $businesses->isEmpty())
		<p class="inf-empty">Nema rezultata. Probajte drugu reč ili pogledajte <a href="{{ InfoUrl::to('firme') }}">imenik firmi</a>.</p>
	@endif
	@if ($businesses->isNotEmpty())
		<section class="inf-svsec" aria-labelledby="inf-sf">
			<div class="inf-head2"><h2 id="inf-sf" class="inf-h-sm">Firme</h2></div>
			<div class="inf-grid3">@foreach ($businesses as $b) @include('info.partials.firm-card', ['b' => $b]) @endforeach</div>
		</section>
	@endif
	@if ($posts->isNotEmpty())
		<section aria-labelledby="inf-sp">
			<div class="inf-head2"><h2 id="inf-sp" class="inf-h-sm">Vesti i dešavanja</h2></div>
			<div class="inf-rows">@foreach ($posts as $post) @include('info.partials.post-row', ['post' => $post]) @endforeach</div>
		</section>
	@endif
</div>
@endsection
