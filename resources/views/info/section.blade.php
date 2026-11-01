@extends('info.layout', ['section' => $section, 'title' => $title, 'description' => $intro.' loglasi Info za Loznicu i okolinu.', 'canonical' => \App\Support\Info\InfoUrl::to($section), 'noindex' => $noindex])
@use('App\Support\Info\InfoUrl')
@section('content')
<div class="inf-wrap">
	<div class="inf-pagehead">
		<div>
			<h1>{{ $title }}</h1>
			<p>{{ $intro }}</p>
		</div>
		<a class="inf-btn inf-btn--warm" href="{{ InfoUrl::to('posalji-vest') }}">Pošaljite vest</a>
	</div>
	@if ($section === 'vesti')
		<nav class="inf-chips inf-chips--gap" aria-label="Teme">
			<a class="inf-chip" href="{{ InfoUrl::to('vesti') }}" @if (! $topic) aria-current="true" @endif>Sve</a>
			@foreach (config('info.topics') as $key => $label)
				<a class="inf-chip" href="{{ InfoUrl::to('vesti') }}?tema={{ $key }}" @if ($topic === $key) aria-current="true" @endif>{{ $label }}</a>
			@endforeach
		</nav>
	@endif
	@if ($posts->isEmpty())
		<p class="inf-empty">Ovde još nema objava. <a href="{{ InfoUrl::to('posalji-vest') }}">Pošaljite nam vest.</a></p>
	@else
		<div class="inf-rows">
			@foreach ($posts as $post)
				@include('info.partials.post-row', ['post' => $post])
			@endforeach
		</div>
		@if ($posts->hasPages())
			<nav class="inf-pager" aria-label="Strane">
				@if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}">Novije</a>@endif
				<span aria-current="page">{{ $posts->currentPage() }}</span>
				@if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}">Starije</a>@endif
			</nav>
		@endif
	@endif
</div>
@endsection
