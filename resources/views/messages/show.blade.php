@extends('layouts.app')
@include('messages.partials.head')
@php
	$otherName = $other && ! $other->trashed() ? $other->name : 'Obrisan korisnik';
	$title = 'Razgovor sa korisnikom '.$otherName.' | loglasi.rs';
	$robots = 'noindex, follow';
	$lastMine = $messages->filter(fn ($m) => (int) $m->sender_id === $user->id)->last();
	$seen = $lastMine && $otherReadAt && $otherReadAt->greaterThan($lastMine->created_at);
@endphp

@section('content')
<div class="lgm lgm--chat lg-wrap">
	<p class="lgm-back"><a href="{{ url_slash('moj-nalog/poruke') }}">← Sve poruke</a></p>

	<header class="lgm-chat-head">
		<h1>{{ $otherName }}
			@if ($other?->isFirm())<span class="lgm-badge lgm-badge--firm">Firma</span>@endif
		</h1>
		<p class="lgm-chat-head__role">{{ $conversation->isBuyer($user) ? 'Prodavac' : 'Zainteresovan kupac' }}</p>
		@include('messages.partials.listing', ['listing' => $listing, 'thumb' => $thumb])
	</header>

	@include('messages.partials.status')

	@if ($hasOlder)
		<p class="lgm-older"><a href="{{ url_slash('moj-nalog/poruke/'.$conversation->id) }}?before={{ $messages->first()->id }}" rel="nofollow">Prikaži starije poruke</a></p>
	@endif

	<ol class="lgm-thread" id="lgm-thread" aria-live="polite" aria-label="Poruke">
		@foreach ($messages as $message)
			@include('messages.partials.message', ['message' => $message, 'mine' => (int) $message->sender_id === $user->id])
		@endforeach
	</ol>
	<p class="lgm-seen" id="lgm-seen"{{ $seen ? '' : ' hidden' }}>Pročitano</p>

	<p class="lgm-safety">Ne šaljite novac unapred i ne plaćajte preko linkova koje dobijete porukom. Stvar pogledajte uživo.</p>

	@if ($otherAvailable)
		<form class="lgm-form" method="post" action="{{ url_slash('moj-nalog/poruke/'.$conversation->id) }}" novalidate>
			@csrf
			<label for="lgm-body">Vaša poruka</label>
			<textarea id="lgm-body" name="body" rows="4" maxlength="{{ config('messages.max_length') }}" required
				@error('body') aria-invalid="true" aria-describedby="lgm-body-error" @enderror>{{ old('body') }}</textarea>
			@error('body')
				<p class="lgm-error" id="lgm-body-error" role="alert">{{ $message }}</p>
				@unless (auth()->user()->hasVerifiedEmail())
					<p class="lgm-help"><a href="{{ url_slash('potvrda-emaila') }}">Pošaljite link za potvrdu emaila ponovo</a></p>
				@endunless
			@enderror
			<button class="lg-btn lg-btn--green" type="submit">Pošalji</button>
		</form>
	@else
		<p class="lgm-alert lgm-alert--warn">Ovaj korisnik više ne prima poruke.</p>
	@endif
</div>
@endsection

@push('scripts')
<script src="{{ asset_v('js/messages.js') }}" defer
	data-poll-url="{{ url_slash('moj-nalog/poruke/'.$conversation->id.'/nove') }}"
	data-poll-seconds="{{ (int) config('messages.poll_seconds') }}"
	data-last-id="{{ (int) ($messages->last()?->id ?? 0) }}"></script>
@endpush
