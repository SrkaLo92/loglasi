{{-- One post in a list (sections, search). --}}
@php
	/** @var \App\Models\InfoPost $post */
@endphp
<a class="inf-row" href="{{ $post->url() }}">
	@include('info.partials.thumb', ['post' => $post, 'class' => 'inf-row__media', 'tag' => 'span'])
	<span class="inf-row__body">
		<span class="inf-kicker">{{ $post->sponsored ? 'Promo' : ($post->topicLabel() ?? $post->typeLabel()) }}@if ($post->place) · {{ $post->place }}@endif</span>
		<strong>{{ $post->title }}</strong>
		<span class="inf-row__text">{{ \Illuminate\Support\Str::limit($post->summary, 160) }}</span>
		<span class="inf-meta">{{ $post->published_at?->diffForHumans() }}@if ($post->source_name) · izvor: {{ $post->source_name }}@endif</span>
	</span>
</a>
