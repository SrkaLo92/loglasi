@extends('layouts.app')
@include('notifications.partials.head')
@php
	$title = 'Obaveštenja | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgn lgt lg-wrap">
	<a class="lgt-back" href="{{ url_slash('moj-nalog') }}">← Moj nalog</a>
	<h1>Obaveštenja</h1>
	<p class="lgn-lead">Izaberite o čemu želite da vas obaveštavamo mejlom na <strong>{{ $user->email }}</strong> i u aplikaciji.</p>

	@include('nalozi.partials.status')

	<section class="lgn-card" aria-labelledby="lgt-izbor">
		<h2 id="lgt-izbor">Šta vam šaljemo</h2>
		<form method="post" action="{{ url_slash('moj-nalog/obavestenja') }}">
			@csrf
			@method('PUT')
			<ul class="lgt-toggles">
				@foreach ($labels as $key => $item)
					<li>
						<label class="lgn-check" for="obavestenje-{{ $key }}">
							<input id="obavestenje-{{ $key }}" type="checkbox" name="{{ $key }}" value="1" @checked($values[$key]) aria-describedby="obavestenje-{{ $key }}-pomoc">
							<span>{{ $item['label'] }}</span>
						</label>
						<p class="lgn-help" id="obavestenje-{{ $key }}-pomoc">{{ $item['help'] }}</p>
					</li>
				@endforeach
			</ul>
			<button class="lgn-btn" type="submit">Sačuvaj podešavanja</button>
		</form>
	</section>

	<p class="lgn-note">Mejlove o nalogu (potvrda adrese, nova lozinka), narudžbinama i uplatama i podsetnike za Biznis paket šaljemo uvek. Pretrage o kojima vas obaveštavamo birate na stranici <a href="{{ url_slash('moj-nalog/pretrage') }}">Sačuvane pretrage</a>.</p>
</div>
@endsection
