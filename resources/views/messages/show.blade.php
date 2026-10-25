@extends('layouts.app')
@include('messages.partials.head')
@php
	$otherName = $other && ! $other->trashed() ? $other->name : 'Obrisan korisnik';
	$title = 'Razgovor sa korisnikom '.$otherName.' | loglasi.rs';
	$robots = 'noindex, follow';
	// "Pročitano" belongs under my last message, so only when the thread ends with it.
	$lastMine = $messages->last();
	$lastMine = $lastMine && (int) $lastMine->sender_id === $user->id ? $lastMine : null;
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

	@if ($iBlocked)
		<p class="lgm-alert lgm-alert--warn">Blokirali ste ovog korisnika. Ne možete da razmenjujete poruke dok ga ne odblokirate.</p>
	@elseif ($blockedMe)
		<p class="lgm-alert lgm-alert--warn">Ovaj korisnik ne prima vaše poruke.</p>
	@elseif ($otherAvailable)
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

	@if ($other)
		<div class="lgm-actions">
			<form method="post" action="{{ url_slash('moj-nalog/poruke/'.$conversation->id.'/blokiraj') }}">
				@csrf
				@if ($iBlocked)
					@method('DELETE')
					<button class="lgm-link lgm-link--green" type="submit">Odblokiraj korisnika</button>
				@else
					<button class="lgm-link" type="submit">Blokiraj korisnika</button>
				@endif
			</form>

			@php($reportErrors = $errors->getBag('report'))
			<details class="lgm-report" @if ($reportErrors->any()) open @endif>
				<summary>Prijavi korisnika</summary>
				<form class="lgm-form" method="post" action="{{ url_slash('moj-nalog/poruke/'.$conversation->id.'/prijava') }}">
					@csrf
					<label for="lgm-reason">Razlog</label>
					<select id="lgm-reason" data-pick name="reason" required @if ($reportErrors->has('reason')) aria-invalid="true" aria-describedby="lgm-reason-error" @endif>
						<option value="">Izaberite razlog</option>
						@foreach (\App\Support\Listing\ReportReasons::ALL as $key => $label)
							<option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>
						@endforeach
					</select>
					@if ($reportErrors->has('reason'))<p class="lgm-error" id="lgm-reason-error" role="alert">{{ $reportErrors->first('reason') }}</p>@endif
					<label for="lgm-details">Opis (nije obavezno)</label>
					<textarea id="lgm-details" name="details" rows="3" maxlength="1000">{{ old('details') }}</textarea>
					@if ($reportErrors->has('details'))<p class="lgm-error" role="alert">{{ $reportErrors->first('details') }}</p>@endif
					<button class="lg-btn lg-btn--orange" type="submit">Pošalji prijavu</button>
				</form>
			</details>
		</div>
	@endif
</div>
@endsection

@push('scripts')
<script src="{{ asset_v('js/messages.js') }}" defer
	data-poll-url="{{ url_slash('moj-nalog/poruke/'.$conversation->id.'/nove') }}"
	data-poll-seconds="{{ (int) config('messages.poll_seconds') }}"
	data-last-id="{{ (int) ($messages->last()?->id ?? 0) }}"></script>
@endpush
