{{-- Newest listings from the classifieds site (links go to loglasi.rs). --}}
@use('App\Support\Info\InfoUrl')
@use('App\Support\Search\ImageUrls')
@if ($listings->isNotEmpty())
<section class="inf-block" aria-label="Novi oglasi iz Loznice">
	<div class="inf-block__head"><h2>{{ $heading ?? 'Najnoviji oglasi iz Loznice' }}</h2><a class="inf-more inf-more--warm" href="{{ InfoUrl::main('oglasi') }}">Svi oglasi <span aria-hidden="true">→</span></a></div>
	<div class="inf-ads">
		@foreach ($listings as $l)
			<a class="inf-ad" href="{{ InfoUrl::main('oglas/'.$l->slug) }}">
				<span class="inf-ad__media">@if ($l->coverImage)<img src="{{ ImageUrls::url($l->coverImage, 'thumb') }}" alt="" loading="lazy" decoding="async" width="400" height="300">@endif</span>
				<span class="inf-ad__body"><strong>{{ $l->title }}</strong><span class="inf-ad__price">{{ $l->priceLabel() }}</span><span class="inf-meta">{{ $l->location?->name }}</span></span>
			</a>
		@endforeach
	</div>
	<a class="inf-btn inf-btn--warm" href="{{ InfoUrl::main('postavi-oglas') }}">+ Postavite oglas besplatno</a>
</section>
@endif
