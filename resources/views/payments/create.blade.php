@extends('layouts.app')
@include('payments.partials.head')
@use('App\Services\Payments\ProductCatalog')
@use('App\Support\Payments\PaymentDetails')
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
						<label for="lgpay-listing">Oglas</label>
						<select id="lgpay-listing" data-pick name="oglas" required>
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
				@endif
				@if (ProductCatalog::type($product) === ProductCatalog::FIRM_PACKAGE && $firm)
					<div><dt>Firma</dt><dd>{{ $firm->name }}</dd></div>
					@if ($firm->package_ends_at?->isFuture())
						<div><dt>Trenutni paket važi do</dt><dd>{{ $firm->package_ends_at->format('j. n. Y.') }}</dd></div>
					@endif
				@endif
				<div class="lgpay-summary__total"><dt>Iznos za uplatu</dt><dd>{{ number_format($product->price_rsd, 0, ',', '.') }}&nbsp;RSD</dd></div>
			</dl>
			@if ($vat = PaymentDetails::vatNote())<p class="lgpay-help">{{ $vat }}</p>@endif

			@if ($reason)
				<p class="lgpay-alert lgpay-alert--warn" role="alert">{{ $reason }}</p>
				@if (ProductCatalog::type($product) === ProductCatalog::FIRM_PACKAGE)
					<p><a class="lg-btn lg-btn--outline" href="{{ url_slash('kontakt') }}?tema=firma#forma">Prijavite firmu</a></p>
				@endif
			@elseif ($credit)
				<p class="lgpay-alert lgpay-alert--ok">{{ str_starts_with($credit, 'pack:') ? 'Imate kupljena podizanja koja još niste iskoristili.' : 'Ova usluga je uključena u vaš paket za ovaj mesec.' }} Uključite je bez plaćanja.</p>
				<form method="post" action="{{ url_slash('moj-nalog/narudzbine/iskoristi') }}" class="lgpay-form">
					@csrf
					<input type="hidden" name="usluga" value="{{ $product->code }}">
					<input type="hidden" name="oglas" value="{{ $listing->id }}">
					<button class="lg-btn lg-btn--green" type="submit">Iskoristi uključenu uslugu</button>
				</form>
			@else
				<form method="post" action="{{ url_slash('moj-nalog/narudzbine') }}" class="lgpay-form">
					@csrf
					<input type="hidden" name="usluga" value="{{ $product->code }}">
					@if ($listing)<input type="hidden" name="oglas" value="{{ $listing->id }}">@endif
					@php($buyer = old('kupac', $privateEnabled && ! $firm ? 'private' : 'firm'))
					<fieldset class="lgpay-field lgpay-buyer">
						<legend>Plaćam kao:</legend>
						@if ($privateEnabled)
							<label class="lgpay-radio"><input type="radio" name="kupac" value="private" @checked($buyer === 'private')> fizičko lice</label>
						@endif
						<label class="lgpay-radio"><input type="radio" name="kupac" value="firm" @checked($buyer === 'firm' || ! $privateEnabled)> firma ili preduzetnik</label>
						@unless ($privateEnabled)
							<p class="lgpay-help">Za sada plaćaju firme i preduzetnici, uz račun. Plaćanje za fizička lica uključujemo uskoro.</p>
						@endunless
					</fieldset>
					<fieldset class="lgpay-firm">
						<legend class="lgpay-help">Samo za firmu ili preduzetnika: podaci za račun</legend>
						@foreach ([
							['naziv', 'Naziv firme', $firm?->name, 'organization', 150],
							['pib', 'PIB', $firm?->pib, 'off', 9],
							['mb', 'Matični broj', $firm?->mb, 'off', 8],
							['adresa', 'Adresa', $firm?->address, 'street-address', 200],
							['mejl', 'Mejl za račun', auth()->user()->email, 'email', 150],
						] as [$name, $label, $value, $auto, $max])
							<div class="lgpay-field">
								<label for="lgpay-{{ $name }}">{{ $label }}*</label>
								<input id="lgpay-{{ $name }}" type="{{ $name === 'mejl' ? 'email' : 'text' }}" name="{{ $name }}" maxlength="{{ $max }}" value="{{ old($name, $value) }}" autocomplete="{{ $auto }}" @if (in_array($name, ['pib', 'mb'], true)) inputmode="numeric" @endif @error($name) aria-invalid="true" aria-describedby="lgpay-{{ $name }}-greska" @enderror>
								@error($name)<p class="lgpay-error" id="lgpay-{{ $name }}-greska">{{ $message }}</p>@enderror
							</div>
						@endforeach
					</fieldset>
					@php($withdrawal = PaymentDetails::legalLink('withdrawal'))
					@php($terms = PaymentDetails::legalLink('terms'))
					<label class="lgpay-check">
						<input type="checkbox" name="odmah" value="1" @checked(old('odmah')) @error('odmah') aria-invalid="true" aria-describedby="lgpay-odmah-error" @enderror>
						<span>Saglasan/na sam da usluga počne odmah po evidentiranju uplate i razumem da time gubim pravo na odustanak od ugovora za izvršenu uslugu. Više na strani <a href="{{ $withdrawal }}">Odustanak od ugovora</a>. <span class="lgpay-help">Obavezno za fizička lica.</span></span>
					</label>
					@error('odmah')<p class="lgpay-error" id="lgpay-odmah-error">{{ $message }}</p>@enderror
					<label class="lgpay-check">
						<input type="checkbox" name="uslovi" value="1" required @checked(old('uslovi')) @error('uslovi') aria-invalid="true" aria-describedby="lgpay-uslovi-error" @enderror>
						<span>Pročitao/la sam i prihvatam <a href="{{ $terms }}">Uslove kupovine i plaćanja</a>.</span>
					</label>
					@error('uslovi')<p class="lgpay-error" id="lgpay-uslovi-error">{{ $message }}</p>@enderror
					<p class="lgpay-help">Reklamacije i povraćaj novca: <a href="{{ PaymentDetails::legalLink('complaints') }}">uslovi i rokovi</a>.</p>
					<p class="lgpay-help">Plaćanje je uplatom na račun. Posle potvrde dobijate predračun sa pozivom na broj i QR kodom za plaćanje telefonom. Porudžbina koja nije plaćena u roku od {{ config('payments.pending_days') }} dana se otkazuje.@if ($testMode) <strong>Probni režim: uplata se ne knjiži, račun je probni.</strong>@endif</p>
					<button class="lg-btn lg-btn--green" type="submit">Potvrdi porudžbinu</button>
				</form>
			@endif
		@endif
	</section>
</div>
@endsection
