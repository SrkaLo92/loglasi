{{-- Rating card in a conversation, buyer side only. $rating from SellerRatings::card(). --}}
@php
	$existing = $rating['existing'];
	$blocker = $rating['blocker'];
	$errorsBag = $errors->getBag('ocena');
	$current = (int) old('score', $existing?->score);
@endphp
<section class="lgr-card" id="ocena" aria-labelledby="lgr-card-title">
	<h2 id="lgr-card-title">{{ $existing ? 'Vaša ocena prodavca' : 'Ocenite prodavca' }}</h2>
	@if ($existing && $blocker !== null)
		@include('ratings.partials.stars', ['value' => $existing->score, 'size' => 20])
		@if ($existing->comment)<p class="lgr-item__text">{{ $existing->comment }}</p>@endif
		<p class="lgr-help">{{ $blocker }}</p>
	@elseif ($blocker !== null)
		<p class="lgr-help">{{ $blocker }}</p>
	@else
		<p class="lgr-help">Ocena je javna i pojavljuje se na profilu prodavca. Ocenjujte kako je tekao dogovor, ne cenu.</p>
		<form method="post" action="{{ url_slash('moj-nalog/poruke/'.$conversation->id.'/ocena') }}" class="lgr-form" novalidate>
			@csrf
			<fieldset class="lgr-pick" @if ($errorsBag->has('score')) aria-invalid="true" aria-describedby="lgr-score-error" @endif>
				<legend>Ocena</legend>
				@foreach ([1 => 'Loše', 2 => 'Slabo', 3 => 'Dobro', 4 => 'Vrlo dobro', 5 => 'Odlično'] as $score => $word)
					<input class="lg-visually-hidden" type="radio" name="score" id="lgr-score-{{ $score }}" value="{{ $score }}" @checked($current === $score) required>
					<label for="lgr-score-{{ $score }}" title="{{ $word }}">
						<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" focusable="false"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8z"/></svg>
						<span class="lg-visually-hidden">{{ $score }} od 5, {{ $word }}</span>
					</label>
				@endforeach
			</fieldset>
			@if ($errorsBag->has('score'))<p class="lgm-error" id="lgr-score-error" role="alert">{{ $errorsBag->first('score') }}</p>@endif
			<label for="lgr-comment">Komentar (nije obavezno)</label>
			<textarea id="lgr-comment" name="comment" rows="3" maxlength="{{ \App\Services\Ratings\SellerRatings::COMMENT_MAX }}" placeholder="Npr. dogovor je bio brz, stvar je kao u opisu">{{ old('comment', $existing?->comment) }}</textarea>
			@if ($errorsBag->has('comment'))<p class="lgm-error" role="alert">{{ $errorsBag->first('comment') }}</p>@endif
			<button class="lg-btn lg-btn--green" type="submit">{{ $existing ? 'Sačuvaj izmenu' : 'Objavi ocenu' }}</button>
		</form>
	@endif
</section>
