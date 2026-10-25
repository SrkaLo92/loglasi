{{-- Links to the order's documents: firm invoice / credit note (printable) or the fiscal receipt check on suf.purs.gov.rs. --}}
@use('App\Enums\BuyerType')
@use('App\Enums\OrderStatus')
@use('App\Enums\ReceiptType')
@php
	$firm = $order->buyer_type === BuyerType::Firm;
	$links = array_filter([
		$firm && $order->receipt_type === ReceiptType::Invoice && $order->receipt_number ? ['Preuzmi račun', url_slash('moj-nalog/narudzbine/'.$order->id.'/racun'), false] : null,
		$firm && $order->status === OrderStatus::Refunded && $order->refund_receipt_number ? ['Preuzmi knjižno odobrenje', url_slash('moj-nalog/narudzbine/'.$order->id.'/odobrenje'), false] : null,
		! $firm && $order->receipt_url ? ['Proveri fiskalni račun', $order->receipt_url, true] : null,
		! $firm && $order->refund_receipt_url ? ['Proveri račun refundacije', $order->refund_receipt_url, true] : null,
	]);
@endphp
@if ($links !== [])
	<p class="lgpay-docs">
		@foreach ($links as [$label, $href, $external])
			<a class="lg-btn lg-btn--outline" href="{{ $href }}" @if ($external) target="_blank" rel="noopener" @endif>{{ $label }}</a>
		@endforeach
	</p>
@endif
