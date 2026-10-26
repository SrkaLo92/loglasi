@extends('layouts.app')
@use('App\Support\Listing\Text')
@use('App\Support\Seo')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/listing-profile.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/ratings.css') }}">
@endpush

@section('content')
@php
	$meta = array_filter([
		Text::memberSince($user->created_at),
		Text::number($total).' '.Text::plural($total, 'oglas', 'oglasa', 'oglasa'),
		$phone !== '' && $user->isPhoneVerified() ? 'Telefon proveren' : null,
		$user->rating_count > 0 ? '★ '.\App\Support\Ratings\RatingPresenter::average((float) $user->rating_avg).' ('.\App\Support\Ratings\RatingPresenter::count((int) $user->rating_count).')' : null,
	]);
@endphp
<section class="lgp lgp-private" aria-labelledby="lgp-name-{{ $user->id }}" data-lgp-user="{{ $user->id }}">
	<span class="lgp-private__avatar" aria-hidden="true">{{ Text::initials($user->name) }}</span>
	<div class="lgp-private__main">
		<h1 class="lgp-private__name" id="lgp-name-{{ $user->id }}">{{ Seo::get()->getH1() ?? $user->name }}</h1>
		<p class="lgp-private__type">Privatno lice</p>
		<ul class="lgp-private__meta">
			@foreach ($meta as $item)
				<li>{{ $item }}</li>
			@endforeach
		</ul>
	</div>
	@include('listing.profile.actions', ['isFirm' => false, 'firm' => null])
</section>
@include('listing.profile.listings', ['title' => 'Oglasi korisnika'])
<div class="lg-wrap">@include('ratings.partials.brief', ['summary' => $ratingSummary, 'latest' => $ratingLatest])</div>
@endsection
