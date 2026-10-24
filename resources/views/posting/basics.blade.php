@extends('posting.frame')
@use('App\Services\Posting')

@php
	$negotiable = old('price_type', $listing->price === null && $listing->title !== '' ? 'negotiable' : 'fixed') === 'negotiable';
	$priceValue = old('price', $listing->price !== null ? rtrim(rtrim(number_format((float) $listing->price, 2, ',', '.'), '0'), ',') : '');
	$currency = old('currency', ($listing->currency ?? \App\Enums\Currency::EUR)->value);
@endphp

@section('step')
<div class="pst-grid">
	<form class="pst-card pst-form" method="post" action="{{ \App\Support\Posting\Wizard::url($listing, 'basics') }}" novalidate data-pst-preview-form>
		@csrf
		@method('PUT')
		<h2>Osnovni podaci</h2>
		<p class="pst-help">Kategorija: <strong>{{ $breadcrumbs->pluck('name')->implode(' › ') }}</strong> · <a href="{{ \App\Support\Posting\Wizard::url($listing, 'category') }}">promeni</a></p>

		<div class="pst-field">
			<label for="pst-title">Naslov oglasa</label>
			<input id="pst-title" type="text" name="title" value="{{ old('title', $listing->title) }}" maxlength="{{ Posting::TITLE_MAX }}" required
				placeholder="npr. Golf 4 1.9 TDI, 2003. godište" data-pst-preview="title"
				@if ($errors->has('title')) aria-invalid="true" aria-describedby="pst-title-error" @else aria-describedby="pst-title-help" @endif>
			<p class="pst-help" id="pst-title-help">Kratko i jasno, do {{ Posting::TITLE_MAX }} znakova. Naslov je i adresa oglasa i ne menja se posle objave.</p>
			@include('posting.partials.error', ['field' => 'title', 'id' => 'pst-title'])
		</div>

		<div class="pst-field">
			<label for="pst-description">Opis</label>
			<textarea id="pst-description" name="description" rows="8" maxlength="{{ Posting::DESCRIPTION_MAX }}" required data-pst-preview="description"
				@if ($errors->has('description')) aria-invalid="true" aria-describedby="pst-description-error" @else aria-describedby="pst-description-help" @endif>{{ old('description', $listing->description) }}</textarea>
			<p class="pst-help" id="pst-description-help">Stanje, mane, šta ide uz predmet, način preuzimanja. Do {{ number_format(Posting::DESCRIPTION_MAX, 0, ',', '.') }} znakova.</p>
			@include('posting.partials.error', ['field' => 'description', 'id' => 'pst-description'])
		</div>

		<fieldset class="pst-field pst-price">
			<legend>Cena</legend>
			<div class="pst-choice">
				<label class="pst-radio"><input type="radio" name="price_type" value="fixed" @checked(! $negotiable) data-pst-preview="price_type"> Cena</label>
				<label class="pst-radio"><input type="radio" name="price_type" value="negotiable" @checked($negotiable) data-pst-preview="price_type"> Dogovor</label>
			</div>
			<div class="pst-price__row" data-pst-price-row>
				<label class="lg-visually-hidden" for="pst-price">Iznos</label>
				<input id="pst-price" type="text" name="price" value="{{ $priceValue }}" inputmode="decimal" autocomplete="off" placeholder="npr. 8.900" data-pst-preview="price"
					@if ($errors->has('price')) aria-invalid="true" aria-describedby="pst-price-error" @endif>
				<label class="lg-visually-hidden" for="pst-currency">Valuta</label>
				<select id="pst-currency" name="currency" data-pst-preview="currency">
					@foreach ($currencies as $c)
						<option value="{{ $c->value }}" @selected($currency === $c->value)>{{ $c === \App\Enums\Currency::EUR ? 'EUR (€)' : 'RSD (din)' }}</option>
					@endforeach
				</select>
			</div>
			<p class="pst-help">Za „Dogovor” iznos ne morate da upisujete.</p>
			@include('posting.partials.error', ['field' => 'price', 'id' => 'pst-price'])
			@include('posting.partials.error', ['field' => 'currency', 'id' => 'pst-currency'])
		</fieldset>

		<div class="pst-field">
			<label for="pst-location">Mesto</label>
			<select id="pst-location" name="location_id" required data-pst-preview="location"
				@if ($errors->has('location_id')) aria-invalid="true" aria-describedby="pst-location-error" @endif>
				<option value="">Izaberite mesto</option>
				@foreach ($locations as $location)
					<option value="{{ $location->id }}" @selected((string) old('location_id', $listing->location_id) === (string) $location->id)>{{ $location->name }}</option>
				@endforeach
			</select>
			@include('posting.partials.error', ['field' => 'location_id', 'id' => 'pst-location'])
		</div>

		@include('posting.partials.actions')
	</form>

	<aside class="pst-side" aria-label="Pregled kartice oglasa">
		<p class="pst-side__label">Ovako će oglas izgledati u listi</p>
		@include('posting.partials.card', ['listing' => $listing])
	</aside>
</div>
@endsection
