@extends('info.layout', ['section' => 'firme', 'title' => 'Firme iz Loznice i okoline', 'description' => 'Imenik firmi, radnji i majstora iz Loznice, Banje Koviljače, Lešnice i okoline: adrese, telefoni i ponuda.', 'canonical' => \App\Support\Info\InfoUrl::to('firme'), 'noindex' => $noindex])
@use('App\Support\Info\InfoUrl')
@section('content')
<section class="inf-dirhero">
	<div class="inf-wrap inf-dirhero__in">
		<h1>Firme iz Loznice i okoline</h1>
		<p>Majstori, servisi, prodavnice i usluge iz kraja, sa telefonom i adresom na jednom mestu.</p>
		<form class="inf-dirsearch" role="search" method="get" action="{{ InfoUrl::to('firme') }}">
			<label class="inf-visually-hidden" for="inf-fq">Šta tražite</label>
			<input id="inf-fq" type="search" name="q" value="{{ $q }}" placeholder="Vulkanizer, pekara, stolar…" maxlength="80">
			<label class="inf-visually-hidden" for="inf-fk">Delatnost</label>
			<select id="inf-fk" name="kategorija" data-pick>
				<option value="">Sve delatnosti</option>
				@foreach ($categories as $name => $count)<option value="{{ $name }}" @selected($category === $name)>{{ $name }}</option>@endforeach
			</select>
			@if ($municipalities->count() > 1)
				<label class="inf-visually-hidden" for="inf-fo">Opština</label>
				<select id="inf-fo" name="opstina" data-pick>
					<option value="">Sve opštine</option>
					@foreach ($municipalities as $name => $count)<option value="{{ $name }}" @selected($municipality === $name)>{{ $name }}</option>@endforeach
				</select>
			@endif
			<button class="inf-btn inf-btn--warm" type="submit">Traži</button>
		</form>
	</div>
</section>
<div class="inf-wrap inf-dir">
	<aside class="inf-dir__side" aria-labelledby="inf-kat">
		<h2 id="inf-kat">Delatnosti</h2>
		<nav class="inf-cats">
			<a href="{{ InfoUrl::to('firme') }}" @if ($category === '' && $q === '') aria-current="true" @endif>Sve firme <span>{{ $total }}</span></a>
			@foreach ($categories as $name => $count)
				<a href="{{ InfoUrl::to('firme') }}?kategorija={{ rawurlencode($name) }}@if ($municipality !== '')&amp;opstina={{ rawurlencode($municipality) }}@endif" @if ($category === $name) aria-current="true" @endif>{{ $name }} <span>{{ $count }}</span></a>
			@endforeach
		</nav>
		<a class="inf-addfirm" href="{{ InfoUrl::to('oglasavanje') }}">
			<strong>Vaše firme nema na spisku?</strong>
			<span>Upis je besplatan. Za prvo mesto u delatnosti pogledajte Istaknutu firmu.</span>
			<b>Dodajte firmu</b>
		</a>
	</aside>
	<div class="inf-dir__main">
		<div class="inf-head2"><h2 class="inf-h-sm">{{ $q !== '' ? 'Rezultati za „'.$q.'“' : ($category !== '' ? $category : 'Sve firme') }}</h2><span class="inf-meta">{{ $businesses->total() }} {{ $businesses->total() % 10 === 1 && $businesses->total() % 100 !== 11 ? 'firma' : 'firmi' }}</span></div>
		@if ($categoryAd)
			@include('info.partials.ad', ['ad' => $categoryAd, 'slim' => true])
		@endif
		@if ($businesses->isEmpty())
			<p class="inf-empty">Nema firmi za ovu pretragu.</p>
		@else
			<div class="inf-dirlist">
				@foreach ($businesses as $b)
					@include('info.partials.firm-card', ['b' => $b])
				@endforeach
			</div>
			@if ($businesses->hasPages())
				<nav class="inf-pager" aria-label="Strane">
					@if ($businesses->previousPageUrl())<a href="{{ $businesses->previousPageUrl() }}">Prethodna</a>@endif
					<span aria-current="page">{{ $businesses->currentPage() }} / {{ $businesses->lastPage() }}</span>
					@if ($businesses->nextPageUrl())<a href="{{ $businesses->nextPageUrl() }}">Sledeća</a>@endif
				</nav>
			@endif
		@endif
	</div>
</div>
@endsection
