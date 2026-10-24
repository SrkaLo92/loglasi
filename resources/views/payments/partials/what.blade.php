{{-- What the order is for: product name and, for listing products, the listing. --}}
<span class="lgpay-what__product">{{ $order->product?->name }}</span>
@if ($order->listing)
	<span class="lgpay-what__listing">
		@if ($order->listing->trashed())
			{{ $order->listing->title }} (obrisan oglas)
		@else
			<a href="{{ $order->listing->url() }}">{{ $order->listing->title }}</a>
		@endif
	</span>
@endif
