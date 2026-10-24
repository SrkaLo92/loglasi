@extends('layouts.app')
@include('notifications.partials.head')
@php
	$title = 'Sačuvane pretrage | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lgt lg-wrap">
	<h1>Sačuvane pretrage</h1>
	<p class="lgn-lead">Kad stigne nov oglas koji odgovara pretrazi, javljamo vam mejlom i u aplikaciji, najviše jednom dnevno po pretrazi.</p>

	@include('nalozi.partials.status')
	@if ($errors->any())
		<p class="lgn-alert lgn-alert--warn" role="alert">{{ $errors->first() }}</p>
	@endif

	@unless ($alertsOn)
		<div class="lgn-alert lgn-alert--warn">
			<p>Obaveštenja za sačuvane pretrage su isključena. <a href="{{ url_slash('moj-nalog/obavestenja') }}">Uključite ih u podešavanjima</a>.</p>
		</div>
	@endunless

	@if ($searches->isEmpty())
		<section class="lgn-card lgt-empty">
			<h2>Još nemate sačuvanih pretraga</h2>
			<p>Pretražite oglase i na stranici rezultata kliknite „Sačuvaj pretragu”.</p>
			<a class="lgn-btn" href="{{ url_slash('oglasi') }}">Pretraži oglase</a>
		</section>
	@else
		<ul class="lgt-list">
			@foreach ($searches as $search)
				<li class="lgn-card lgt-item">
					<div class="lgt-item__head">
						<h2 class="lgt-item__name">{{ $search->name ?: 'Sačuvana pretraga' }}</h2>
						<a href="{{ $search->url() }}">Prikaži rezultate</a>
					</div>
					<p class="lgt-item__meta">
						Sačuvano {{ $search->created_at->format('j. n. Y.') }}
						@if ($search->notify)
							· obaveštenja uključena
						@else
							· <span class="lgt-badge-off">obaveštenja isključena</span>
						@endif
						@if ($search->last_notified_at)
							· poslednje {{ $search->last_notified_at->timezone('Europe/Belgrade')->format('j. n. Y. \u H:i') }}
						@endif
					</p>
					<div class="lgt-item__actions">
						<form class="lgt-rename" method="post" action="{{ url_slash('moj-nalog/pretrage/'.$search->id) }}">
							@csrf
							@method('PATCH')
							<div class="lgn-field">
								<label for="pretraga-{{ $search->id }}-naziv">Naziv</label>
								<input id="pretraga-{{ $search->id }}-naziv" type="text" name="name" value="{{ $search->name }}" maxlength="80">
							</div>
							<button class="lgn-btn lgn-btn--line" type="submit">Preimenuj</button>
						</form>
						<form method="post" action="{{ url_slash('moj-nalog/pretrage/'.$search->id) }}">
							@csrf
							@method('PATCH')
							<input type="hidden" name="notify" value="{{ $search->notify ? 0 : 1 }}">
							<button class="lgn-btn lgn-btn--line" type="submit">{{ $search->notify ? 'Isključi obaveštenja' : 'Uključi obaveštenja' }}</button>
						</form>
						<form method="post" action="{{ url_slash('moj-nalog/pretrage/'.$search->id) }}">
							@csrf
							@method('DELETE')
							<button class="lgn-btn lgn-btn--danger" type="submit">Obriši</button>
						</form>
					</div>
				</li>
			@endforeach
		</ul>
		<p class="lgn-note">Možete sačuvati najviše {{ $max }} pretraga.</p>
	@endif
</div>
@endsection
