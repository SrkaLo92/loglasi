@extends('layouts.app')
@include('payments.partials.head')
@use('App\Models\Product')
@php
	$title = $product->name.' | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgp lg-wrap">
	<nav class="lgp-crumbs" aria-label="Putanja">
		<a href="{{ url_slash('moj-nalog') }}">Moj nalog</a> <span aria-hidden="true">›</span>
		<a href="{{ url_slash('moj-nalog/narudzbine') }}">Moje narudžbine</a> <span aria-hidden="true">›</span>
		<span>Nova narudžbina</span>
	</nav>
	<header class="lgp-head">
		<h1>{{ $product->name }}</h1>
		<p class="lgp-lead">@include('payments.partials.benefit', ['product' => $product])</p>
	</header>

	@include('payments.partials.status')

	<section class="lgp-card lgp-confirm" aria-labelledby="lgp-confirm-title">
		<h2 id="lgp-confirm-title">{{ $needsChoice ? 'Izaberite oglas' : 'Potvrda narudžbine' }}</h2>

		@if ($needsChoice)
			@if ($listings->isEmpty())
				<p>Nemate aktivnih oglasa. Uslugu možete da naručite za oglas koji je objavljen i nije istekao.</p>
				<p><a class="lg-btn lg-btn--orange" href="{{ url_slash('postavi-oglas') }}">Postavite oglas</a></p>
			@else
				<form method="get" action="{{ url_slash('moj-nalog/narudzbine/nova') }}" class="lgp-form">
					<input type="hidden" name="usluga" value="{{ $product->code }}">
					<div class="lgp-field">
						<label for="lgp-oglas">Oglas</label>
						<select id="lgp-oglas" name="oglas" required>
							@foreach ($listings as $item)
								<option value="{{ $item->id }}">{{ $item->title }}</option>
							@endforeach
						</select>
					</div>
					<button class="lg-btn lg-btn--green" type="submit">Dalje</button>
				</form>
			@endif
		@else
			<dl class="lgp-summary">
				<div><dt>Usluga</dt><dd>{{ $product->name }}</dd></div>
				@if ($listing)
					<div><dt>Oglas</dt><dd><a href="{{ $listing->url() }}">{{ $listing->title }}</a></dd></div>
					@if ($listing->isFeatured())
						<div><dt>Istaknut do</dt><dd>{{ $listing->featured_until->format('j. n. Y. \u H:i') }}</dd></div>
					@endif
				@endif
				@if ($product->code === Product::BIZNIS_12M && $firm)
					<div><dt>Firma</dt><dd>{{ $firm->name }}</dd></div>
					@if ($firm->hasBiznis())
						<div><dt>Paket važi do</dt><dd>{{ $firm->biznis_until->format('j. n. Y.') }}</dd></div>
					@endif
				@endif
				<div class="lgp-summary__total"><dt>Iznos za uplatu</dt><dd>{{ number_format($product->price_rsd, 0, ',', '.') }}&nbsp;RSD</dd></div>
			</dl>

			@if ($reason)
				<p class="lgp-alert lgp-alert--warn" role="alert">{{ $reason }}</p>
				@if ($product->code === Product::BIZNIS_12M)
					<p><a class="lg-btn lg-btn--outline" href="{{ url_slash('kontakt') }}?tema=firma#forma">Prijavite firmu</a></p>
				@endif
			@else
				<form method="post" action="{{ url_slash('moj-nalog/narudzbine') }}" class="lgp-form">
					@csrf
					<input type="hidden" name="usluga" value="{{ $product->code }}">
					@if ($listing)<input type="hidden" name="oglas" value="{{ $listing->id }}">@endif
					@php($buyer = old('kupac', $privateEnabled && ! $firm ? 'private' : 'firm'))
					<fieldset class="lgp-field lgp-buyer">
						<legend>Plaćam kao</legend>
						@if ($privateEnabled)
							<label class="lgp-radio"><input type="radio" name="kupac" value="private" @checked($buyer === 'private')> Fizičko lice <span class="lgp-help">dobijate fiskalni račun</span></label>
						@endif
						<label class="lgp-radio"><input type="radio" name="kupac" value="firm" @checked($buyer === 'firm' || ! $privateEnabled)> Firma ili preduzetnik, sa računa firme <span class="lgp-help">dobijate račun na firmu</span></label>
					</fieldset>
					<div class="lgp-firm">
						<p class="lgp-help">Za plaćanje kao firma upišite podatke za račun. Ako uplaćujete sa ličnog računa, izaberite fizičko lice.</p>
						<div class="lgp-field">
							<label for="lgp-naziv">Naziv firme</label>
							<input id="lgp-naziv" type="text" name="naziv" maxlength="150" value="{{ old('naziv', $firm?->name) }}" autocomplete="organization" @error('naziv') aria-invalid="true" aria-describedby="lgp-naziv-greska" @enderror>
							@error('naziv')<p class="lgp-error" id="lgp-naziv-greska">{{ $message }}</p>@enderror
						</div>
						<div class="lgp-row">
							<div class="lgp-field">
								<label for="lgp-pib">PIB</label>
								<input id="lgp-pib" type="text" name="pib" inputmode="numeric" maxlength="9" value="{{ old('pib', $firm?->pib) }}" @error('pib') aria-invalid="true" aria-describedby="lgp-pib-greska" @enderror>
								@error('pib')<p class="lgp-error" id="lgp-pib-greska">{{ $message }}</p>@enderror
							</div>
							<div class="lgp-field">
								<label for="lgp-mb">Matični broj</label>
								<input id="lgp-mb" type="text" name="mb" inputmode="numeric" maxlength="8" value="{{ old('mb', $firm?->mb) }}" @error('mb') aria-invalid="true" aria-describedby="lgp-mb-greska" @enderror>
								@error('mb')<p class="lgp-error" id="lgp-mb-greska">{{ $message }}</p>@enderror
							</div>
						</div>
					</div>
					<label class="lgp-check">
						<input type="checkbox" name="saglasnost" value="1" required @checked(old('saglasnost')) @error('saglasnost') aria-invalid="true" aria-describedby="lgp-saglasnost-greska" @enderror>
						<span>Saglasan sam da usluga počne odmah po uplati i znam da time gubim pravo na odustanak od ugovora u roku od 14 dana.</span>
					</label>
					@error('saglasnost')<p class="lgp-error" id="lgp-saglasnost-greska">{{ $message }}</p>@enderror
					<p class="lgp-help">Plaćanje je uplatom na račun. Posle potvrde dobijate predračun sa pozivom na broj i QR kodom za plaćanje telefonom. Narudžbina koja nije plaćena u roku od {{ config('payments.pending_days') }} dana se otkazuje. {{ config('payments.vat_note') }}</p>
					<button class="lg-btn lg-btn--green" type="submit">Potvrdi narudžbinu</button>
				</form>
			@endif
		@endif
	</section>
</div>
@endsection
