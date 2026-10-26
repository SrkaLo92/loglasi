{{-- Numbered steps; a step's text may contain a Markdown link. --}}
@use('App\Support\Pages\PageText')
<ol class="lgx-steps{{ ($grid ?? false) ? ' lgx-steps--grid' : '' }}">
	@foreach ($steps as $i => $step)
		@continue(! is_array($step) || empty($step['naslov']))
		<li class="lgx-step">
			<span class="lgx-step__num" aria-hidden="true">{{ $loop->iteration }}</span>
			<div>
				<h3 class="lgx-step__title">{{ PageText::replace((string) $step['naslov']) }}</h3>
				<p class="lgx-step__text">{{ PageText::inline((string) ($step['tekst'] ?? '')) }}</p>
			</div>
		</li>
	@endforeach
</ol>
