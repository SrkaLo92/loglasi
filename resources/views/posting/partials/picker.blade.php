{{-- Category tree, one level at a time; a leaf is a submit button, a group a link (?grupa=ID). --}}
@php
	$base = $baseUrl ?? url()->current();
@endphp
<form class="pst-picker" method="post" action="{{ $action }}">
	@csrf
	<nav class="pst-trail" aria-label="Izabrane kategorije">
		<ol>
			<li><a href="{{ $base }}">Sve kategorije</a></li>
			@foreach ($picker['trail'] as $crumb)
				<li>
					@if ($loop->last)
						<span aria-current="page">{{ $crumb->name }}</span>
					@else
						<a href="{{ $base }}?grupa={{ $crumb->id }}">{{ $crumb->name }}</a>
					@endif
				</li>
			@endforeach
		</ol>
	</nav>
	@include('posting.partials.error', ['field' => 'category_id', 'id' => 'pst-category'])
	<ul class="pst-cats" role="list">
		@foreach ($picker['items'] as $item)
			@php $cat = $item['category']; @endphp
			<li>
				@if ($item['leaf'])
					<button class="pst-cat{{ $picker['current'] === $cat->id ? ' is-current' : '' }}" type="submit" name="category_id" value="{{ $cat->id }}">
						<span class="pst-cat__name">{{ $cat->name }}</span>
						@if ($picker['current'] === $cat->id)<span class="pst-cat__note">izabrano</span>@endif
					</button>
				@else
					<a class="pst-cat pst-cat--group" href="{{ $base }}?grupa={{ $cat->id }}">
						<span class="pst-cat__name">{{ $cat->name }}</span>
						<span class="pst-cat__more" aria-hidden="true">›</span>
					</a>
				@endif
			</li>
		@endforeach
	</ul>
</form>
