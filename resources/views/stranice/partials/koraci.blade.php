{{-- Numerisani koraci; tekst koraka sme da ima Markdown link. --}}
@use('App\Support\Stranice\PageText')
<ol class="lgx-koraci{{ ($mreza ?? false) ? ' lgx-koraci--mreza' : '' }}">
	@foreach ($koraci as $i => $korak)
		@continue(! is_array($korak) || empty($korak['naslov']))
		<li class="lgx-korak">
			<span class="lgx-korak__broj" aria-hidden="true">{{ $loop->iteration }}</span>
			<div>
				<h3 class="lgx-korak__naslov">{{ PageText::replace((string) $korak['naslov']) }}</h3>
				<p class="lgx-korak__tekst">{{ PageText::inline((string) ($korak['tekst'] ?? '')) }}</p>
			</div>
		</li>
	@endforeach
</ol>
