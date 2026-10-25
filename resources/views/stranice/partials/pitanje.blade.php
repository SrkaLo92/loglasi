{{-- One question and answer (<details>, works without JavaScript). Questions with the same name form a group where one is open at a time. --}}
@use('App\Support\Stranice\Ikonice')
@use('App\Support\Stranice\PageText')
<details class="lgx-faq__stavka" name="{{ $grupa ?? 'faq' }}" @if ($otvoreno ?? false) open @endif>
	<summary class="lgx-faq__pitanje"><span>{{ PageText::replace((string) $pitanje) }}</span>{{ Ikonice::svg('chevron', 20) }}</summary>
	<div class="lgx-faq__odgovor"><p>{{ PageText::inline((string) $odgovor) }}</p></div>
</details>
