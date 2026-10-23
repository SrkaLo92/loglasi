@use('App\Support\Listing\Icons')
@php
	$tips = array_values(array_filter((array) config('listing.safety_tips')));
@endphp
@if ($tips)
	<section class="lgo lgo-card lgo-tips lgo-o8" aria-labelledby="lgo-tips-title">
		<h2 class="lgo-tips__title" id="lgo-tips-title">{{ Icons::listing('shield', 20) }}<span>{{ config('listing.safety_tips_title') }}</span></h2>
		<ul class="lgo-tips__list">
			@foreach ($tips as $tip)
				<li>{{ $tip }}</li>
			@endforeach
		</ul>
	</section>
@endif
