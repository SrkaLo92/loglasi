{{-- Zaglavlje sajta po odobrenoj maketi: logo, pretraga, nalog i „Postavite oglas”. --}}
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\Navigacija')
@php
	$lgKategorije = Navigacija::kategorije();
	$lgMesta = Navigacija::mesta();
	$lgUlogovan = auth()->check();
@endphp
<header class="lg-header">
	<div class="lg-wrap lg-header__in">
		@include('partials.logo', ['logoClass' => 'lg-logo'])
		<p class="lg-header__mesto">{{ Ikonice::svg('pin', 14, '2') }}Loznica i okolina</p>
		<form class="lg-search" role="search" action="{{ Navigacija::link('oglasi') }}" method="get">
			<label class="lg-visually-hidden" for="lg-q">Šta tražite?</label>
			<div class="lg-search__polje">
				{{ Ikonice::svg('search', 20, '2') }}
				<input class="lg-search__input" id="lg-q" type="search" name="keyword" value="{{ collect(['keyword', 's', 'q', 'kljucna-rec'])->map(fn ($key) => request($key))->first(fn ($value) => is_string($value) && trim($value) !== '') ?? '' }}" placeholder="Šta tražite? npr. bicikl, stan, Golf" autocomplete="off" enterkeyhint="search" maxlength="100">
			</div>
			@if ($lgKategorije)
				<label class="lg-visually-hidden" for="lg-kat">Kategorija</label>
				<select class="lg-search__izbor lg-search__izbor--kat" id="lg-kat" name="kategorija" data-pick="bar">
					<option value="">Sve kategorije</option>
					@foreach ($lgKategorije as $kat)
						<option value="{{ $kat['slug'] }}" @selected(request('kategorija') === $kat['slug'])>{{ $kat['name'] }}</option>
					@endforeach
				</select>
			@endif
			@if ($lgMesta)
				<label class="lg-visually-hidden" for="lg-mesto">Mesto</label>
				<select class="lg-search__izbor lg-search__izbor--mesto" id="lg-mesto" name="mesto" data-pick="bar">
					<option value="">Loznica i okolina</option>
					@foreach ($lgMesta as $mesto)
						<option value="{{ $mesto['slug'] }}" @selected(request('mesto') === $mesto['slug'])>{{ $mesto['name'] }}</option>
					@endforeach
				</select>
			@endif
			<button class="lg-search__dugme" type="submit">Traži</button>
			<a class="lg-search__filteri" href="{{ Navigacija::link('oglasi') }}" aria-label="Filteri i svi oglasi">{{ Ikonice::svg('filter', 20, '2') }}</a>
		</form>
		<nav class="lg-header__nav" aria-label="Nalog">
			@if ($lgUlogovan)
				<a class="lg-header__nalog" href="{{ Navigacija::link('nalog') }}">{{ Ikonice::svg('user', 20) }}Moj nalog</a>
			@else
				<a class="lg-header__nalog" href="{{ Navigacija::link('prijava') }}">{{ Ikonice::svg('user', 20) }}Prijava</a>
			@endif
			<a class="lg-header__postavi" href="{{ Navigacija::link('postavi') }}">{{ Ikonice::svg('plus', 18) }}Postavite oglas</a>
		</nav>
	</div>
</header>
