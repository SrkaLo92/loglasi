{{-- "Danas u Loznici" on the classifieds home page: links to the info portal. Data from InfoToday (cached). --}}
@use('App\Support\Info\InfoToday')
@use('App\Support\Info\InfoUrl')
@use('App\Support\Info\Words')
@php($infToday = InfoToday::get())
@once
	@push('head')
		<link rel="stylesheet" href="{{ asset_v('css/info-block.css') }}">
	@endpush
@endonce
<section class="hm-sec inb" aria-labelledby="inb-title">
	<div class="inb__head">
		<h2 class="inb__title" id="inb-title">Danas u Loznici</h2>
		@if ($infToday['weather'])<span class="inb__weather">{{ $infToday['weather']['temp'] }}° · {{ $infToday['weather']['label'] }}</span>@endif
	</div>
	<ul class="inb__list">
		<li><a href="{{ InfoUrl::to('servisne-informacije') }}#apoteka"><strong>Dežurna apoteka:</strong> {{ $infToday['pharmacy'] ? $infToday['pharmacy']['name'].($infToday['pharmacy']['address'] ? ', '.$infToday['pharmacy']['address'] : '') : 'pogledajte raspored' }}</a></li>
		<li><a href="{{ InfoUrl::to('servisne-informacije') }}#struja"><strong>Struja:</strong> {{ $infToday['power_today'] > 0 ? Words::outages($infToday['power_today']).' danas' : 'bez najavljenih isključenja danas' }}</a></li>
		@if ($infToday['next_event'])
			<li><a href="{{ $infToday['next_event']['url'] }}"><strong>{{ $infToday['next_event']['when'] }}:</strong> {{ $infToday['next_event']['title'] }}</a></li>
		@endif
	</ul>
	<a class="inb__more" href="{{ InfoUrl::to('/') }}">Sve info za Loznicu <span aria-hidden="true">→</span></a>
</section>
