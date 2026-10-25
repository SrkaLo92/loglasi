@extends('layouts.app')
@include('notifications.partials.head')
@php
	$title = 'Podešavanja obaveštenja | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lgt lg-wrap">
	<h1>Podešavanja obaveštenja</h1>
	<p class="lgn-lead">Izaberite o čemu želite da vas obaveštavamo mejlom na <strong>{{ $user->email }}</strong> i u aplikaciji.</p>

	@include('nalozi.partials.status')

	<section class="lgn-card" aria-labelledby="lgt-izbor">
		<h2 id="lgt-izbor">Šta vam šaljemo</h2>
		<form method="post" action="{{ url_slash('moj-nalog/obavestenja/podesavanja') }}">
			@csrf
			@method('PUT')
			<ul class="lgt-toggles">
				@foreach ($labels as $key => $item)
					<li>
						<label class="lgt-switch" for="obavestenje-{{ $key }}">
							<span class="lgt-switch__text">
								<span class="lgt-switch__label">{{ $item['label'] }}</span>
								<span class="lgn-help" id="obavestenje-{{ $key }}-pomoc">{{ $item['help'] }}</span>
							</span>
							<input id="obavestenje-{{ $key }}" type="checkbox" role="switch" name="{{ $key }}" value="1" @checked($values[$key]) aria-describedby="obavestenje-{{ $key }}-pomoc">
						</label>
					</li>
				@endforeach
			</ul>
			<button class="lgn-btn" type="submit">Sačuvaj podešavanja</button>
		</form>
	</section>

	<p class="lgn-note">Mejlove o nalogu (potvrda adrese, nova lozinka), narudžbinama i uplatama i podsetnike za Biznis paket šaljemo uvek. Pretrage o kojima vas obaveštavamo birate na stranici <a href="{{ url_slash('moj-nalog/pretrage') }}">Sačuvane pretrage</a>.</p>
</div>
@endsection
