@extends('layouts.app')
@use('App\Support\Pages\SiteIcons')
@include('notifications.partials.head')
@php
	$title = 'Obaveštenja | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lgt lg-wrap">
	<div class="lgo-head">
		<h1>Obaveštenja</h1>
		<a class="lgo-head__set" href="{{ url_slash('moj-nalog/obavestenja/podesavanja') }}">{{ SiteIcons::svg('settings', 18) }}Podešavanja</a>
	</div>

	@if ($items === [])
		<section class="lgn-card lgt-empty">
			<h2>Još nemate obaveštenja</h2>
			<p>Ovde vam javljamo kad neko sačuva vaš oglas, kad dobijete ocenu, kad oglas bude objavljen i kad stignu novi oglasi za vaše sačuvane pretrage.</p>
			<a class="lgn-btn" href="{{ url_slash('postavi-oglas') }}">Postavite oglas</a>
		</section>
	@else
		<ul class="lgo-list">
			@foreach ($items as $n)
				<li>
					<a class="lgo-item{{ $n['unread'] ? ' is-new' : '' }}" href="{{ $n['url'] }}">
						<span class="lgo-item__icon lg-tone--{{ $n['tone'] }}">{{ SiteIcons::svg($n['icon'], 20) }}</span>
						<span class="lgo-item__text">
							<span class="lgo-item__title">{{ $n['title'] }}</span>
							@if ($n['body'])<span class="lgo-item__body">{{ $n['body'] }}</span>@endif
							<time class="lgo-item__time" datetime="{{ $n['at']->toIso8601String() }}">{{ \App\Support\Search\TimeAgo::format($n['at']) }}</time>
						</span>
						@if ($n['unread'])<span class="lgo-item__dot" aria-label="Novo"></span>@endif
					</a>
				</li>
			@endforeach
		</ul>
		@if ($next)
			<p class="lgo-more"><a class="lgn-btn lgn-btn--line" href="{{ $next }}">Starija obaveštenja</a></p>
		@endif
	@endif
</div>
@endsection
