@extends('layouts.app')
@include('payments.partials.head')
@php
	$title = 'Moje narudžbine | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgp lg-wrap">
	<nav class="lgp-crumbs" aria-label="Putanja">
		<a href="{{ url_slash('moj-nalog') }}">Moj nalog</a> <span aria-hidden="true">›</span> <span>Moje narudžbine</span>
	</nav>
	<header class="lgp-head">
		<h1>Moje narudžbine</h1>
		<p class="lgp-lead">Isticanje i podizanje oglasa i Biznis paket plaćaju se uplatom na račun. Usluga se uključuje čim administrator vidi uplatu.</p>
	</header>

	@include('payments.partials.status')

	@if ($orders->isEmpty())
		<section class="lgp-card lgp-empty">
			<p>Još nemate narudžbina.</p>
			<p>Oglas možete da istaknete ili podignete iz liste svojih oglasa. Cene su na strani <a href="{{ url_slash('cenovnik') }}">Cenovnik</a>.</p>
		</section>
	@else
		<ul class="lgp-orders">
			@foreach ($orders as $order)
				<li class="lgp-card lgp-order">
					<div class="lgp-order__main">
						<p class="lgp-what">@include('payments.partials.what', ['order' => $order])</p>
						<p class="lgp-order__meta">
							Poziv na broj <strong>{{ $order->reference }}</strong>
							· {{ $order->created_at->format('j. n. Y.') }}
						</p>
					</div>
					<div class="lgp-order__side">
						<p class="lgp-order__amount">{{ number_format($order->amount_rsd, 0, ',', '.') }}&nbsp;RSD</p>
						@include('payments.partials.badge', ['order' => $order])
						<a class="lgp-link" href="{{ url_slash('moj-nalog/narudzbine/'.$order->id) }}">{{ $order->isPending() ? 'Uputstvo za uplatu' : 'Detalji' }}<span class="lg-visually-hidden"> za narudžbinu {{ $order->reference }}</span></a>
					</div>
				</li>
			@endforeach
		</ul>
		@if ($orders->hasPages())
			<nav class="lgp-pager" aria-label="Strane">
				@if ($orders->previousPageUrl())<a class="lg-btn lg-btn--outline" href="{{ $orders->previousPageUrl() }}" rel="prev">Novije</a>@endif
				@if ($orders->nextPageUrl())<a class="lg-btn lg-btn--outline" href="{{ $orders->nextPageUrl() }}" rel="next">Starije</a>@endif
			</nav>
		@endif
	@endif
</div>
@endsection
