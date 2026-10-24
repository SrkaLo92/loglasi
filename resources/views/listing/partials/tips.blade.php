@use('App\Support\Listing\Icons')
@use('App\Support\Stranice\PageText')
@php
	$tips = array_values(array_filter(PageText::list('oglas.saveti'), fn ($tip) => is_string($tip) && trim($tip) !== ''));
@endphp
@if ($tips)
	<section class="lgo lgo-card lgo-tips lgo-o8" aria-labelledby="lgo-tips-title">
		<h2 class="lgo-tips__title" id="lgo-tips-title">{{ Icons::listing('shield', 20) }}<span>{{ PageText::get('oglas.saveti.naslov') }}</span></h2>
		<ul class="lgo-tips__list">
			@foreach ($tips as $tip)
				<li>{{ $tip }}</li>
			@endforeach
		</ul>
	</section>
@endif
