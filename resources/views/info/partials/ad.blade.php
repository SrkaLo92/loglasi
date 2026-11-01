{{-- One paid ad. $ad (InfoAd), $slim (bool, the narrow variant under articles). Always labelled "Oglas". --}}
@php($adImage = $ad->imageUrl() ?? ($ad->business?->photoUrls()[0] ?? null))
<aside aria-label="Oglas" @class(['inf-ad', 'inf-ad--slim' => $slim ?? false, 'inf-ad--noimg' => ! $adImage])>
	@if ($slim ?? false)
		@if ($adImage)<img src="{{ $adImage }}" alt="" loading="lazy" decoding="async">@endif
		<a class="inf-ad__body" href="{{ $ad->clickUrl() }}" rel="sponsored noopener">
			<span class="inf-ad__label">Oglas</span>
			<strong class="inf-ad__title">{{ $ad->title }}</strong>
			@if ($ad->text)<span class="inf-ad__text">{{ $ad->text }}</span>@endif
		</a>
	@else
		<div class="inf-ad__body">
			<span class="inf-ad__label">Oglas</span>
			<strong class="inf-ad__title">{{ $ad->title }}</strong>
			@if ($ad->text)<span class="inf-ad__text">{{ $ad->text }}</span>@endif
			<a class="inf-btn inf-btn--white" href="{{ $ad->clickUrl() }}" rel="sponsored noopener">{{ $ad->business && ! $ad->link_url ? 'Pogledaj profil firme' : 'Saznajte više' }}</a>
		</div>
		@if ($adImage)<img src="{{ $adImage }}" alt="" loading="lazy" decoding="async">@endif
	@endif
</aside>
