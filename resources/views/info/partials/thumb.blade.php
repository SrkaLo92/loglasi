{{-- Picture of a post ($tag 'span' when it sits inside another link): its own photo, or (no photo) a calm tile with the topic's icon instead of a stock picture.
     $post (InfoPost), $class (size modifier), $eager (first picture on the page). --}}
@php
	$thIcons = [
		'sport' => '<circle cx="12" cy="12" r="9"/><path d="m12 7 4 3-1.5 4.5h-5L8 10z"/>',
		'kultura' => '<path d="M4 20h16M6 20V10M10 20V10M14 20V10M18 20V10M3 10l9-6 9 6z"/>',
		'privreda' => '<path d="M3 21h18M5 21V9l5 3V9l5 3V5h4v16"/>',
		'zdravlje' => '<path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.5-7 10-7 10z"/>',
		'obrazovanje' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
		'saobracaj' => '<path d="M5 17h14M6 17l1.5-6h9L18 17M8 17v2M16 17v2"/><circle cx="8.5" cy="14" r=".5"/><circle cx="15.5" cy="14" r=".5"/>',
		'drustvo' => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6M15 14.5c3 0 6 2 6 5.5"/>',
		'event' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'notice' => '<path d="M12 3v1M5 9a7 7 0 0 1 14 0v5l2 3H3l2-3z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
	];
	$thTag = ($tag ?? 'a') === 'span' ? 'span' : 'a';
	$thHref = $thTag === 'a' ? 'href="'.e($post->url()).'" tabindex="-1"' : '';
	$thKey = isset($thIcons[$post->topic]) ? $post->topic : (isset($thIcons[$post->type]) ? $post->type : 'drustvo');
@endphp
@if ($post->hasOwnImage())
	<{{ $thTag }} class="inf-media {{ $class ?? '' }}" {!! $thHref !!} aria-hidden="true"><img src="{{ $post->imageUrl() }}" alt="" @if ($eager ?? false) fetchpriority="high" @else loading="lazy" decoding="async" @endif></{{ $thTag }}>
@else
	<{{ $thTag }} class="inf-media inf-media--topic inf-media--t-{{ $thKey }} {{ $class ?? '' }}" {!! $thHref !!} aria-hidden="true">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $thIcons[$thKey] !!}</svg>
		<span>{{ $post->topicLabel() ?? $post->typeLabel() }}</span>
	</{{ $thTag }}>
@endif
