{{-- Site footer, structured like the old live site: news line, contact, useful links, categories, newest listings and social. --}}
@use('App\Support\Home\FooterSections')
@use('App\Support\Phone')
@use('App\Support\Settings')
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')
@php
	$lgFooterLinks = [
		'about' => 'O nama',
		'contact' => 'Kontakt',
		'faq' => 'Česta pitanja',
		'pricing' => 'Cenovnik',
	];
	$lgLegalLinks = [
		'privacy' => 'Politika privatnosti',
		'data_deletion' => 'Brisanje ličnih podataka',
		'terms' => 'Uslovi korišćenja',
		'rules' => 'Pravila oglašavanja',
		'payment_terms' => 'Uslovi kupovine i plaćanja',
		'withdrawal' => 'Odustanak od ugovora',
		'complaints' => 'Reklamacije i povraćaj novca',
	];
	$lgCurrent = url_slash(request()->path());
	// No "post a listing" band while posting or inside the account: the user is already doing it.
	$lgFooterCta = ! request()->is('postavi-oglas*', 'moj-nalog*', 'o-nama*');
	$lgFooter = FooterSections::get();
	$lgPhone = (string) Settings::get('contact_phone');
	$lgEmail = (string) Settings::get('contact_email');
	$lgAddress = (string) Settings::get('contact_address');
	$lgSocial = [
		'Facebook' => ['url' => 'https://www.facebook.com/profile.php?id=61571911496798', 'path' => '<path d="M15 3h-3a4 4 0 0 0-4 4v3H5v4h3v7h4v-7h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>'],
		'Instagram' => ['url' => 'https://www.instagram.com/loglasi.rs/', 'path' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".5"/>'],
		'TikTok' => ['url' => 'https://www.tiktok.com/@loglasi.rs', 'path' => '<path d="M14 3v11a3.5 3.5 0 1 1-3.5-3.5M14 3c.5 3 2.5 4.5 5 4.5"/>'],
	];
@endphp
<footer class="lg-footer">
	@if ($lgFooterCta)
		<div class="lg-wrap lg-footer__top">
			<div>
				<p class="lg-footer__news">Imate nešto za prodaju?</p>
				<p class="lg-footer__subtitle">Postavite oglas besplatno, za par minuta, i javite se kupcima iz svog kraja.</p>
			</div>
			<a class="lg-btn lg-btn--orange lg-footer__post" href="{{ url_slash('/postavi-oglas') }}">{{ SiteIcons::svg('plus', 18) }}Postavite oglas</a>
		</div>
	@endif

	<div class="lg-wrap lg-footer__in">
		<div class="lg-footer__col lg-footer__brand">
			@include('partials.logo', ['logoClass' => 'lg-footer__logo', 'logoTheme' => 'light'])
			<p class="lg-footer__text">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.</p>
			<ul class="lg-footer__contact">
				@if ($lgAddress !== '')
					<li><span>{{ $lgAddress }}</span></li>
				@endif
				@if ($lgPhone !== '')
					<li><a href="tel:{{ $lgPhone }}">{{ Phone::display($lgPhone) }}</a></li>
				@endif
				@if ($lgEmail !== '')
					<li><a href="mailto:{{ $lgEmail }}">{{ $lgEmail }}</a></li>
				@endif
			</ul>
		</div>

		<nav class="lg-footer__col lg-footer__groups" aria-label="Linkovi u podnožju">
			<details class="lg-footer__group" open data-lg-fold>
				<summary class="lg-footer__title">Korisni linkovi</summary>
				<ul class="lg-footer__list">
					@foreach ($lgFooterLinks as $key => $label)
						@php($href = Navigation::link($key))
						<li><a href="{{ $href }}" @if ($href === $lgCurrent) aria-current="page" @endif>{{ $label }}</a></li>
					@endforeach
				</ul>
			</details>
			<details class="lg-footer__group" open data-lg-fold>
				<summary class="lg-footer__title">Pravne informacije</summary>
				<ul class="lg-footer__list">
					@foreach ($lgLegalLinks as $key => $label)
						@php($href = Navigation::link($key))
						<li><a href="{{ $href }}" @if ($href === $lgCurrent) aria-current="page" @endif>{{ $label }}</a></li>
					@endforeach
				</ul>
			</details>
		</nav>

		<nav class="lg-footer__col" aria-label="Kategorije">
			<details class="lg-footer__group" open data-lg-fold>
				<summary class="lg-footer__title">Kategorije</summary>
				<ul class="lg-footer__list">
					@foreach ($lgFooter['categories'] as $item)
						<li><a href="{{ $item['url'] }}">{{ $item['name'] }}</a></li>
					@endforeach
					<li><a class="lg-footer__all" href="{{ url_slash('/oglasi') }}">Svi oglasi</a></li>
				</ul>
			</details>
		</nav>

		<div class="lg-footer__col lg-footer__last">
			<h2 class="lg-footer__title lg-footer__title--latest" id="lg-footer-latest">Upravo objavljeno</h2>
			<ul class="lg-footer__list lg-footer__latest" data-lg-latest="{{ url_slash('/podnozje/najnoviji-oglasi') }}" aria-labelledby="lg-footer-latest">
				<li><a class="lg-footer__all" href="{{ url_slash('/oglasi') }}">Pogledajte sve oglase</a></li>
			</ul>
			<h2 class="lg-footer__title lg-footer__title--social">Pratite nas</h2>
			<ul class="lg-footer__social">
				@foreach ($lgSocial as $name => $social)
					<li><a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="loglasi.rs na mreži {{ $name }} (otvara se u novom prozoru)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $social['path'] !!}</svg></a></li>
				@endforeach
			</ul>
		</div>
	</div>

	<div class="lg-wrap lg-footer__bottom">
		<p>© {{ now()->year }} loglasi.rs · Vaš lokalni portal za kupovinu, prodaju i razmenu. Sva prava zadržana.</p>
		<button type="button" class="lg-footer__cookies" data-cookies-change hidden>Podešavanje kolačića</button>
		<a href="https://alphasoft.dev/" target="_blank" rel="noopener">Alphasoft Development</a>
	</div>
</footer>
<script>
(function () {
	// Link groups fold on phones and stay open on bigger screens.
	if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) {
		Array.prototype.forEach.call(document.querySelectorAll('[data-lg-fold]'), function (d) { d.removeAttribute('open'); });
	}
})();
(function () {
	var list = document.querySelector('[data-lg-latest]');
	if (!list || !window.fetch) return;
	var load = function () {
		fetch(list.getAttribute('data-lg-latest'), {headers: {Accept: 'application/json'}})
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (json) {
				if (!json || !json.data || !json.data.length) return;
				var items = document.createDocumentFragment();
				json.data.forEach(function (item) {
					var li = document.createElement('li');
					var a = document.createElement('a');
					var title = document.createElement('span');
					var price = document.createElement('span');
					a.href = item.url;
					a.className = 'lg-footer__listing';
					title.textContent = item.title;
					price.textContent = item.price;
					price.className = 'lg-footer__price';
					a.appendChild(title);
					a.appendChild(price);
					li.appendChild(a);
					items.appendChild(li);
				});
				list.insertBefore(items, list.firstChild);
			})
			.catch(function () {});
	};
	if ('IntersectionObserver' in window) {
		var seen = new IntersectionObserver(function (entries) {
			if (entries[0].isIntersecting) { seen.disconnect(); load(); }
		}, {rootMargin: '400px'});
		seen.observe(list);
	} else {
		load();
	}
})();
</script>
