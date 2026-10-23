@extends('posting.frame')
@use('App\Support\Posting\Wizard')
@use('App\Enums\ListingStatus')

@php
	$svc = app(\App\Services\ListingImages::class);
	$stepFor = ['category' => 'category', 'basics' => 'basics', 'details' => 'details'];
	$canSubmit = in_array($listing->status, [ListingStatus::Draft, ListingStatus::Rejected], true);
@endphp

@section('step')
@if ($errors->has('submit'))
	<p class="pst-alert pst-alert--error" role="alert">{{ $errors->first('submit') }}</p>
@endif
@if ($missing !== [])
	<div class="pst-alert pst-alert--warn" role="alert">
		<p><strong>Pre objave dopunite:</strong></p>
		<ul>
			@foreach ($missing as $key => $message)
				<li><a href="{{ Wizard::url($listing, $stepFor[$key] ?? 'basics') }}">{{ $message }}</a></li>
			@endforeach
		</ul>
	</div>
@endif
@if ($listing->status === ListingStatus::Rejected && $listing->rejection_reason)
	<p class="pst-alert pst-alert--warn"><strong>Razlog odbijanja:</strong> {{ $listing->rejection_reason }}</p>
@endif

<article class="pst-card pst-review" aria-labelledby="pst-review-title">
	<div class="pst-review__gallery">
		@forelse ($listing->images as $image)
			<picture class="{{ $loop->first ? 'pst-review__cover' : 'pst-review__thumb' }}">
				<source srcset="{{ $svc->url($image, $loop->first ? 'lg' : 'sm', 'webp') }}" type="image/webp">
				<img src="{{ $svc->url($image, $loop->first ? 'lg' : 'sm', 'jpg') }}" alt="{{ $loop->first ? $listing->title : 'Slika '.$loop->iteration }}" width="{{ $image->width ?: 800 }}" height="{{ $image->height ?: 600 }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
			</picture>
		@empty
			<div class="pst-review__empty">{{ \App\Support\Stranice\Ikonice::svg('image', 40) }}<span>Oglas nema slika. <a href="{{ Wizard::url($listing, 'images') }}">Dodajte slike</a></span></div>
		@endforelse
	</div>
	<div class="pst-review__body">
		<p class="pst-help">{{ $breadcrumbs->pluck('name')->implode(' › ') }}</p>
		<h2 id="pst-review-title" class="pst-review__title">{{ $listing->title !== '' ? $listing->title : 'Oglas bez naslova' }}</h2>
		<p class="pst-review__price">{{ $listing->priceLabel() }}</p>
		<ul class="pst-review__meta" role="list">
			<li>{{ \App\Support\Stranice\Ikonice::svg('pin', 16) }}{{ $listing->location?->name ?? 'Mesto nije izabrano' }}</li>
			<li>{{ \App\Support\Stranice\Ikonice::svg('phone', 16) }}{{ $phone ? \App\Support\Phone::national($phone) : 'Bez broja telefona' }}</li>
		</ul>
		@if ($fieldValues !== [])
			<dl class="pst-review__attrs">
				@foreach ($fieldValues as $value)
					<div><dt>{{ $value['label'] }}</dt><dd>{{ $value['display'] }}</dd></div>
				@endforeach
			</dl>
		@endif
		<div class="pst-review__desc">{!! nl2br(e($listing->description)) !!}</div>
		<p class="pst-review__edit">
			<a href="{{ Wizard::url($listing, 'basics') }}">Izmeni podatke</a>
			<a href="{{ Wizard::url($listing, 'images') }}">Izmeni slike</a>
			<a href="{{ Wizard::url($listing, 'contact') }}">Izmeni kontakt</a>
		</p>
	</div>
</article>

@if ($canSubmit)
	<form class="pst-submit" method="post" action="{{ url_slash('postavi-oglas/'.$listing->id.'/objavi') }}">
		@csrf
		<p class="pst-help">
			@if ($needsReview)
				Oglas će biti vidljiv kad ga pregledamo. Novi nalozi i nalozi bez potvrđenog telefona prolaze proveru.
			@else
				Oglas će odmah biti vidljiv na sajtu i aktivan {{ app(\App\Services\Posting::class)->durationDays() }} dana. Obnova je besplatna.
			@endif
		</p>
		<div class="pst-actions">
			<a class="pst-btn pst-btn--line" href="{{ Wizard::url($listing, 'contact') }}">Nazad</a>
			<button class="pst-btn pst-btn--orange" type="submit" @disabled($missing !== [])>{{ $listing->status === ListingStatus::Rejected ? 'Pošalji ponovo na proveru' : 'Objavi oglas' }}</button>
		</div>
	</form>
@else
	<div class="pst-actions">
		<a class="pst-btn" href="{{ url_slash('moj-nalog/oglasi') }}">Gotovo</a>
		@if ($listing->status === ListingStatus::Published)
			<a class="pst-btn pst-btn--line" href="{{ url_slash('oglas/'.$listing->slug) }}">Pogledaj oglas na sajtu</a>
		@endif
	</div>
@endif
@endsection
