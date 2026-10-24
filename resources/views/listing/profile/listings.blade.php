{{-- The user's active listings: category tabs (links), card grid, pagination. --}}
@use('App\Support\Listing\Text')
@php
	$base = url_slash('korisnik/'.$user->slug);
@endphp
<section class="lgp lgp-oglasi" id="lgp-oglasi" aria-labelledby="lgp-oglasi-naslov">
	<div class="lgp-oglasi__head">
		<h2 class="lgp-oglasi__title" id="lgp-oglasi-naslov">{{ $title }} <span class="lgp-oglasi__count">({{ Text::number($total) }})</span></h2>
	</div>
	@if ($tabs)
		<nav class="lgp-tabs" aria-label="Oglasi po kategoriji">
			<a class="lgp-tab" href="{{ $base }}#lgp-oglasi"@if (! $selected) aria-current="true"@endif>Svi <span class="lgp-tab__count">{{ Text::number($total) }}</span></a>
			@foreach ($tabs as $tab)
				<a class="lgp-tab" href="{{ $base }}?kategorija={{ urlencode($tab['slug']) }}#lgp-oglasi" rel="nofollow"@if ($selected && $selected['id'] === $tab['id']) aria-current="true"@endif>{{ $tab['name'] }} <span class="lgp-tab__count">{{ Text::number($tab['count']) }}</span></a>
			@endforeach
		</nav>
	@endif
	<div class="lgp-oglasi__panel">
		@if ($listings->isNotEmpty())
			<div class="lgp-grid">
				@foreach ($listings as $l)
					<div class="lgp-item"><x-listing-card :listing="$l" /></div>
				@endforeach
			</div>
			@include('listing.profile.pager', ['paginator' => $listings])
		@else
			<p class="lgp-empty">{{ $user->isFirm() ? 'Firma trenutno nema aktivnih oglasa.' : 'Korisnik trenutno nema aktivnih oglasa.' }}</p>
		@endif
	</div>
</section>
