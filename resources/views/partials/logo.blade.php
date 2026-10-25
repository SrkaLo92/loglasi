{{-- Site logo link. $logoClass: CSS class of the link, $logoTheme: "dark" (on white) or "light" (on the dark footer). --}}
@php
	$logoTheme = $logoTheme ?? 'dark';
	$logoFile = $logoTheme === 'light' ? 'img/brand/logo-light.svg' : 'img/brand/logo.svg';
	$logoHasImage = is_file(public_path($logoFile));
@endphp
<a class="{{ $logoClass ?? 'lg-logo' }}" href="{{ url_slash('/') }}" aria-label="loglasi.rs, početna strana">
	@if ($logoHasImage)
		<img src="{{ asset_v($logoFile) }}" alt="" width="{{ $logoWidth ?? 120 }}" height="{{ $logoHeight ?? 45 }}" decoding="async" @if ($logoTheme === 'dark') fetchpriority="high" @else loading="lazy" @endif>
	@else
		loglasi<span>.rs</span>
	@endif
</a>
