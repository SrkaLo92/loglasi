{{-- Numbered steps; a step's text may contain a Markdown link. --}}
@use('App\Support\Pages\PageText')
<ol class="lgx-steps{{ ($mreza ?? false) ? ' lgx-steps--grid' : '' }}">
	@foreach ($koraci as $i => $korak)
		@continue(! is_array($korak) || empty($korak['naslov']))
		<li class="lgx-step">
			<span class="lgx-step__num" aria-hidden="true">{{ $loop->iteration }}</span>
			<div>
				<h3 class="lgx-step__title">{{ PageText::replace((string) $korak['naslov']) }}</h3>
				<p class="lgx-step__text">{{ PageText::inline((string) ($korak['tekst'] ?? '')) }}</p>
			</div>
		</li>
	@endforeach
</ol>
