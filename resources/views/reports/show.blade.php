{{-- /moj-nalog/prijave/{id}/: the user's report, the admin's questions and the answer form. --}}
@extends('layouts.app')
@include('notifications.partials.head')
@php
	$title = 'Vaša prijava | loglasi.rs';
	$robots = 'noindex, nofollow';
	$target = $report->reportable;
	$isListing = $target instanceof \App\Models\Listing;
	$open = $report->status === \App\Enums\ReportStatus::Open;
@endphp

@section('content')
<div class="lgn lgt lg-wrap">
	<h1>Vaša prijava</h1>
	<p class="lgn-lead">
		@if ($isListing)
			Oglas: <a href="{{ url_slash('oglas/'.$target->slug) }}">„{{ $target->title }}”</a>
		@elseif ($target)
			Nalog: {{ $target->name }}
		@else
			Prijavljeni sadržaj je u međuvremenu uklonjen.
		@endif
	</p>

	@include('accounts.partials.status')

	<section class="lgn-card lgr-thread" aria-labelledby="lgr-prepiska">
		<h2 id="lgr-prepiska">Prepiska</h2>
		<ol class="lgr-msgs">
			<li class="lgr-msg lgr-msg--mine">
				<span class="lgr-msg__who">Vi, {{ $report->created_at->format('j. n. Y. H:i') }}</span>
				<p><strong>{{ $reasonLabel }}</strong>@if ($report->details)<br>{{ $report->details }}@endif</p>
			</li>
			@foreach ($report->messages as $message)
				<li @class(['lgr-msg', 'lgr-msg--staff' => $message->from_staff, 'lgr-msg--mine' => ! $message->from_staff])>
					<span class="lgr-msg__who">{{ $message->from_staff ? 'loglasi.rs' : 'Vi' }}, {{ $message->created_at->format('j. n. Y. H:i') }}</span>
					<p>{!! nl2br(e($message->body)) !!}</p>
				</li>
			@endforeach
		</ol>

		@if ($open)
			<form class="lgn-form" id="odgovor" method="post" action="{{ url_slash('moj-nalog/prijave/'.$report->id) }}" novalidate>
				@csrf
				<div class="lgn-field">
					<label for="lgr-body">Vaš odgovor</label>
					<textarea id="lgr-body" name="body" rows="4" maxlength="{{ \App\Services\Reports\ReportThread::MAX_LENGTH }}" required
						@error('body') aria-invalid="true" aria-describedby="lgr-body-greska" @enderror>{{ old('body') }}</textarea>
					@error('body')<p class="lgn-error" id="lgr-body-greska">{{ $message }}</p>@enderror
				</div>
				<button class="lgn-btn" type="submit">Pošalji odgovor</button>
			</form>
		@else
			<p class="lgn-note">Prijava je {{ mb_strtolower($report->status->label()) }}. Hvala što ste nam pomogli.</p>
		@endif
	</section>
</div>
@endsection
