{{-- Newest listings from the classifieds site (links go to loglasi.rs). --}}
@use('App\Support\Info\InfoUrl')
@use('App\Support\Search\ImageUrls')
@use('App\Support\Search\CategoryIcons')
@if ($listings->isNotEmpty())
<section aria-labelledby="inf-oglasi">
	<div class="inf-head2"><h2 id="inf-oglasi">{{ $heading ?? 'Novo na loglasi.rs' }}</h2><a class="inf-link" href="{{ InfoUrl::main('oglasi') }}">Svi oglasi</a></div>
	<div class="inf-grid4">
		@foreach ($listings as $l)
			<a class="inf-listing" href="{{ InfoUrl::main('oglas/'.$l->slug) }}">
				@if ($l->coverImage)<img src="{{ ImageUrls::url($l->coverImage, 'thumb') }}" alt="" loading="lazy" decoding="async" width="400" height="300">
				@else<span class="inf-listing__ph" aria-hidden="true">{{ CategoryIcons::forCategory($l->category, 32) }}<span>{{ $l->category?->name ?? 'Bez fotografije' }}</span></span>@endif
				<span class="inf-listing__body"><span class="inf-meta">{{ $l->location?->name }}</span><strong>{{ $l->title }}</strong><span class="inf-listing__price">{{ $l->priceLabel() }}</span></span>
			</a>
		@endforeach
	</div>
</section>
@endif
