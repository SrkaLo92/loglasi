@extends('layouts.app')
@use('App\Enums\ListingStatus')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/posting.css') }}">
@endpush

@php $svc = app(\App\Services\ListingImages::class); @endphp

@section('content')
<div class="pst lg-wrap">
	<header class="pst-head pst-head--row">
		<div>
			<p class="pst-head__back"><a href="{{ url_slash('moj-nalog') }}">Moj nalog</a></p>
			<h1>{{ \App\Support\Seo::get()->getH1() }}</h1>
			@unless ($limits['unlimited'])
				<p class="pst-help">Aktivni oglasi: {{ $limits['used_this_month'] }} od {{ $limits['monthly_limit'] }} besplatnih.</p>
			@endunless
		</div>
		<a class="pst-btn pst-btn--orange" href="{{ url_slash('postavi-oglas') }}">Postavite oglas</a>
	</header>

	@if (session('status'))
		<p class="pst-alert pst-alert--ok" role="status">{{ session('status') }}</p>
	@endif

	<nav class="pst-tabs" aria-label="Status oglasa">
		<ul role="list">
			@foreach ($tabs as $key => $label)
				@continue($key === 'nacrti' && $counts['nacrti'] === 0 && $tab !== 'nacrti')
				<li><a href="{{ url_slash('moj-nalog/oglasi') }}?status={{ $key }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }} <span class="pst-tabs__count">{{ $counts[$key] }}</span></a></li>
			@endforeach
		</ul>
	</nav>

	@if ($listings->isEmpty())
		<div class="pst-card pst-empty">
			<p>{{ match ($tab) {
				'na-cekanju' => 'Nemate oglase koji čekaju odobrenje.',
				'istekli' => 'Nemate istekle oglase.',
				'odbijeni' => 'Nemate odbijene oglase.',
				'nacrti' => 'Nemate nezavršene oglase.',
				default => 'Nemate aktivnih oglasa.',
			} }}</p>
			<a class="pst-btn" href="{{ url_slash('postavi-oglas') }}">Postavite oglas</a>
		</div>
	@else
		<ul class="pst-mine" role="list">
			@foreach ($listings as $item)
				@php
					$cover = $item->images->first();
					$expired = $posting->isExpired($item);
					$recentStats = $recent[$item->id] ?? null;
					$statusLabel = $item->status === ListingStatus::Published && $expired ? 'Istekao' : $item->status->label();
				@endphp
				<li class="pst-mine__item{{ session('posted') === $item->id ? ' is-new' : '' }}" id="oglas-{{ $item->id }}">
					<div class="pst-mine__media">
						@if ($cover)
							<picture>
								<source srcset="{{ $svc->url($cover, 'sm', 'webp') }}" type="image/webp">
								<img src="{{ $svc->url($cover, 'sm', 'jpg') }}" alt="" width="{{ $cover->width ?: 320 }}" height="{{ $cover->height ?: 240 }}" loading="lazy">
							</picture>
						@else
							<span class="pst-mine__ph">{{ \App\Support\Stranice\Ikonice::svg('image', 28) }}</span>
						@endif
					</div>
					<div class="pst-mine__body">
						<p class="pst-mine__status pst-status--{{ $item->status->value }}{{ $expired ? ' pst-status--expired' : '' }}">{{ $statusLabel }}</p>
						<h2 class="pst-mine__title">
							@if ($item->status === ListingStatus::Published && ! $expired)
								<a href="{{ url_slash('oglas/'.$item->slug) }}">{{ $item->title }}</a>
							@else
								{{ $item->title !== '' ? $item->title : 'Oglas bez naslova' }}
							@endif
						</h2>
						<p class="pst-mine__price">{{ $item->priceLabel() }}</p>
						<p class="pst-help">
							{{ $item->category?->name }}@if ($item->location), {{ $item->location->name }}@endif
							@if ($item->status === ListingStatus::Published && $item->expires_at)
								· {{ $expired ? 'istekao' : 'ističe' }} {{ $posting->expiresLabel($item->expires_at) }}
							@endif
						</p>
						@if ($item->status === ListingStatus::Rejected && $item->rejection_reason)
							<p class="pst-mine__reason"><strong>Razlog:</strong> {{ $item->rejection_reason }}</p>
						@endif
						@if ($item->status !== ListingStatus::Draft)
							<p class="pst-mine__stats">
								<span>{{ \App\Support\Stranice\Ikonice::svg('chart', 16) }}{{ number_format($item->views, 0, ',', '.') }} pregleda</span>
								<span>{{ \App\Support\Stranice\Ikonice::svg('phone', 16) }}{{ number_format($item->phone_reveals, 0, ',', '.') }} prikaza broja</span>
								@if ($recentStats)<span class="pst-help">poslednjih 30 dana: {{ (int) $recentStats->views }} / {{ (int) $recentStats->phone_reveals }}</span>@endif
							</p>
						@endif
						<div class="pst-mine__actions">
							@if ($item->status === ListingStatus::Draft)
								<a class="pst-btn pst-btn--sm" href="{{ url_slash('postavi-oglas/'.$item->id.'/osnovno') }}">Nastavi</a>
							@else
								<a class="pst-btn pst-btn--sm pst-btn--line" href="{{ url_slash('moj-nalog/oglasi/'.$item->id.'/izmena') }}">Izmeni</a>
							@endif
							@if ($posting->canRenew($item))
								<form method="post" action="{{ url_slash('moj-nalog/oglasi/'.$item->id.'/obnova') }}">@csrf<button class="pst-btn pst-btn--sm" type="submit">Obnovi besplatno</button></form>
							@endif
							@if ($item->status === ListingStatus::Published && ! $expired)
								<form method="post" action="{{ url_slash('moj-nalog/oglasi/'.$item->id.'/prodato') }}">@csrf<button class="pst-btn pst-btn--sm pst-btn--line" type="submit">Prodato</button></form>
								<form method="post" action="{{ url_slash('moj-nalog/oglasi/'.$item->id.'/deaktivacija') }}">@csrf<button class="pst-btn pst-btn--sm pst-btn--line" type="submit">Skini sa sajta</button></form>
							@endif
							@if ($item->status !== ListingStatus::Draft)
								<a class="pst-link" href="{{ url_slash('moj-nalog/oglasi/'.$item->id.'/statistika') }}">Statistika</a>
							@endif
							<a class="pst-link pst-link--danger" href="{{ url_slash('moj-nalog/oglasi/'.$item->id.'/brisanje') }}">Obriši</a>
						</div>
					</div>
				</li>
			@endforeach
		</ul>
		{{ $listings->links() }}
	@endif
</div>
@endsection
