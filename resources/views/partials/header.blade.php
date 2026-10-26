{{-- Site header per the approved mock-up: logo, search, account and "Postavite oglas". --}}
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')
@php
	$lgCategories = Navigation::categories();
	$lgPlaces = Navigation::places();
	$lgLoggedIn = auth()->check();
	$lgNotifications = $lgLoggedIn ? \App\Services\Notifications\InAppNotifications::badge() : 0;
	$lgMessages = max(0, (int) ($unreadMessages ?? 0));
	$lgCount = fn (int $n) => $n > 99 ? '99+' : (string) $n;
@endphp
<header class="lg-header{{ auth()->check() ? ' lg-header--account' : '' }}">
	<div class="lg-wrap lg-header__in">
		@include('partials.logo', ['logoClass' => 'lg-logo'])
		<p class="lg-header__place">{{ SiteIcons::svg('pin', 14, '2') }}Loznica i okolina</p>
		<form class="lg-search" role="search" action="{{ Navigation::link('listings') }}" method="get">
			<label class="lg-visually-hidden" for="lg-q">Šta tražite?</label>
			<div class="lg-search__field">
				{{ SiteIcons::svg('search', 20, '2') }}
				<input class="lg-search__input" id="lg-q" type="search" name="keyword" value="{{ collect(['keyword', 's', 'q', 'kljucna-rec'])->map(fn ($key) => request($key))->first(fn ($value) => is_string($value) && trim($value) !== '') ?? '' }}" placeholder="Šta tražite? npr. bicikl, stan, Golf" autocomplete="off" enterkeyhint="search" maxlength="100">
			</div>
			@if ($lgCategories)
				<label class="lg-visually-hidden" for="lg-cat">Kategorija</label>
				<select class="lg-search__pick lg-search__pick--cat" id="lg-cat" name="kategorija" data-pick="bar">
					<option value="">Sve kategorije</option>
					@foreach ($lgCategories as $cat)
						<option value="{{ $cat['slug'] }}" @selected(request('kategorija') === $cat['slug'])>{{ $cat['name'] }}</option>
					@endforeach
				</select>
			@endif
			@if ($lgPlaces)
				<label class="lg-visually-hidden" for="lg-place">Mesto</label>
				<select class="lg-search__pick lg-search__pick--place" id="lg-place" name="mesto" data-pick="bar">
					<option value="">Loznica i okolina</option>
					@foreach ($lgPlaces as $place)
						<option value="{{ $place['slug'] }}" @selected(request('mesto') === $place['slug'])>{{ $place['name'] }}</option>
					@endforeach
				</select>
			@endif
			<button class="lg-search__button" type="submit">Traži</button>
			<a class="lg-search__filters" href="{{ Navigation::link('listings') }}" aria-label="Filteri i svi oglasi">{{ SiteIcons::svg('filter', 20, '2') }}</a>
		</form>
		@if ($lgLoggedIn)
			<a class="lg-header__bell" href="{{ url_slash('moj-nalog/obavestenja') }}" aria-label="Obaveštenja{{ $lgNotifications ? ', '.$lgNotifications.' novih' : '' }}">{{ SiteIcons::svg('bell', 22) }}@if ($lgNotifications)<span class="lg-header__num" aria-hidden="true">{{ $lgCount($lgNotifications) }}</span>@endif</a>
		@endif
		<nav class="lg-header__nav" aria-label="Nalog">
			@if ($lgLoggedIn)
				<a class="lg-header__account" href="{{ Navigation::link('messages') }}" aria-label="Poruke{{ $lgMessages ? ', '.$lgMessages.' nepročitanih' : '' }}"><span class="lg-header__ico">{{ SiteIcons::svg('chat', 20) }}@if ($lgMessages)<span class="lg-header__num" aria-hidden="true">{{ $lgCount($lgMessages) }}</span>@endif</span><span class="lg-header__text">Poruke</span></a>
				<a class="lg-header__account" href="{{ Navigation::link('account') }}">{{ SiteIcons::svg('user', 20) }}Moj nalog</a>
			@else
				<a class="lg-header__account" href="{{ Navigation::link('login') }}">{{ SiteIcons::svg('user', 20) }}Prijava</a>
			@endif
			<a class="lg-header__post" href="{{ Navigation::link('post') }}">{{ SiteIcons::svg('plus', 18) }}Postavite oglas</a>
		</nav>
	</div>
</header>
