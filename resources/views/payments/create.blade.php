@extends('layouts.app')
@include('payments.partials.head')
@use('App\Models\Product')
@php
	$title = $product->name.' | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgpay lg-wrap">
	<nav class="lgpay-crumbs" aria-label="Putanja">
		<a href="{{ url_slash('moj-nalog') }}">Moj nalog</a> <span aria-hidden="true">›</span>
		<a href="{{ url_slash('moj-nalog/narudzbine') }}">Moje narudžbine</a> <span aria-hidden="true">›</span>
		<span>Nova narudžbina</span>
	</nav>
	<header class="lgpay-head">
		<h1>{{ $product->name }}</h1>
		<p class="lgpay-lead">@include('payments.partials.benefit', ['product' => $product])</p>
	</header>

	@include('payments.partials.status')

	<section class="lgpay-card lgpay-confirm" aria-labelledby="lgpay-confirm-title">
		<h2 id="lgpay-confirm-title">{{ $needsChoice ? 'Izaberite oglas' : 'Potvrda narudžbine' }}</h2>

		@if ($needsChoice)
			@if ($listings->isEmpty())
				<p>Nemate aktivnih oglasa. Uslugu možete da naručite za oglas koji je objavljen i nije istekao.</p>
				<p><a class="lg-btn lg-btn--orange" href="{{ url_slash('postavi-oglas') }}">Postavite oglas</a></p>
			@else
				<form method="get" action="{{ url_slash('moj-nalog/narudzbine/nova') }}" class="lgpay-form">
					<input type="hidden" name="usluga" value="{{ $product->code }}">
					<div class="lgpay-field">
						<label for="lgpay-oglas">Oglas</label>
						<select id="lgpay-oglas" name="oglas" required>
							@foreach ($listings as $item)
								<option value="{{ $item->id }}">{{ $item->title }}</option>
							@endforeach
						</select>
					</div>
					<button class="lg-btn lg-btn--green" type="submit">Dalje</button>
				</form>
			@endif
		@else
			<dl class="lgpay-summary">
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
				<div class="lgpay-summary__total"><dt>Iznos za uplatu</dt><dd>{{ number_format($product->price_rsd, 0, ',', '.') }}&nbsp;RSD</dd></div>
			</dl>

			@if ($reason)
				<p class="lgpay-alert lgpay-alert--warn" role="alert">{{ $reason }}</p>
				@if ($product->code === Product::BIZNIS_12M)
					<p><a class="lg-btn lg-btn--outline" href="{{ url_slash('kontakt') }}?tema=firma#forma">Prijavite firmu</a></p>
				@endif
			@else
				<form method="post" action="{{ url_slash('moj-nalog/narudzbine') }}" class="lgpay-form">
					@csrf
					<input type="hidden" name="usluga" value="{{ $product->code }}">
					@if ($listing)<input type="hidden" name="oglas" value="{{ $listing->id }}">@endif
					@php($buyer = old('kupac', $privateEnabled && ! $firm ? 'private' : 'firm'))
					<fieldset class="lgpay-field lgpay-buyer">
						<legend>Plaćam kao</legend>
						@if ($privateEnabled)
							<label class="lgpay-radio"><input type="radio" name="kupac" value="private" @checked($buyer === 'private')> Fizičko lice <span class="lgpay-help">dobijate fiskalni račun</span></label>
						@endif
						<label class="lgpay-radio"><input type="radio" name="kupac" value="firm" @checked($buyer === 'firm' || ! $privateEnabled)> Firma ili preduzetnik, sa računa firme <span class="lgpay-help">dobijate račun na firmu</span></label>
					</fieldset>
					<div class="lgpay-firm">
						<p class="lgpay-help">Za plaćanje kao firma upišite podatke za račun. Ako uplaćujete sa ličnog računa, izaberite fizičko lice.</p>
						<div class="lgpay-field">
							<label for="lgpay-naziv">Naziv firme</label>
							<input id="lgpay-naziv" type="text" name="naziv" maxlength="150" value="{{ old('naziv', $firm?->name) }}" autocomplete="organization" @error('naziv') aria-invalid="true" aria-describedby="lgpay-naziv-greska" @enderror>
							@error('naziv')<p class="lgpay-error" id="lgpay-naziv-greska">{{ $message }}</p>@enderror
						</div>
						<div class="lgpay-row">
							<div class="lgpay-field">
								<label for="lgpay-pib">PIB</label>
								<input id="lgpay-pib" type="text" name="pib" inputmode="numeric" maxlength="9" value="{{ old('pib', $firm?->pib) }}" @error('pib') aria-invalid="true" aria-describedby="lgpay-pib-greska" @enderror>
								@error('pib')<p class="lgpay-error" id="lgpay-pib-greska">{{ $message }}</p>@enderror
							</div>
							<div class="lgpay-field">
								<label for="lgpay-mb">Matični broj</label>
								<input id="lgpay-mb" type="text" name="mb" inputmode="numeric" maxlength="8" value="{{ old('mb', $firm?->mb) }}" @error('mb') aria-invalid="true" aria-describedby="lgpay-mb-greska" @enderror>
								@error('mb')<p class="lgpay-error" id="lgpay-mb-greska">{{ $message }}</p>@enderror
							</div>
						</div>
					</div>
					<label class="lgpay-check">
						<input type="checkbox" name="saglasnost" value="1" required @checked(old('saglasnost')) @error('saglasnost') aria-invalid="true" aria-describedby="lgpay-saglasnost-greska" @enderror>
						<span>Saglasan sam da usluga počne odmah po uplati i znam da time gubim pravo na odustanak od ugovora u roku od 14 dana.</span>
					</label>
					@error('saglasnost')<p class="lgpay-error" id="lgpay-saglasnost-greska">{{ $message }}</p>@enderror
					<p class="lgpay-help">Plaćanje je uplatom na račun. Posle potvrde dobijate predračun sa pozivom na broj i QR kodom za plaćanje telefonom. Narudžbina koja nije plaćena u roku od {{ config('payments.pending_days') }} dana se otkazuje. {{ config('payments.vat_note') }}</p>
					<button class="lg-btn lg-btn--green" type="submit">Potvrdi narudžbinu</button>
				</form>
			@endif
		@endif
	</section>
</div>
@endsection
