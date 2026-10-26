@extends('layouts.app')
@include('accounts.partials.head')
@use('App\Support\Auth\LoginPage')
@php
	$title = 'Prijava i registracija | loglasi.rs';
	$robots = 'noindex, follow';
	// $tab from LoginController::show(): the submitted form, then ?tab=, then login.
	$tabs = [LoginPage::LOGIN => 'Prijava', LoginPage::REGISTER => 'Novi nalog'];
@endphp

@push('scripts')
<script src="{{ asset_v('js/accounts.js') }}" defer></script>
@endpush

@section('content')
<div class="lgn lg-wrap">
	<h1 class="lgn-title">Prijava i registracija</h1>
	<section class="lgn-card lgn-tabs" data-lgn-tabs>
		{{-- Tabs are links: without JavaScript each one opens the page on its own tab. --}}
		<div class="lgn-tabs__list" role="tablist" aria-label="Prijava ili novi nalog">
			@foreach ($tabs as $key => $label)
				<a class="lgn-tabs__tab" role="tab" id="lgn-tab-{{ $key }}" href="{{ LoginPage::url($key) }}" aria-controls="lgn-panel-{{ $key }}"
					aria-selected="{{ $tab === $key ? 'true' : 'false' }}" tabindex="{{ $tab === $key ? '0' : '-1' }}" data-lgn-tab="{{ $key }}">{{ $label }}</a>
			@endforeach
		</div>

		@include('accounts.partials.status')

		<div class="lgn-tabs__panel" role="tabpanel" id="lgn-panel-{{ LoginPage::LOGIN }}" aria-labelledby="lgn-tab-{{ LoginPage::LOGIN }}" @if ($tab !== LoginPage::LOGIN) hidden @endif>
			@include('accounts.partials.login-form')
			<p class="lgn-switch">Nemate nalog? <a href="{{ LoginPage::url(LoginPage::REGISTER) }}" data-lgn-tab="{{ LoginPage::REGISTER }}">Registrujte se besplatno</a></p>
		</div>

		<div class="lgn-tabs__panel" role="tabpanel" id="lgn-panel-{{ LoginPage::REGISTER }}" aria-labelledby="lgn-tab-{{ LoginPage::REGISTER }}" @if ($tab !== LoginPage::REGISTER) hidden @endif>
			<p class="lgn-lead">Nalog je besplatan. Besplatno možete da imate do {{ (int) \App\Support\Settings::get('private_active_limit') }} aktivnih oglasa.</p>
			@include('accounts.partials.register-form')
			<p class="lgn-switch">Već imate nalog? <a href="{{ LoginPage::url(LoginPage::LOGIN) }}" data-lgn-tab="{{ LoginPage::LOGIN }}">Prijavite se</a></p>
		</div>
	</section>
</div>
@endsection
