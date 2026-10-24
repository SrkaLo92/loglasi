@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/posting.css') }}">
@endpush

@section('content')
<div class="pst lg-wrap">
	<section class="pst-card pst-confirm" aria-labelledby="pst-confirm-title">
		<h1 id="pst-confirm-title">{{ $heading }}</h1>
		<p>{{ $text }}</p>
		<form method="post" action="{{ $action }}" class="pst-actions">
			@csrf
			@if ($method !== 'POST') @method($method) @endif
			<a class="pst-btn pst-btn--line" href="{{ url_slash('moj-nalog/oglasi') }}">Odustani</a>
			<button class="pst-btn{{ $danger ? ' pst-btn--danger' : '' }}" type="submit">{{ $button }}</button>
		</form>
	</section>
</div>
@endsection
