{{-- Page links with trailing-slash URLs (?page=N), current page marked with aria-current. --}}
@if ($paginator->hasPages())
	@php
		$current = $paginator->currentPage();
		$last = $paginator->lastPage();
		$pages = collect([1, $current - 1, $current, $current + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
	@endphp
	<nav class="lgp-pager" aria-label="Stranice oglasa">
		<ul class="lgp-pager__list">
			@if ($current > 1)
				<li><a class="lgp-pager__link" href="{{ $paginator->url($current - 1) }}" rel="prev">‹<span class="lgp-sr"> Prethodna strana</span></a></li>
			@endif
			@foreach ($pages as $i => $page)
				@if ($i > 0 && $page - $pages[$i - 1] > 1)
					<li class="lgp-pager__gap" aria-hidden="true">…</li>
				@endif
				<li>
					<a class="lgp-pager__link" href="{{ $page === 1 ? $paginator->path().(($q = http_build_query(request()->except('page'))) ? '?'.$q : '') : $paginator->url($page) }}"@if ($page === $current) aria-current="page"@endif><span class="lgp-sr">Strana </span>{{ $page }}</a>
				</li>
			@endforeach
			@if ($current < $last)
				<li><a class="lgp-pager__link" href="{{ $paginator->url($current + 1) }}" rel="next">›<span class="lgp-sr"> Sledeća strana</span></a></li>
			@endif
		</ul>
	</nav>
@endif
