{{-- One question and answer (<details>, works without JavaScript). Questions with the same name form a group where one is open at a time. --}}
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\PageText')
<details class="lgx-faq__item" name="{{ $grupa ?? 'faq' }}" @if ($otvoreno ?? false) open @endif>
	<summary class="lgx-faq__question"><span>{{ PageText::replace((string) $pitanje) }}</span>{{ SiteIcons::svg('chevron', 20) }}</summary>
	<div class="lgx-faq__answer"><p>{{ PageText::inline((string) $odgovor) }}</p></div>
</details>
