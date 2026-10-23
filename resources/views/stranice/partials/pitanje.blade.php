{{-- Jedno pitanje i odgovor (radi bez JavaScript-a, <details>). --}}
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\PageText')
<details class="lgx-faq__stavka">
	<summary class="lgx-faq__pitanje"><span>{{ PageText::replace((string) $pitanje) }}</span>{{ Ikonice::svg('chevron', 20) }}</summary>
	<div class="lgx-faq__odgovor"><p>{{ PageText::inline((string) $odgovor) }}</p></div>
</details>
