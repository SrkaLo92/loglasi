@use('App\Support\Listing\Icons')
@php
	$count = $listing->images->count();
@endphp
<section class="lgo lgo-gallery lgo-o1" aria-label="Fotografije oglasa" data-lgo-gallery>
	@if ($count === 0)
		<div class="lgo-gallery__empty">{{ Icons::listing('image', 48) }}<span>Oglas nema fotografija</span></div>
	@else
		<div class="lgo-gallery__track" tabindex="0" aria-label="Fotografije, {{ $count }} ukupno" data-lgo-track>
			@foreach ($listing->images as $i => $image)
				<figure class="lgo-gallery__slide" id="slika-{{ $i + 1 }}" data-lgo-slide>
					<a href="{{ $image->sizeUrl('lg') }}" target="_blank" rel="noopener" data-lgo-full="{{ $image->sizeUrl('lg') }}" data-lgo-thumb-src="{{ $image->sizeUrl('sm') }}" aria-label="Otvori galeriju, fotografija {{ $i + 1 }} od {{ $count }}">
						@include('listing.partials.picture', [
							'image' => $image,
							'alt' => $image->alt ?: $listing->title.($count > 1 ? ', fotografija '.($i + 1) : ''),
							'sizes' => '(max-width: 767px) 100vw, (max-width: 1240px) 60vw, 800px',
							'eager' => $i === 0,
							'class' => 'lgo-gallery__img',
						])
					</a>
				</figure>
			@endforeach
		</div>
		@if ($count > 1)
			<span class="lgo-gallery__count" aria-hidden="true"><span data-lgo-current>1</span> / {{ $count }}</span>
			<button type="button" class="lgo-gallery__nav lgo-gallery__nav--prev" data-lgo-prev hidden aria-label="Prethodna fotografija">{{ Icons::listing('chevron-l', 22) }}</button>
			<button type="button" class="lgo-gallery__nav lgo-gallery__nav--next" data-lgo-next hidden aria-label="Sledeća fotografija">{{ Icons::listing('chevron-r', 22) }}</button>
			<ol class="lgo-gallery__thumbs">
				@foreach ($listing->images as $i => $image)
					<li>
						<a href="#slika-{{ $i + 1 }}" @if ($i === 0) aria-current="true" @endif data-lgo-thumb="{{ $i }}" aria-label="Fotografija {{ $i + 1 }}">
							<img src="{{ $image->sizeUrl('sm') }}" alt="" width="120" height="90" loading="lazy" decoding="async">
						</a>
					</li>
				@endforeach
			</ol>
		@endif
	@endif
</section>
