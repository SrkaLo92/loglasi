{{-- Wizard frame: heading, steps, messages; each step fills the "step" section. --}}
@extends('layouts.app')
@use('App\Support\Posting\Wizard')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/posting.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/pick.css') }}">
@endpush
@push('scripts')
<script src="{{ asset_v('js/posting.js') }}" defer></script>
<script src="{{ asset_v('js/fields.js') }}" defer></script>
<script src="{{ asset_v('js/pick.js') }}" defer></script>
@endpush

@section('content')
<div class="pst lg-wrap">
	<header class="pst-head">
		<h1>{{ \App\Support\Seo::get()->getH1() }}</h1>
		@isset($listing)
			@if ($listing && ($editing ?? false) && $listing->title !== '')
				<p class="pst-head__sub">{{ $listing->title }}</p>
			@endif
		@endisset
	</header>

	@if (isset($steps, $listing) && $listing)
		<nav class="pst-steps" aria-label="Koraci">
			<ol>
				@foreach ($steps as $key => [$segment, $label])
					<li class="pst-steps__item{{ $key === $step ? ' is-current' : '' }}{{ array_search($key, array_keys($steps)) < array_search($step, array_keys($steps)) ? ' is-done' : '' }}">
						<a href="{{ Wizard::url($listing, $key) }}" @if ($key === $step) aria-current="step" @endif>
							<span class="pst-steps__num" aria-hidden="true">{{ $loop->iteration }}</span>
							<span class="pst-steps__label">{{ $label }}</span>
						</a>
					</li>
				@endforeach
			</ol>
			<p class="pst-steps__mobile">Korak {{ array_search($step, array_keys($steps)) + 1 }} od {{ count($steps) }}: <strong>{{ $steps[$step][1] }}</strong></p>
		</nav>
	@endif

	@if (session('status'))
		<p class="pst-alert pst-alert--ok" role="status">{{ session('status') }}</p>
	@endif
	@if ($errors->has('limit'))
		<p class="pst-alert pst-alert--error" role="alert">{{ $errors->first('limit') }}</p>
	@endif
	@include('posting.partials.duplicate')

	@yield('step')
</div>
@endsection
