{{-- Category picker: search (GET ?trazi=, live with JavaScript) and the tree one level at a time (?grupa=ID).
     A last-level category is a submit button, a group a link; everything works without JavaScript. --}}
@use('App\Support\Search\CategoryIcons')
@php
	$base = $baseUrl ?? url()->current();
	$parent = $picker['parent'];
	$back = $parent && $parent->parent_id ? $base.'?grupa='.$parent->parent_id : $base;
	$query = $picker['query'];
@endphp
<div class="pst-picker" data-pst-picker>
	<form class="pst-find" method="get" action="{{ $base }}" role="search">
		<label class="pst-find__label" for="pst-find">Pronađite kategoriju</label>
		<div class="pst-find__row">
			<span class="pst-find__icon">{{ CategoryIcons::svg('search', 20) }}</span>
			<input id="pst-find" type="search" name="trazi" value="{{ $query }}" placeholder="npr. stan, bicikl, frižider, laptop" autocomplete="off" maxlength="60" data-pst-find>
			<button class="pst-btn pst-btn--line pst-btn--sm" type="submit" data-pst-find-submit>Traži</button>
		</div>
	</form>

	<form class="pst-picker__form" method="post" action="{{ $action }}">
		@csrf
		@include('posting.partials.error', ['field' => 'category_id', 'id' => 'pst-category'])

		<div class="pst-results" data-pst-results @if ($query === '') hidden @endif aria-live="polite">
			<p class="pst-help" data-pst-results-note>
				@if ($query !== '')
					@if ($picker['results'] === [])
						Nema kategorije za „{{ $query }}”. Probajte drugu reč ili izaberite sa spiska ispod.
					@else
						Kategorije za „{{ $query }}”:
					@endif
				@endif
			</p>
			<ul class="pst-cats pst-cats--results" role="list" data-pst-results-list>
				@foreach ($picker['results'] as $leaf)
					<li>
						<button class="pst-cat pst-cat--result" type="submit" name="category_id" value="{{ $leaf['id'] }}">
							<span class="pst-cat__text"><span class="pst-cat__name">{{ $leaf['name'] }}</span><span class="pst-cat__path">{{ $leaf['path'] }}</span></span>
							<span class="pst-cat__more" aria-hidden="true">›</span>
						</button>
					</li>
				@endforeach
			</ul>
		</div>

		<div class="pst-tree" data-pst-tree>
			@if ($parent)
				<div class="pst-group">
					<a class="pst-back" href="{{ $back }}">‹ Nazad</a>
					<p class="pst-group__title"><span class="pst-cat__icon">{{ CategoryIcons::forCategory($parent, 22) }}</span>{{ $parent->name }}</p>
				</div>
				@if ($picker['trail']->count() > 1)
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
				@endif
			@elseif ($query === '')
				<p class="pst-help">Ili izaberite sa spiska:</p>
			@endif

			<ul class="pst-cats{{ $parent ? '' : ' pst-cats--top' }}" role="list">
				@foreach ($picker['items'] as $item)
					@php $cat = $item['category']; @endphp
					<li>
						@if ($item['leaf'])
							<button class="pst-cat{{ $picker['current'] === $cat->id ? ' is-current' : '' }}" type="submit" name="category_id" value="{{ $cat->id }}">
								@unless ($parent)<span class="pst-cat__icon">{{ CategoryIcons::forCategory($cat, 22) }}</span>@endunless
								<span class="pst-cat__name">{{ $cat->name }}</span>
								@if ($picker['current'] === $cat->id)<span class="pst-cat__note">izabrano</span>@endif
							</button>
						@else
							<a class="pst-cat pst-cat--group" href="{{ $base }}?grupa={{ $cat->id }}">
								@unless ($parent)<span class="pst-cat__icon">{{ CategoryIcons::forCategory($cat, 22) }}</span>@endunless
								<span class="pst-cat__name">{{ $cat->name }}</span>
								<span class="pst-cat__more" aria-hidden="true">›</span>
							</a>
						@endif
					</li>
				@endforeach
			</ul>
		</div>
	</form>
	<script type="application/json" data-pst-leaves>@json($picker['leaves'])</script>
</div>
