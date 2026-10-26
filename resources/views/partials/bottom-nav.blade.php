{{-- Bottom navigation for phones (< 768 px), as in inc/home/sadrzaj.php. Unread message count: $unreadMessages (messages module). --}}
@use('App\Support\Pages\Pricing')
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')
@php
	$lgAktivna = Navigation::aktivna(request()->path());
	$lgNeprocitano = max(0, (int) ($unreadMessages ?? 0));
	$lgStavke = [
		'pocetna' => ['naziv' => 'Početna', 'aria' => 'Početna strana', 'ikonica' => 'home', 'link' => 'pocetna'],
		'pretraga' => ['naziv' => 'Pretraga', 'aria' => 'Pretraga oglasa', 'ikonica' => 'search', 'link' => 'oglasi'],
		'postavi' => ['naziv' => 'Postavi oglas', 'aria' => 'Postavi oglas', 'ikonica' => 'plus', 'link' => 'postavi'],
		'poruke' => ['naziv' => 'Poruke', 'aria' => 'Poruke', 'ikonica' => 'chat', 'link' => 'poruke'],
		'nalog' => ['naziv' => 'Nalog', 'aria' => auth()->check() ? 'Moj nalog' : 'Prijava ili moj nalog', 'ikonica' => 'user', 'link' => auth()->check() ? 'nalog' : 'prijava'],
	];
@endphp
<nav class="lg-mnav" aria-label="Glavna navigacija">
	<ul class="lg-mnav__list">
		@foreach ($lgStavke as $key => $item)
			@php
				$aria = $item['aria'];
				if ($key === 'poruke' && $lgNeprocitano > 0) {
					$aria .= ', '.$lgNeprocitano.' '.Pricing::plural($lgNeprocitano, 'nepročitana', 'nepročitane', 'nepročitanih');
				}
			@endphp
			<li class="lg-mnav__item{{ $key === 'postavi' ? ' lg-mnav__item--cta' : '' }}">
				<a class="lg-mnav__link" href="{{ Navigation::link($item['link']) }}" aria-label="{{ $aria }}" @if ($key === $lgAktivna) aria-current="page" @endif>
					<span class="lg-mnav__icon">{{ SiteIcons::svg($item['ikonica'], $key === 'postavi' ? 26 : 24) }}@if ($key === 'poruke' && $lgNeprocitano > 0)<span class="lg-mnav__num" aria-hidden="true">{{ $lgNeprocitano > 99 ? '99+' : $lgNeprocitano }}</span>@endif</span>
					<span class="lg-mnav__name">{{ $item['naziv'] }}</span>
				</a>
			</li>
		@endforeach
	</ul>
</nav>
