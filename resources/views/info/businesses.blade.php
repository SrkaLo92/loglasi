@extends('info.layout', ['section' => 'firme', 'title' => 'Firme iz Loznice i okoline', 'description' => 'Imenik firmi, radnji i majstora iz Loznice, Banje Koviljače, Lešnice i okoline: adrese, telefoni i ponuda.', 'canonical' => \App\Support\Info\InfoUrl::to('firme'), 'noindex' => $noindex])
@use('App\Support\Info\InfoUrl')
@section('content')
<div class="inf-band">
	<div class="inf-wrap">
		<h1>Firme iz Loznice i okoline</h1>
		<p>Radnje, servisi, majstori i firme, sa adresom i telefonom</p>
	</div>
</div>
<div class="inf-wrap inf-stack">
	<form class="inf-search" method="get" action="{{ InfoUrl::to('firme') }}" role="search">
		<label class="inf-visually-hidden" for="inf-q">Šta tražite?</label>
		<input id="inf-q" type="search" name="q" value="{{ $q }}" placeholder="npr. vulkanizer, frizer, auto plac" maxlength="80" autocomplete="off">
		<button class="inf-btn" type="submit">Traži</button>
	</form>
	@if ($categories->isNotEmpty())
		<nav class="inf-chips inf-chips--wrap" aria-label="Delatnosti">
			<a href="{{ InfoUrl::to('firme') }}" @if ($category === '' && $q === '') aria-current="page" @endif>Sve</a>
			@foreach ($categories as $name => $total)
				<a href="{{ InfoUrl::to('firme') }}?kategorija={{ rawurlencode($name) }}" @if ($category === $name) aria-current="page" @endif>{{ $name }} <span class="inf-meta">{{ $total }}</span></a>
			@endforeach
		</nav>
	@endif

	@if ($businesses->isEmpty())
		<p class="inf-empty">Nema firmi za ovu pretragu.</p>
	@else
		<div class="inf-firms">
			@foreach ($businesses as $b)
				<a @class(['inf-firm', 'inf-firm--featured' => $b->featured]) href="{{ $b->url() }}">
					<strong>{{ $b->name }}</strong>
					@if ($b->category)<span class="inf-label">{{ $b->category }}</span>@endif
					<span class="inf-meta">{{ $b->address ?: $b->place }}</span>
					@if ($b->firm_id)<span class="inf-badge">Ima oglase na loglasi.rs</span>@endif
				</a>
			@endforeach
		</div>
		@if ($businesses->hasPages())
			<nav class="inf-pager" aria-label="Strane">
				@if ($businesses->previousPageUrl())<a href="{{ $businesses->previousPageUrl() }}">← Prethodna</a>@endif
				@if ($businesses->nextPageUrl())<a href="{{ $businesses->nextPageUrl() }}">Sledeća →</a>@endif
			</nav>
		@endif
	@endif

	<div class="inf-card inf-card--green inf-stack-sm">
		<h2 class="inf-card__title">Vaša firma nije na listi?</h2>
		<p>Upis je besplatan. Sa besplatnim nalogom za firme na loglasi.rs dobijate i svoju stranu sa oglasima, logom i radnim vremenom.</p>
		<a class="inf-btn inf-btn--light" href="{{ InfoUrl::main('za-firme') }}">Upišite firmu besplatno</a>
	</div>
</div>
@endsection
