{{-- One post in a list (sections, search). --}}
@php
	/** @var \App\Models\InfoPost $post */
	$prImage = $post->imageUrl();
@endphp
<a class="inf-row" href="{{ $post->url() }}">
	<span class="inf-row__media">@if ($prImage)<img src="{{ $prImage }}" alt="" loading="lazy" decoding="async" width="200" height="150">@endif</span>
	<span class="inf-row__body">
		<span class="inf-kicker">{{ $post->sponsored ? 'Promo' : ($post->topicLabel() ?? $post->typeLabel()) }}@if ($post->place) · {{ $post->place }}@endif</span>
		<strong>{{ $post->title }}</strong>
		<span class="inf-row__text">{{ \Illuminate\Support\Str::limit($post->summary, 160) }}</span>
		<span class="inf-meta">{{ $post->published_at?->diffForHumans() }}@if ($post->source_name) · izvor: {{ $post->source_name }}@endif</span>
	</span>
</a>
