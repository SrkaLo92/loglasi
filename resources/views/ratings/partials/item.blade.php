{{-- One rating; the seller (owner of the profile) also sees the reply and report forms. --}}
@use('App\Support\Ratings\RatingPresenter')
@php($own = isset($viewer) && $viewer && (int) $viewer->id === (int) $rating->seller_id)
<li class="lgr-item" id="ocena-{{ $rating->id }}">
	<div class="lgr-item__head">
		@include('ratings.partials.stars', ['value' => $rating->score])
		<strong>{{ RatingPresenter::raterName($rating->rater) }}</strong>
		<time datetime="{{ $rating->created_at->toDateString() }}">{{ $rating->created_at->format('j. n. Y.') }}</time>
	</div>
	@if ($rating->listing)
		<p class="lgr-item__listing">Oglas: @if (! $rating->listing->trashed())<a href="{{ url_slash('oglas/'.$rating->listing->slug) }}">{{ $rating->listing->title }}</a>@else{{ $rating->listing->title }}@endif</p>
	@endif
	@if ($rating->comment)
		<p class="lgr-item__text">{!! nl2br(e($rating->comment)) !!}</p>
	@endif
	@if ($rating->reply)
		<div class="lgr-item__reply">
			<strong>Odgovor prodavca</strong>
			<p>{!! nl2br(e($rating->reply)) !!}</p>
		</div>
	@endif
	@if ($own)
		<details class="lgr-item__tools">
			<summary>{{ $rating->reply ? 'Izmeni odgovor' : 'Odgovori' }}</summary>
			<form method="post" action="{{ url_slash('ocene/'.$rating->id.'/odgovor') }}" class="lgr-form">
				@csrf
				<label for="lgr-odgovor-{{ $rating->id }}">Vaš javni odgovor</label>
				<textarea id="lgr-odgovor-{{ $rating->id }}" name="reply" rows="3" maxlength="{{ \App\Services\Ratings\SellerRatings::REPLY_MAX }}">{{ $rating->reply }}</textarea>
				<button class="lg-btn" type="submit">Sačuvaj odgovor</button>
			</form>
		</details>
		@if ($rating->reported_at)
			<p class="lgr-help">Prijavili ste ovu ocenu {{ $rating->reported_at->format('j. n. Y.') }}. Administrator je pregleda.</p>
		@else
			<details class="lgr-item__tools">
				<summary>Prijavi ocenu</summary>
				<form method="post" action="{{ url_slash('ocene/'.$rating->id.'/prijava') }}" class="lgr-form">
					@csrf
					<label for="lgr-prijava-{{ $rating->id }}">Zašto ocena krši pravila?</label>
					<textarea id="lgr-prijava-{{ $rating->id }}" name="reason" rows="2" maxlength="500" required placeholder="Npr. uvrede, lični podaci, nismo se dogovarali"></textarea>
					<button class="lg-btn lg-btn--outline" type="submit">Pošalji prijavu</button>
				</form>
			</details>
		@endif
	@endif
</li>
