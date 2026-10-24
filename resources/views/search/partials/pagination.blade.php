{{-- Pages: /page/N/ links (WordPress style), at most up to ListingSearch::MAX_OFFSET. --}}
@php
	$pgLast = min($results->lastPage(), intdiv(\App\Services\ListingSearch::MAX_OFFSET, max(1, $results->perPage())) + 1);
	$pgCurrent = $results->currentPage();
	$pgPages = collect([1, $pgLast])->merge(range(max(1, $pgCurrent - 2), min($pgLast, $pgCurrent + 2)))->unique()->sort()->values();
@endphp
@if ($pgLast > 1)
	<nav class="sr-pages" aria-label="Stranice">
		@if ($pgCurrent > 1)
			<a class="sr-pages__step" href="{{ $filters->url(null, $pgCurrent - 1) }}" rel="prev">Prethodna</a>
		@endif
		@foreach ($pgPages as $i => $n)
			@if ($i > 0 && $n - $pgPages[$i - 1] > 1)
				<span class="sr-pages__gap" aria-hidden="true">…</span>
			@endif
			@if ($n === $pgCurrent)
				<span class="sr-pages__n is-current" aria-current="page"><span class="lg-visually-hidden">Strana </span>{{ $n }}</span>
			@else
				<a class="sr-pages__n" href="{{ $filters->url(null, $n) }}"><span class="lg-visually-hidden">Strana </span>{{ $n }}</a>
			@endif
		@endforeach
		@if ($pgCurrent < $pgLast)
			<a class="sr-pages__step" href="{{ $filters->url(null, $pgCurrent + 1) }}" rel="next">Sledeća</a>
		@endif
	</nav>
@endif
