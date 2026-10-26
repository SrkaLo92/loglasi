{{-- One question and answer (<details>, works without JavaScript). Questions with the same name form a group where one is open at a time. --}}
@use('App\Support\Pages\SiteIcons')
@use('App\Support\Pages\PageText')
<details class="lgx-faq__item" name="{{ $group ?? 'faq' }}" @if ($open ?? false) open @endif>
	<summary class="lgx-faq__question"><span>{{ PageText::replace((string) $question) }}</span>{{ SiteIcons::svg('chevron', 20) }}</summary>
	<div class="lgx-faq__answer"><p>{{ PageText::inline((string) $answer) }}</p></div>
</details>
