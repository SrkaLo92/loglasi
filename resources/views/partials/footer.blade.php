{{-- Site footer, structured like the old live site: contact and social, useful links, categories. --}}
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
		'za_firme' => 'Za firme',
		'privatnost' => 'Politika privatnosti',
		'uslovi' => 'Uslovi korišćenja',
	];
	$lgCurrent = url_slash(request()->path());
	$lgFooter = FooterSections::get();
	$lgPhone = (string) Settings::get('contact_phone');
	$lgEmail = (string) Settings::get('contact_email');
	$lgAddress = (string) Settings::get('contact_address');
	$lgSocial = [
		'Facebook' => ['url' => 'https://www.facebook.com/profile.php?id=61571911496798', 'path' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z" fill="currentColor"/>'],
		'Instagram' => ['url' => 'https://www.instagram.com/loglasi.rs/', 'path' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/>'],
		'TikTok' => ['url' => 'https://www.tiktok.com/@loglasi.rs', 'path' => '<path d="M14 3h3a4.5 4.5 0 0 0 4 4v3a7.5 7.5 0 0 1-4-1.2V15a6 6 0 1 1-6-6h.5v3.2A2.8 2.8 0 1 0 14 15V3z" fill="currentColor"/>'],
	];
@endphp
<footer class="lg-footer">
	<div class="lg-wrap lg-footer__in">
		<div class="lg-footer__col lg-footer__brend">
			@include('partials.logo', ['logoClass' => 'lg-footer__logo', 'logoTheme' => 'light'])
			<p class="lg-footer__tekst">Vaš lokalni portal za kupovinu, prodaju i razmenu proizvoda i usluga u Loznici i okolini.</p>
			<ul class="lg-footer__kontakt">
				@if ($lgPhone !== '')
					<li><a href="tel:{{ $lgPhone }}">{{ Ikonice::svg('phone', 18) }}{{ Phone::display($lgPhone) }}</a></li>
				@endif
				@if ($lgEmail !== '')
					<li><a href="mailto:{{ $lgEmail }}">{{ Ikonice::svg('mail', 18) }}{{ $lgEmail }}</a></li>
				@endif
				@if ($lgAddress !== '')
					<li><span>{{ Ikonice::svg('pin', 18) }}{{ $lgAddress }}</span></li>
				@endif
			</ul>
			<a class="lg-btn lg-btn--orange lg-footer__postavi" href="{{ url_slash('/postavi-oglas') }}">{{ Ikonice::svg('plus', 18) }}Postavite oglas</a>
			<div class="lg-footer__mreze">
				<p class="lg-footer__mreze-naslov">Pratite nas na društvenim mrežama</p>
				<ul>
					@foreach ($lgSocial as $name => $social)
						<li><a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="loglasi.rs na mreži {{ $name }} (otvara se u novom prozoru)"><svg class="lg-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $social['path'] !!}</svg></a></li>
					@endforeach
				</ul>
			</div>
		</div>

		<nav class="lg-footer__col" aria-labelledby="lg-footer-linkovi">
			<h2 class="lg-footer__naslov" id="lg-footer-linkovi">Korisni linkovi</h2>
			<ul class="lg-footer__lista">
				@foreach ($lgFooterLinks as $key => $label)
					@php($href = Navigacija::link($key))
					<li><a href="{{ $href }}" @if ($href === $lgCurrent) aria-current="page" @endif>{{ $label }}</a></li>
				@endforeach
			</ul>
		</nav>

		@if ($lgFooter['categories'])
			<nav class="lg-footer__col" aria-labelledby="lg-footer-kategorije">
				<h2 class="lg-footer__naslov" id="lg-footer-kategorije">Kategorije</h2>
				<ul class="lg-footer__lista">
					@foreach ($lgFooter['categories'] as $item)
						<li><a href="{{ $item['url'] }}">{{ $item['name'] }}</a></li>
					@endforeach
					<li><a class="lg-footer__sve" href="{{ url_slash('/oglasi') }}">Svi oglasi</a></li>
				</ul>
			</nav>
		@endif

	</div>
	<div class="lg-footer__dno">
		<div class="lg-wrap"><p>© {{ now()->year }} loglasi.rs – Vaš lokalni portal za kupovinu, prodaju i razmenu proizvoda i usluga. Sva prava zadržana.</p></div>
	</div>
</footer>
