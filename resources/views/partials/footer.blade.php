{{-- Site footer, structured like the old live site: news line, contact, useful links, categories, newest listings and social. --}}
@use('App\Support\Home\FooterSections')
@use('App\Support\Phone')
@use('App\Support\Settings')
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\Navigacija')
@php
	$lgFooterLinks = [
		'o_nama' => 'O nama',
		'kontakt' => 'Kontakt',
		'faq' => 'Česta pitanja',
		'cenovnik' => 'Cenovnik',
	];
	$lgLegalLinks = [
		'privatnost' => 'Politika privatnosti',
		'brisanje' => 'Brisanje ličnih podataka',
		'uslovi' => 'Uslovi korišćenja',
		'placanje' => 'Uslovi kupovine i plaćanja',
		'odustanak' => 'Odustanak od ugovora',
		'reklamacije' => 'Reklamacije i povraćaj novca',
	];
	$lgCurrent = url_slash(request()->path());
	// No "post a listing" band while posting or inside the account: the user is already doing it.
	$lgFooterCta = ! request()->is('postavi-oglas*', 'moj-nalog*');
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
		<div class="lg-wrap lg-footer__vrh">
			<div>
				<p class="lg-footer__vesti">Imate nešto za prodaju?</p>
				<p class="lg-footer__podnaslov">Postavite oglas besplatno, za par minuta, i javite se kupcima iz svog kraja.</p>
			</div>
			<a class="lg-btn lg-btn--orange lg-footer__postavi" href="{{ url_slash('/postavi-oglas') }}">{{ Ikonice::svg('plus', 18) }}Postavite oglas</a>
		</div>
	@endif

	<div class="lg-wrap lg-footer__in">
		<div class="lg-footer__col lg-footer__brend">
			@include('partials.logo', ['logoClass' => 'lg-footer__logo', 'logoTheme' => 'light'])
			<p class="lg-footer__tekst">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.</p>
			<ul class="lg-footer__kontakt">
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

		<nav class="lg-footer__col lg-footer__grupe" aria-label="Linkovi u podnožju">
			<details class="lg-footer__grupa" open data-lg-fold>
				<summary class="lg-footer__naslov">Korisni linkovi</summary>
				<ul class="lg-footer__lista">
					@foreach ($lgFooterLinks as $key => $label)
						@php($href = Navigacija::link($key))
						<li><a href="{{ $href }}" @if ($href === $lgCurrent) aria-current="page" @endif>{{ $label }}</a></li>
					@endforeach
				</ul>
			</details>
			<details class="lg-footer__grupa" open data-lg-fold>
				<summary class="lg-footer__naslov">Pravne informacije</summary>
				<ul class="lg-footer__lista">
					@foreach ($lgLegalLinks as $key => $label)
						@php($href = Navigacija::link($key))
						<li><a href="{{ $href }}" @if ($href === $lgCurrent) aria-current="page" @endif>{{ $label }}</a></li>
					@endforeach
				</ul>
			</details>
		</nav>

		<nav class="lg-footer__col" aria-label="Kategorije">
			<details class="lg-footer__grupa" open data-lg-fold>
				<summary class="lg-footer__naslov">Kategorije</summary>
				<ul class="lg-footer__lista">
					@foreach ($lgFooter['categories'] as $item)
						<li><a href="{{ $item['url'] }}">{{ $item['name'] }}</a></li>
					@endforeach
					<li><a class="lg-footer__sve" href="{{ url_slash('/oglasi') }}">Svi oglasi</a></li>
				</ul>
			</details>
		</nav>

		<div class="lg-footer__col lg-footer__zadnja">
			<h2 class="lg-footer__naslov lg-footer__naslov--najnoviji" id="lg-footer-najnoviji">Najnoviji oglasi</h2>
			<ul class="lg-footer__lista lg-footer__najnoviji" data-lg-najnoviji="{{ url_slash('/podnozje/najnoviji-oglasi') }}" aria-labelledby="lg-footer-najnoviji">
				<li><a class="lg-footer__sve" href="{{ url_slash('/oglasi') }}">Pogledajte sve oglase</a></li>
			</ul>
			<h2 class="lg-footer__naslov lg-footer__naslov--mreze">Pratite nas</h2>
			<ul class="lg-footer__mreze">
				@foreach ($lgSocial as $name => $social)
					<li><a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="loglasi.rs na mreži {{ $name }} (otvara se u novom prozoru)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $social['path'] !!}</svg></a></li>
				@endforeach
			</ul>
		</div>
	</div>

	<div class="lg-wrap lg-footer__dno">
		<p>© {{ now()->year }} loglasi.rs · Vaš lokalni portal za kupovinu, prodaju i razmenu. Sva prava zadržana.</p>
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
	var list = document.querySelector('[data-lg-najnoviji]');
	if (!list || !window.fetch) return;
	var load = function () {
		fetch(list.getAttribute('data-lg-najnoviji'), {headers: {Accept: 'application/json'}})
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
					a.className = 'lg-footer__oglas';
					title.textContent = item.title;
					price.textContent = item.price;
					price.className = 'lg-footer__cena';
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
