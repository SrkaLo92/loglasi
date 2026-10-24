{{-- Order status badge. --}}
<span class="lgpay-badge lgpay-badge--{{ $order->status->value }}">{{ $order->status->label() }}</span>
