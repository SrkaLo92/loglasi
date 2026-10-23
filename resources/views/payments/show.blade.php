@extends('layouts.app')
@include('payments.partials.head')
@use('App\Enums\OrderStatus')
@use('App\Models\Product')
@use('App\Support\Stranice\PageText')
@php
	$title = 'Narudžbina '.$order->reference.' | loglasi.rs';
	$robots = 'noindex, follow';
	$pending = $order->status === OrderStatus::Pending;
@endphp

@section('content')
<div class="lgp lg-wrap">
	<nav class="lgp-crumbs" aria-label="Putanja">
		<a href="{{ url_slash('moj-nalog') }}">Moj nalog</a> <span aria-hidden="true">›</span>
		<a href="{{ url_slash('moj-nalog/narudzbine') }}">Moje narudžbine</a> <span aria-hidden="true">›</span>
		<span>{{ $order->reference }}</span>
	</nav>
	<header class="lgp-head">
		<h1>{{ $pending ? 'Uputstvo za uplatu' : 'Narudžbina '.$order->reference }}</h1>
		<p class="lgp-what">@include('payments.partials.what', ['order' => $order]) @include('payments.partials.badge', ['order' => $order])</p>
	</header>

	@include('payments.partials.status')

	@if ($pending)
		<div class="lgp-grid">
			<section class="lgp-card" aria-labelledby="lgp-pay-title">
				<h2 id="lgp-pay-title">Podaci za uplatu</h2>
				@if ($details->ready())
					@include('payments.partials.instructions', ['details' => $details])
					<p class="lgp-help">Poziv na broj upišite tačno ovako, sa modelom {{ $details->model }}: po njemu prepoznajemo vašu uplatu. Usluga se uključuje kad administrator vidi uplatu, obično prvog radnog dana.</p>
				@else
					<p>{{ PageText::inline((string) PageText::raw('cenovnik.uplata.nema')) }}</p>
					<p>Iznos: <strong>{{ $details->amountLabel() }}</strong>, poziv na broj: <strong class="lgp-mono">{{ $details->model }} {{ $details->reference }}</strong>.</p>
				@endif
			</section>

			@if ($qrSvg)
				<section class="lgp-card lgp-qr" aria-labelledby="lgp-qr-title">
					<h2 id="lgp-qr-title">Plaćanje telefonom</h2>
					<figure class="lgp-qr__figure">
						<div class="lgp-qr__code" role="img" aria-label="IPS QR kod za uplatu {{ $details->amountLabel() }}, poziv na broj {{ $details->reference }}">{!! $qrSvg !!}</div>
						<figcaption>U aplikaciji svoje banke izaberite „IPS skeniraj” i skenirajte kod. Podaci za uplatu se popunjavaju sami.</figcaption>
					</figure>
				</section>
			@endif
		</div>

		@include('payments.partials.buyer', ['order' => $order])

		<section class="lgp-card lgp-cancel" aria-labelledby="lgp-cancel-title">
			<h2 id="lgp-cancel-title">Rok i otkazivanje</h2>
			@if ($expiresAt)
				<p>Ako uplata ne stigne do {{ $expiresAt->format('j. n. Y.') }}, narudžbina se otkazuje sama.</p>
			@endif
			@can('cancel', $order)
				<form method="post" action="{{ url_slash('moj-nalog/narudzbine/'.$order->id.'/otkazi') }}">
					@csrf
					<button class="lg-btn lg-btn--outline" type="submit">Otkaži narudžbinu</button>
				</form>
			@endcan
		</section>
	@else
		<section class="lgp-card">
			<dl class="lgp-summary">
				<div><dt>Iznos</dt><dd>{{ $details->amountLabel() }}</dd></div>
				<div><dt>Poziv na broj</dt><dd class="lgp-mono">{{ $details->model }} {{ $order->reference }}</dd></div>
				<div><dt>Naručeno</dt><dd>{{ $order->created_at->format('j. n. Y. \u H:i') }}</dd></div>
				@if ($order->paid_at)
					<div><dt>Plaćeno</dt><dd>{{ $order->paid_at->format('j. n. Y. \u H:i') }}</dd></div>
				@endif
				@if ($order->activated_at)
					<div><dt>Usluga uključena</dt><dd>{{ $order->activated_at->format('j. n. Y. \u H:i') }}</dd></div>
				@endif
				@if ($order->receipt_number)
					<div><dt>{{ $order->receipt_type?->label() ?? 'Račun' }}</dt><dd class="lgp-mono">{{ $order->receipt_number }}</dd></div>
				@endif
				@if ($order->refunded_at)
					<div><dt>Refundirano</dt><dd>{{ $order->refunded_at->format('j. n. Y.') }}</dd></div>
				@endif
				@if ($order->status === OrderStatus::Cancelled && $order->cancelled_at)
					<div><dt>Otkazano</dt><dd>{{ $order->cancelled_at->format('j. n. Y. \u H:i') }}</dd></div>
				@endif
				@if ($order->status === OrderStatus::Activated && $order->listing && ! $order->listing->trashed() && $order->listing->isFeatured() && in_array($order->product?->code, [Product::FEATURED_7, Product::FEATURED_30], true))
					<div><dt>Istaknut do</dt><dd>{{ $order->listing->featured_until->format('j. n. Y. \u H:i') }}</dd></div>
				@endif
			</dl>
			@if ($order->status === OrderStatus::Paid)
				<p class="lgp-help">Uplata je primljena, usluga se uključuje uskoro.</p>
			@endif
			@if ($order->status === OrderStatus::Cancelled)
				<p class="lgp-help">Ako ste ipak uplatili, pišite nam preko strane <a href="{{ url_slash('kontakt') }}">Kontakt</a> i navedite poziv na broj.</p>
			@endif
		</section>
	@endif
</div>
@endsection
