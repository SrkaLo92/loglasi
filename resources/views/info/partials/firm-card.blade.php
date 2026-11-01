{{-- Business in the directory and on the home page. $b (InfoBusiness), $photo (bool: show a photo when there is one). --}}
@php
	$fcFeatured = $b->isFeatured();
	$fcPhoto = ($photo ?? false) && $fcFeatured ? ($b->photoUrls()[0] ?? null) : null;
	$fcAds = $fcFeatured ? $b->listingsCount() : 0;
	$fcPhone = $b->phones()[0] ?? null;
@endphp
<a href="{{ $b->url() }}" @class(['inf-firm', 'inf-firm--featured' => $fcFeatured, 'inf-firm--photo' => $fcPhoto])>
	@if ($fcPhoto)
		<img src="{{ $fcPhoto }}" alt="" loading="lazy" decoding="async">
		<span class="inf-firm__body">
			<span class="inf-tag">Istaknuta firma</span>
			<strong class="inf-firm__name">{{ $b->name }}</strong>
			<span class="inf-firm__cat">{{ trim($b->category.', '.($b->place ?: 'Loznica'), ', ') }}</span>
			@if ($fcAds > 0)<span class="inf-firm__extra"><span class="inf-ads">{{ $fcAds }} {{ $fcAds % 10 === 1 && $fcAds % 100 !== 11 ? 'aktivan oglas' : 'aktivnih oglasa' }} na loglasi.rs</span></span>@endif
		</span>
	@else
		<span class="inf-firm__top">
			<span class="inf-initials" aria-hidden="true">{{ $b->initials() }}</span>
			<span><strong class="inf-firm__name">{{ $b->name }}</strong><br><span class="inf-firm__cat">{{ $b->category }}</span></span>
		</span>
		@if ($fcFeatured)<span class="inf-tag">Istaknuta firma</span>@endif
		@if ($b->address || $b->place)<span class="inf-firm__line">{{ $b->address ?: $b->place }}</span>@endif
		<span class="inf-firm__extra">@if ($fcPhone)<span class="inf-phone">{{ $fcPhone }}</span>@endif @if ($fcAds > 0)<span class="inf-ads">{{ $fcAds }} oglasa na loglasi.rs</span>@endif</span>
	@endif
</a>
