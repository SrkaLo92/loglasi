{{-- One post in a list: image (or a coloured tile), label, title, time and source. --}}
@php
	/** @var \App\Models\InfoPost $post */
	$prImage = $post->imageUrl();
	$prStory = $post->type === 'story';
	$prLabel = $post->typeLabel().($post->topicLabel() ? ' · '.$post->topicLabel() : '').($post->place ? ' · '.$post->place : '');
@endphp
<a class="inf-row" href="{{ $post->url() }}">
	<span @class(['inf-row__media', 'inf-tile--warm' => $prStory])>
		@if ($prImage)<img src="{{ $prImage }}" alt="" loading="lazy" decoding="async" width="160" height="120">@endif
	</span>
	<span class="inf-row__body">
		<span @class(['inf-label', 'inf-label--warm' => $prStory || $post->sponsored])>{{ $post->sponsored ? 'Sponzorisano' : $prLabel }}</span>
		<strong class="inf-row__title">{{ $post->title }}</strong>
		<span class="inf-meta">{{ $post->published_at?->diffForHumans() }}@if ($post->source_name) · izvor: {{ $post->source_name }}@endif</span>
	</span>
</a>
