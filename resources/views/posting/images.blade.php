@extends('posting.frame')
@use('App\Support\Posting\Wizard')

@section('step')
@php $svc = app(\App\Services\ListingImages::class); @endphp
<section class="pst-card" aria-labelledby="pst-img-title">
	<h2 id="pst-img-title">Slike</h2>
	<p class="pst-help">Do {{ $maxImages }} slika, JPG, PNG ili WebP, najviše {{ $maxMb }} MB po slici. Prva slika je naslovna. Podaci iz fotografije (lokacija, uređaj) se brišu.</p>

	@if ($errors->has('images'))
		<div class="pst-alert pst-alert--error" role="alert">
			@foreach ($errors->get('images') as $message)
				<p>{{ $message }}</p>
			@endforeach
		</div>
	@endif

	@if ($listingImages->count() < $maxImages)
		<form class="pst-upload" method="post" action="{{ Wizard::url($listing, 'images') }}" enctype="multipart/form-data" data-pst-upload>
			@csrf
			<label class="pst-upload__zone" for="pst-files" data-pst-drop>
				{{ \App\Support\Stranice\Ikonice::svg('image', 32) }}
				<span class="pst-upload__title">Izaberite slike</span>
				<span class="pst-help pst-upload__hint">ili ih prevucite ovde</span>
			</label>
			<input class="pst-upload__input" id="pst-files" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif">
			<button class="pst-btn pst-btn--line" type="submit" data-pst-upload-button>Otpremi izabrane slike</button>
			<p class="pst-help" data-pst-upload-status role="status" aria-live="polite"></p>
		</form>
	@else
		<p class="pst-alert pst-alert--warn">Dodali ste najviše slika ({{ $maxImages }}). Obrišite neku da biste dodali novu.</p>
	@endif

	@if ($listingImages->isNotEmpty())
		<ol class="pst-photos" role="list" data-pst-sortable data-pst-order-url="{{ Wizard::url($listing, 'images') }}redosled/">
			@foreach ($listingImages as $image)
				<li class="pst-photo" data-id="{{ $image->id }}">
					<picture>
						<source srcset="{{ $svc->url($image, 'sm', 'webp') }}" type="image/webp">
						<img src="{{ $svc->url($image, 'sm', 'jpg') }}" alt="Slika {{ $loop->iteration }}" width="{{ $image->width ?: 320 }}" height="{{ $image->height ?: 240 }}" loading="lazy">
					</picture>
					@if ($loop->first)<span class="pst-photo__badge">Naslovna</span>@endif
					<div class="pst-photo__tools">
						@unless ($loop->first)
							<form method="post" action="{{ Wizard::url($listing, 'images') }}{{ $image->id }}/naslovna/">@csrf<button class="pst-tool" type="submit">Naslovna</button></form>
							<form method="post" action="{{ Wizard::url($listing, 'images') }}{{ $image->id }}/pomeri/">@csrf<input type="hidden" name="direction" value="up"><button class="pst-tool" type="submit" aria-label="Pomeri sliku {{ $loop->iteration }} levo">‹</button></form>
						@endunless
						@unless ($loop->last)
							<form method="post" action="{{ Wizard::url($listing, 'images') }}{{ $image->id }}/pomeri/">@csrf<input type="hidden" name="direction" value="down"><button class="pst-tool" type="submit" aria-label="Pomeri sliku {{ $loop->iteration }} desno">›</button></form>
						@endunless
						<form method="post" action="{{ Wizard::url($listing, 'images') }}{{ $image->id }}/">@csrf @method('DELETE')<button class="pst-tool pst-tool--danger" type="submit" aria-label="Obriši sliku {{ $loop->iteration }}">Obriši</button></form>
					</div>
				</li>
			@endforeach
		</ol>
		<p class="pst-help pst-js-only">Redosled možete da promenite i prevlačenjem slika.</p>
	@endif
</section>
<div class="pst-actions">
	@if ($previous)<a class="pst-btn pst-btn--line" href="{{ Wizard::url($listing, $previous) }}">Nazad</a>@endif
	<a class="pst-btn" href="{{ Wizard::url($listing, 'contact') }}">{{ $listingImages->isEmpty() ? 'Nastavi bez slika' : 'Nastavi' }}</a>
</div>
@endsection
