@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset_v('css/listing-profile.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/ratings.css') }}">
@endpush

@section('content')
<div class="lgr lgr--page lg-wrap">
	<nav class="lgr-crumbs" aria-label="Putanja">
		<a href="{{ $user->profileUrl() }}">‹ {{ $name }}</a>
	</nav>
	<h1>Ocene kupaca: {{ $name }}</h1>

	@if (session('status'))
		<p class="lgr-alert" role="status">{{ session('status') }}</p>
	@endif
	@if ($errors->any())
		<p class="lgr-alert lgr-alert--error" role="alert">{{ $errors->first() }}</p>
	@endif

	@if ($summary['count'] === 0)
		<p class="lgr-help">Još nema ocena. Kupci ocenjuju prodavca posle razgovora o oglasu.</p>
	@else
		<div class="lgr__grid">
			@include('ratings.partials.summary', ['summary' => $summary])
			<div>
				<ul class="lgr-list" role="list">
					@foreach ($items as $rating)
						@include('ratings.partials.item', ['rating' => $rating, 'viewer' => $viewer])
					@endforeach
				</ul>
				@include('listing.profile.pager', ['paginator' => $items])
			</div>
		</div>
	@endif
	<p class="lgr-help lgr-rules">Ocenu može da da samo kupac koji se dopisivao sa prodavcem o oglasu. <a href="{{ url_slash('pravila-oglasavanja') }}">Pravila ocenjivanja</a></p>
</div>
@endsection
