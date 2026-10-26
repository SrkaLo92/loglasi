@extends('layouts.app')
@include('accounts.partials.head')
@php
	use App\Enums\ListingStatus;
	use App\Support\Pages\SiteIcons;

	$title = 'Moj nalog | loglasi.rs';
	$robots = 'noindex, follow';
	$images = app(\App\Services\ListingImages::class);
	$firstName = \Illuminate\Support\Str::before(trim($user->firm?->name ?: $user->name), ' ') ?: $user->name;
	$usedPct = $stats['limit'] ? min(100, (int) round($stats['active'] / max(1, $stats['limit']) * 100)) : null;
@endphp

@section('content')
<div class="lgn lga lg-wrap">
	<header class="lga-hero">
		<div class="lga-hero__text">
			<h1>Zdravo, {{ $firstName }}</h1>
			<p>{{ $tasks === [] ? 'Sve je u redu. Evo kako stoje vaši oglasi.' : 'Evo šta čeka na vas.' }}</p>
		</div>
		<a class="lga-btn lga-btn--cta" href="{{ url_slash('postavi-oglas') }}">{{ SiteIcons::svg('plus', 18) }} Postavite oglas</a>
	</header>

	@include('accounts.partials.status')

	@unless ($user->hasVerifiedEmail())
		<div class="lga-task lga-task--warn">
			<span class="lga-task__icon">{{ SiteIcons::svg('mail', 20) }}</span>
			<p>Potvrdite e-poštu: kliknite na link koji smo poslali na <strong>{{ $user->email }}</strong>.</p>
			<form method="post" action="{{ url_slash('potvrda-emaila/ponovo') }}">
				@csrf
				<button class="lga-task__action" type="submit">Pošalji ponovo</button>
			</form>
		</div>
	@endunless

	@if ($tasks !== [])
		<section class="lga-tasks" aria-label="Zahteva pažnju">
			@foreach ($tasks as $task)
				<a class="lga-task lga-task--{{ $task['tone'] }}" href="{{ $task['url'] }}">
					<span class="lga-task__icon">{{ SiteIcons::svg($task['icon'], 20) }}</span>
					<p>{{ $task['text'] }}</p>
					<span class="lga-task__action">{{ $task['action'] }} {{ SiteIcons::svg('chevron-r', 16) }}</span>
				</a>
			@endforeach
		</section>
	@endif

	<section class="lga-stats" aria-label="Vaši oglasi u brojevima">
		<a class="lga-stat lga-stat--wide" href="{{ url_slash('moj-nalog/oglasi') }}">
			<span class="lga-stat__label">Aktivni oglasi</span>
			@if ($stats['limit'])
				<strong class="lga-stat__value">{{ $stats['active'] }} <small>od {{ $stats['limit'] }}</small></strong>
				<span class="lga-meter" role="meter" aria-valuemin="0" aria-valuemax="{{ $stats['limit'] }}" aria-valuenow="{{ $stats['active'] }}" aria-label="Iskorišćeno mesta za oglase"><span style="width: {{ $usedPct }}%"></span></span>
				<span class="lga-stat__hint">
					@if ($stats['remaining'] > 0)
						Još {{ $stats['remaining'] }} besplatno. Mesto se oslobađa kad oglas istekne, prodate ga ili obrišete.
					@else
						Popunili ste sva mesta. Označite prodate oglase ili obrišite stare da biste postavili nove.
					@endif
				</span>
			@else
				<strong class="lga-stat__value">{{ $stats['active'] }}</strong>
				<span class="lga-stat__hint">Vaš paket nema ograničenje broja oglasa.</span>
			@endif
			@if ($stats['plus_until'])
				<span class="lga-chip">{{ SiteIcons::svg('badge', 16) }} Plus prodavac do {{ $stats['plus_until']->format('j. n. Y.') }}</span>
			@endif
		</a>
		<div class="lga-stat">
			<span class="lga-stat__label">{{ SiteIcons::svg('eye', 18) }} Pregledi</span>
			<strong class="lga-stat__value">{{ number_format($stats['views'], 0, ',', '.') }}</strong>
			<span class="lga-stat__hint">poslednjih 30 dana</span>
		</div>
		<div class="lga-stat">
			<span class="lga-stat__label">{{ SiteIcons::svg('phone', 18) }} Pozivi</span>
			<strong class="lga-stat__value">{{ number_format($stats['reveals'], 0, ',', '.') }}</strong>
			<span class="lga-stat__hint">prikaza broja, 30 dana</span>
		</div>
		<div class="lga-stat">
			<span class="lga-stat__label">{{ SiteIcons::svg('heart', 18) }} Sačuvali</span>
			<strong class="lga-stat__value">{{ number_format($stats['saved'], 0, ',', '.') }}</strong>
			<span class="lga-stat__hint">puta su vaši oglasi u omiljenim</span>
		</div>
	</section>

	<section class="lga-card" aria-labelledby="lga-listings">
		<div class="lga-card__head">
			<h2 id="lga-listings">Vaši oglasi</h2>
			@if ($recent->isNotEmpty())
				<a href="{{ url_slash('moj-nalog/oglasi') }}">Svi oglasi {{ SiteIcons::svg('chevron-r', 16) }}</a>
			@endif
		</div>
		@if ($recent->isEmpty())
			<div class="lga-empty">
				<span class="lga-empty__icon">{{ SiteIcons::svg('image', 28) }}</span>
				<p><strong>Još nemate oglasa.</strong> Postavljanje je besplatno i traje par minuta.</p>
				<a class="lga-btn lga-btn--cta" href="{{ url_slash('postavi-oglas') }}">Postavite prvi oglas</a>
			</div>
		@else
			<ul class="lga-list" role="list">
				@foreach ($recent as $item)
					@php
						$cover = $item->images->first();
						$expired = $item->isExpired();
						$label = $expired ? 'Istekao' : $item->status->label();
						$tone = match (true) {
							$item->isActive() => 'ok',
							$item->status === ListingStatus::Rejected => 'bad',
							$item->status === ListingStatus::Pending => 'wait',
							default => 'off',
						};
					@endphp
					<li>
						<a class="lga-row" href="{{ $item->status === ListingStatus::Rejected ? url_slash('moj-nalog/oglasi').'?status=odbijeni' : url_slash('oglas/'.$item->slug) }}">
							<span class="lga-row__img">
								@if ($cover)
									<img src="{{ $images->url($cover, 'sm', 'webp') }}" alt="" width="72" height="54" loading="lazy">
								@else
									{{ SiteIcons::svg('image', 20) }}
								@endif
							</span>
							<span class="lga-row__main">
								<strong>{{ $item->title }}</strong>
								<span>{{ $item->price ? $item->priceLabel() : 'Po dogovoru' }}@if ($item->expires_at && $item->isActive()) · ističe {{ $item->expires_at->format('j. n.') }}@endif</span>
							</span>
							<span class="lga-pill lga-pill--{{ $tone }}">{{ $label }}</span>
						</a>
					</li>
				@endforeach
			</ul>
		@endif
	</section>

	@if ($user->isFirm())
		<a class="lga-banner" href="{{ url_slash('moj-nalog/firma') }}">
			<span class="lga-task__icon">{{ SiteIcons::svg('shop', 20) }}</span>
			<span><strong>Profil firme</strong> Paket, podaci firme i statistika posete.</span>
			{{ SiteIcons::svg('chevron-r', 18) }}
		</a>
	@endif

	@include('partials.account-tabs', ['acctMenuList' => true])
</div>
@endsection
