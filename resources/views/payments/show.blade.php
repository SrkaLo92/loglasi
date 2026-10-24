@extends('layouts.app')
@include('payments.partials.head')
@use('App\Enums\OrderStatus')
@use('App\Support\Stranice\PageText')
@php
	$title = 'Narudžbina '.$order->reference.' | loglasi.rs';
	$robots = 'noindex, follow';
	$pending = $order->status === OrderStatus::Pending;
@endphp

@section('content')
<div class="lgpay lg-wrap">
	<nav class="lgpay-crumbs" aria-label="Putanja">
		<a href="{{ url_slash('moj-nalog') }}">Moj nalog</a> <span aria-hidden="true">›</span>
		<a href="{{ url_slash('moj-nalog/narudzbine') }}">Moje narudžbine</a> <span aria-hidden="true">›</span>
		<span>{{ $order->reference }}</span>
	</nav>
	<header class="lgpay-head">
		<h1>{{ $pending ? 'Uputstvo za uplatu' : 'Narudžbina '.$order->reference }}</h1>
		<p class="lgpay-what">@include('payments.partials.what', ['order' => $order]) @include('payments.partials.badge', ['order' => $order])</p>
	</header>

	@include('payments.partials.status')
	@if ($order->is_test)
		<p class="lgpay-alert lgpay-alert--warn" role="note"><strong>TEST:</strong> probna porudžbina. Uplata se ne knjiži, a račun je probni.</p>
	@endif

	@if ($pending)
		<div class="lgpay-grid">
			<section class="lgpay-card" aria-labelledby="lgpay-pay-title">
				<h2 id="lgpay-pay-title">Podaci za uplatu</h2>
				@if ($details->ready())
					@include('payments.partials.instructions', ['details' => $details])
					<p class="lgpay-help">Poziv na broj upišite tačno ovako, sa modelom {{ $details->model }}: po njemu prepoznajemo vašu uplatu. Usluga se uključuje kad administrator vidi uplatu, obično prvog radnog dana.</p>
				@else
					<p>{{ PageText::inline((string) PageText::raw('cenovnik.uplata.nema')) }}</p>
					<p>Iznos: <strong>{{ $details->amountLabel() }}</strong>, poziv na broj: <strong class="lgpay-mono">{{ $details->model }} {{ $details->reference }}</strong>.</p>
				@endif
			</section>

			@if ($qrSvg)
				<section class="lgpay-card lgpay-qr" aria-labelledby="lgpay-qr-title">
					<h2 id="lgpay-qr-title">Plaćanje telefonom</h2>
					<figure class="lgpay-qr__figure">
						<div class="lgpay-qr__code" role="img" aria-label="IPS QR kod za uplatu {{ $details->amountLabel() }}, poziv na broj {{ $details->reference }}">{!! $qrSvg !!}</div>
						<figcaption>U aplikaciji svoje banke izaberite „IPS skeniraj” i skenirajte kod. Podaci za uplatu se popunjavaju sami.</figcaption>
					</figure>
				</section>
			@endif
		</div>

		@include('payments.partials.buyer', ['order' => $order])

		<section class="lgpay-card lgpay-cancel" aria-labelledby="lgpay-cancel-title">
			<h2 id="lgpay-cancel-title">Rok i otkazivanje</h2>
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
		<section class="lgpay-card">
			<dl class="lgpay-summary">
				<div><dt>Iznos</dt><dd>{{ $details->amountLabel() }}</dd></div>
				<div><dt>Poziv na broj</dt><dd class="lgpay-mono">{{ $details->model }} {{ $order->reference }}</dd></div>
				<div><dt>Naručeno</dt><dd>{{ $order->created_at->format('j. n. Y. \u H:i') }}</dd></div>
				@if ($order->paid_at)
					<div><dt>Plaćeno</dt><dd>{{ $order->paid_at->format('j. n. Y. \u H:i') }}</dd></div>
				@endif
				@if ($order->activated_at)
					<div><dt>Usluga uključena</dt><dd>{{ $order->activated_at->format('j. n. Y. \u H:i') }}</dd></div>
				@endif
				@if ($order->receipt_number)
					<div><dt>{{ $order->receipt_type?->label() ?? 'Račun' }}</dt><dd class="lgpay-mono">{{ $order->receipt_number }}</dd></div>
				@endif
				@if ($order->refunded_at)
					<div><dt>Refundirano</dt><dd>{{ $order->refunded_at->format('j. n. Y.') }}</dd></div>
				@endif
				@if ($order->status === OrderStatus::Cancelled && $order->cancelled_at)
					<div><dt>Otkazano</dt><dd>{{ $order->cancelled_at->format('j. n. Y. \u H:i') }}</dd></div>
				@endif
				@if ($order->status === OrderStatus::Activated && $order->service_ends_at)
					<div><dt>Usluga traje do</dt><dd>{{ $order->service_ends_at->format('j. n. Y. \u H:i') }}</dd></div>
				@endif
				@if ($order->refund_amount_rsd !== null)
					<div><dt>Vraćen iznos</dt><dd>{{ number_format($order->refund_amount_rsd, 0, ',', '.') }}&nbsp;RSD</dd></div>
				@endif
			</dl>
			@if ($order->status === OrderStatus::Paid)
				<p class="lgpay-help">Uplata je primljena, usluga se uključuje uskoro.</p>
			@endif
			@if ($order->status === OrderStatus::Cancelled)
				<p class="lgpay-help">Ako ste ipak uplatili, pišite nam preko strane <a href="{{ url_slash('kontakt') }}">Kontakt</a> i navedite poziv na broj.</p>
			@endif
		</section>
	@endif
</div>
@endsection
