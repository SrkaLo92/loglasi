{{-- Bottom navigation for phones (< 768 px), as in inc/home/sadrzaj.php. Unread message count: $unreadMessages (messages module). --}}
@use('App\Support\Pages\Pricing')
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\Navigation')
@php
	$lgActive = Navigation::active(request()->path());
	$lgUnread = max(0, (int) ($unreadMessages ?? 0));
	$lgItems = [
		'home' => ['name' => 'Početna', 'aria' => 'Početna strana', 'icon' => 'home', 'link' => 'home'],
		'search' => ['name' => 'Pretraga', 'aria' => 'Pretraga oglasa', 'icon' => 'search', 'link' => 'listings'],
		'post' => ['name' => 'Postavi oglas', 'aria' => 'Postavi oglas', 'icon' => 'plus', 'link' => 'post'],
		'messages' => ['name' => 'Poruke', 'aria' => 'Poruke', 'icon' => 'chat', 'link' => 'messages'],
		'account' => ['name' => 'Nalog', 'aria' => auth()->check() ? 'Moj nalog' : 'Prijava ili moj nalog', 'icon' => 'user', 'link' => auth()->check() ? 'account' : 'login'],
	];
@endphp
<nav class="lg-mnav" aria-label="Glavna navigacija">
	<ul class="lg-mnav__list">
		@foreach ($lgItems as $key => $item)
			@php
				$aria = $item['aria'];
				if ($key === 'messages' && $lgUnread > 0) {
					$aria .= ', '.$lgUnread.' '.Pricing::plural($lgUnread, 'nepročitana', 'nepročitane', 'nepročitanih');
				}
			@endphp
			<li class="lg-mnav__item{{ $key === 'post' ? ' lg-mnav__item--cta' : '' }}">
				<a class="lg-mnav__link" href="{{ Navigation::link($item['link']) }}" aria-label="{{ $aria }}" @if ($key === $lgActive) aria-current="page" @endif>
					<span class="lg-mnav__icon">{{ SiteIcons::svg($item['icon'], $key === 'post' ? 26 : 24) }}@if ($key === 'messages' && $lgUnread > 0)<span class="lg-mnav__num" aria-hidden="true">{{ $lgUnread > 99 ? '99+' : $lgUnread }}</span>@endif</span>
					<span class="lg-mnav__name">{{ $item['name'] }}</span>
				</a>
			</li>
		@endforeach
	</ul>
</nav>
