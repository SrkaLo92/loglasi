{{-- Short block on a profile: summary, the newest ratings and a link to all ($summary, $latest from ProfileController). --}}
<section class="lgr" aria-labelledby="lgr-title">
	<div class="lgr__head">
		<h2 id="lgr-title">Ocene kupaca</h2>
		@if ($summary['count'] > 3)
			<a href="{{ url_slash('korisnik/'.$user->slug.'/ocene') }}">Sve ocene ({{ $summary['count'] }})</a>
		@endif
	</div>
	@if ($summary['count'] === 0)
		<p class="lgr-help">Još nema ocena. Kupci ocenjuju prodavca posle razgovora o oglasu.</p>
	@else
		<div class="lgr__grid">
			@include('ratings.partials.summary', ['summary' => $summary])
			<ul class="lgr-list" role="list">
				@foreach ($latest as $rating)
					@include('ratings.partials.item', ['rating' => $rating, 'viewer' => auth()->user()])
				@endforeach
			</ul>
		</div>
	@endif
</section>
