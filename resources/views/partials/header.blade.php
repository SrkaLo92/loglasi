{{-- Zaglavlje sajta po odobrenoj maketi: logo, pretraga, nalog i „Postavite oglas”. --}}
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')
@php
	$lgKategorije = Navigation::kategorije();
	$lgMesta = Navigation::mesta();
	$lgUlogovan = auth()->check();
	$lgObavestenja = $lgUlogovan ? \App\Services\Notifications\InAppNotifications::badge() : 0;
	$lgPoruke = max(0, (int) ($unreadMessages ?? 0));
	$lgBroj = fn (int $n) => $n > 99 ? '99+' : (string) $n;
@endphp
<header class="lg-header{{ auth()->check() ? ' lg-header--nalog' : '' }}">
	<div class="lg-wrap lg-header__in">
		@include('partials.logo', ['logoClass' => 'lg-logo'])
		<p class="lg-header__mesto">{{ SiteIcons::svg('pin', 14, '2') }}Loznica i okolina</p>
		<form class="lg-search" role="search" action="{{ Navigation::link('oglasi') }}" method="get">
			<label class="lg-visually-hidden" for="lg-q">Šta tražite?</label>
			<div class="lg-search__polje">
				{{ SiteIcons::svg('search', 20, '2') }}
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
			<a class="lg-search__filteri" href="{{ Navigation::link('oglasi') }}" aria-label="Filteri i svi oglasi">{{ SiteIcons::svg('filter', 20, '2') }}</a>
		</form>
		@if ($lgUlogovan)
			<a class="lg-header__zvono" href="{{ url_slash('moj-nalog/obavestenja') }}" aria-label="Obaveštenja{{ $lgObavestenja ? ', '.$lgObavestenja.' novih' : '' }}">{{ SiteIcons::svg('bell', 22) }}@if ($lgObavestenja)<span class="lg-header__broj" aria-hidden="true">{{ $lgBroj($lgObavestenja) }}</span>@endif</a>
		@endif
		<nav class="lg-header__nav" aria-label="Nalog">
			@if ($lgUlogovan)
				<a class="lg-header__nalog" href="{{ Navigation::link('poruke') }}" aria-label="Poruke{{ $lgPoruke ? ', '.$lgPoruke.' nepročitanih' : '' }}"><span class="lg-header__ik">{{ SiteIcons::svg('chat', 20) }}@if ($lgPoruke)<span class="lg-header__broj" aria-hidden="true">{{ $lgBroj($lgPoruke) }}</span>@endif</span><span class="lg-header__tekst">Poruke</span></a>
				<a class="lg-header__nalog" href="{{ Navigation::link('nalog') }}">{{ SiteIcons::svg('user', 20) }}Moj nalog</a>
			@else
				<a class="lg-header__nalog" href="{{ Navigation::link('prijava') }}">{{ SiteIcons::svg('user', 20) }}Prijava</a>
			@endif
			<a class="lg-header__postavi" href="{{ Navigation::link('postavi') }}">{{ SiteIcons::svg('plus', 18) }}Postavite oglas</a>
		</nav>
	</div>
</header>
