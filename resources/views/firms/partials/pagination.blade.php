{{--
	Numbered pagination for a LengthAwarePaginator. The paginator drops the trailing slash of its path,
	so links are built from $base (url_slash) and $query (filters); page 1 has no page parameter.
--}}
@if ($paginator->hasPages())
	@php
		$current = $paginator->currentPage();
		$last = $paginator->lastPage();
		$link = fn (int $page) => $base.(($q = http_build_query(array_filter($query ?? [], fn ($v) => $v !== null && $v !== '') + ($page > 1 ? ['page' => $page] : []))) !== '' ? '?'.$q : '');
		$pages = collect([1, $current - 1, $current, $current + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
	@endphp
	<nav aria-label="Strane">
		<ul class="lgf-pages">
			@if ($current > 1)
				<li><a href="{{ $link($current - 1) }}" rel="prev">Prethodna</a></li>
			@endif
			@foreach ($pages as $i => $page)
				@if ($i > 0 && $page - $pages[$i - 1] > 1)
					<li><span aria-hidden="true">…</span></li>
				@endif
				<li>
					@if ($page === $current)
						<span aria-current="page">{{ $page }}</span>
					@else
						<a href="{{ $link($page) }}">{{ $page }}</a>
					@endif
				</li>
			@endforeach
			@if ($current < $last)
				<li><a href="{{ $link($current + 1) }}" rel="next">Sledeća</a></li>
			@endif
		</ul>
	</nav>
@endif
