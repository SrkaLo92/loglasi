{{-- "Sve kategorije": opened by any [data-lg-cats] link (the link itself goes to /oglasi/ without JavaScript). --}}
@use('App\Support\Search\CategoryIcons')
@use('App\Support\Search\CategorySheet')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/cats.css') }}">
@endpush
@push('scripts')
<script src="{{ asset_v('js/cats.js') }}" defer></script>
@endpush
@php
$lgcData = CategorySheet::get();
$lgcTile = function (array $c) {
	$media = $c['image']
		? '<img src="'.e($c['image']).'" alt="" loading="lazy" width="48" height="48">'
		: CategoryIcons::svg($c['icon'], 28, '1.6');
	return '<a class="lgc-tile" href="'.e($c['url']).'"><span class="lgc-tile__media lg-tone--'.e($c['color']).'">'.$media.'</span><span class="lgc-tile__name">'.e(str_replace(' | ', ' · ', $c['name'])).'</span>'
		.($c['count'] > 0 ? '<span class="lgc-tile__n">'.number_format($c['count'], 0, ',', '.').'</span>' : '').'</a>';
};
@endphp
<div class="lgc" id="lg-cats" data-lgc-index="{{ route('categories.index') }}" role="dialog" aria-modal="true" aria-labelledby="lgc-title" hidden>
	<div class="lgc__panel">
		<div class="lgc__head">
			<h2 class="lgc__title" id="lgc-title">Sve kategorije</h2>
			<button class="lgc__close" type="button" data-lgc-close aria-label="Zatvori">{{ CategoryIcons::svg('close', 22, '2') }}</button>
		</div>
		<div class="lgc__find">
			{{ CategoryIcons::svg('search', 20, '2') }}
			<input class="lgc__input" type="search" placeholder="Pretražite kategoriju…" autocomplete="off" enterkeyhint="search" aria-label="Pretražite kategoriju" data-lgc-find>
		</div>
		<div class="lgc__body">
			<ul class="lgc-results" data-lgc-results hidden></ul>
			<p class="lgc-none" data-lgc-none hidden>Nema kategorije sa tim imenom. Probajte <a href="{{ url_slash('/oglasi') }}" data-lgc-keyword>pretragu svih oglasa</a>.</p>
			<div data-lgc-browse>
				@if ($lgcData['popular'])
					<section class="lgc-sec">
						<h3 class="lgc-sec__title">Najtraženije</h3>
						<div class="lgc-grid">
							@foreach ($lgcData['popular'] as $c){!! $lgcTile($c) !!}@endforeach
						</div>
					</section>
				@endif
				@foreach ($lgcData['themes'] as $theme)
					<section class="lgc-sec">
						<h3 class="lgc-sec__title">{{ $theme['name'] }}</h3>
						<div class="lgc-grid">
							@foreach ($theme['items'] as $c){!! $lgcTile($c) !!}@endforeach
						</div>
					</section>
				@endforeach
				<a class="lgc-all" href="{{ url_slash('/oglasi') }}">Svi oglasi</a>
			</div>
		</div>
	</div>
</div>
