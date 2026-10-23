@extends('layouts.app')
@use('App\Support\Listing\Text')
@use('App\Support\Seo')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/listing-profile.css') }}">
@endpush

@section('content')
@php
	$meta = array_filter([
		Text::memberSince($user->created_at),
		Text::number($total).' '.Text::plural($total, 'oglas', 'oglasa', 'oglasa'),
		$phone !== '' && $user->isPhoneVerified() ? 'Telefon proveren' : null,
	]);
@endphp
<section class="lgp lgp-privatni" aria-labelledby="lgp-ime-{{ $user->id }}" data-lgp-user="{{ $user->id }}">
	<span class="lgp-privatni__avatar" aria-hidden="true">{{ Text::initials($user->name) }}</span>
	<div class="lgp-privatni__main">
		<h1 class="lgp-privatni__name" id="lgp-ime-{{ $user->id }}">{{ Seo::get()->getH1() ?? $user->name }}</h1>
		<p class="lgp-privatni__type">Privatno lice</p>
		<ul class="lgp-privatni__meta">
			@foreach ($meta as $item)
				<li>{{ $item }}</li>
			@endforeach
		</ul>
	</div>
	@include('listing.profile.actions', ['isFirm' => false, 'firm' => null])
</section>
@include('listing.profile.listings', ['title' => 'Oglasi korisnika'])
@endsection
