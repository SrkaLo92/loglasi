@extends('info.layout', ['section' => $section, 'title' => $title, 'description' => $intro.' loglasi Info za Loznicu i okolinu.', 'canonical' => \App\Support\Info\InfoUrl::to($section)])
@use('App\Support\Info\InfoUrl')
@section('content')
<div class="inf-wrap inf-stack">
	<div class="inf-pagehead">
		<h1>{{ $title }}</h1>
		<p class="inf-meta">{{ $intro }}</p>
	</div>
	@if ($section === 'vesti')
		<nav class="inf-chips" aria-label="Teme">
			<a href="{{ InfoUrl::to('vesti') }}" @if (! $topic) aria-current="page" @endif>Sve</a>
			@foreach (config('info.topics') as $key => $label)
				<a href="{{ InfoUrl::to('vesti') }}?tema={{ $key }}" @if ($topic === $key) aria-current="page" @endif>{{ $label }}</a>
			@endforeach
		</nav>
	@endif
	@if ($posts->isEmpty())
		<p class="inf-empty">Ovde još nema objava. <a href="{{ InfoUrl::to('posalji-vest') }}">Pošaljite nam vest.</a></p>
	@else
		<div class="inf-list">
			@foreach ($posts as $post)
				@include('info.partials.post-row', ['post' => $post])
			@endforeach
		</div>
		@if ($posts->hasPages())
			<nav class="inf-pager" aria-label="Strane">
				@if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}">← Novije</a>@endif
				@if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}">Starije →</a>@endif
			</nav>
		@endif
	@endif
</div>
@endsection
