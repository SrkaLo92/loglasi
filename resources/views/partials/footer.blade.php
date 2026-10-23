{{-- Podnožje sajta po odobrenoj maketi. --}}
@use('App\Support\Stranice\Navigacija')
@php
	$lgFooterLinks = [
		'o_nama' => 'O nama',
		'faq' => 'Česta pitanja',
		'cenovnik' => 'Cenovnik',
		'za_firme' => 'Za firme',
		'kontakt' => 'Kontakt',
		'uslovi' => 'Uslovi korišćenja',
		'privatnost' => 'Privatnost',
	];
	$lgCurrent = url_slash(request()->path());
@endphp
<footer class="lg-footer">
	<div class="lg-wrap lg-footer__in">
		<div class="lg-footer__brend">
			<a class="lg-footer__logo" href="{{ url_slash('/') }}" aria-label="loglasi.rs, početna strana">loglasi<span>.rs</span></a>
			<p class="lg-footer__tekst">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.</p>
		</div>
		<nav class="lg-footer__nav" aria-label="Podnožje">
			<ul>
				@foreach ($lgFooterLinks as $key => $label)
					@php
	$href = Navigacija::link($key);
@endphp
					<li><a href="{{ $href }}" @if ($href === $lgCurrent) aria-current="page" @endif>{{ $label }}</a></li>
				@endforeach
			</ul>
		</nav>
	</div>
	<div class="lg-footer__dno">
		<div class="lg-wrap"><p>© {{ now()->year }} loglasi.rs</p></div>
	</div>
</footer>
