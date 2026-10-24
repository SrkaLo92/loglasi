{{-- Buyer on the pro-forma (predračun): firm name, PIB, MB; seller tax data when the admin entered it. --}}
@use('App\Enums\BuyerType')
@use('App\Support\Settings')
@php
	$sellerPib = trim((string) Settings::get('payment_pib', ''));
	$sellerMb = trim((string) Settings::get('payment_mb', ''));
@endphp
<section class="lgpay-card" aria-labelledby="lgpay-buyer-title">
	<h2 id="lgpay-buyer-title">Predračun</h2>
	<dl class="lgpay-summary">
		<div><dt>Broj</dt><dd class="lgpay-mono">{{ $order->reference }}</dd></div>
		<div><dt>Datum</dt><dd>{{ $order->created_at->format('j. n. Y.') }}</dd></div>
		@if ($sellerPib !== '')<div><dt>PIB prodavca</dt><dd class="lgpay-mono">{{ $sellerPib }}</dd></div>@endif
		@if ($sellerMb !== '')<div><dt>MB prodavca</dt><dd class="lgpay-mono">{{ $sellerMb }}</dd></div>@endif
		@if ($order->buyer_type === BuyerType::Firm)
			<div><dt>Kupac</dt><dd>{{ $order->buyer_name }}</dd></div>
			<div><dt>PIB kupca</dt><dd class="lgpay-mono">{{ $order->buyer_pib }}</dd></div>
			<div><dt>MB kupca</dt><dd class="lgpay-mono">{{ $order->buyer_mb }}</dd></div>
		@else
			<div><dt>Kupac</dt><dd>{{ $order->user?->name }} (fizičko lice)</dd></div>
		@endif
		<div><dt>Usluga</dt><dd>{{ $order->product?->name }}</dd></div>
		<div class="lgpay-summary__total"><dt>Za uplatu</dt><dd>{{ number_format($order->amount_rsd, 2, ',', '.') }}&nbsp;RSD</dd></div>
	</dl>
	<p class="lgpay-help">{{ \App\Support\Payments\PaymentDetails::vatNote() }} {{ $order->buyer_type === BuyerType::Firm ? 'Račun na firmu šaljemo e-poštom posle uplate.' : 'Fiskalni račun šaljemo e-poštom istog dana kad vidimo uplatu.' }}</p>
</section>
