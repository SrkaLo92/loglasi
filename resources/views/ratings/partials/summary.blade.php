{{-- Average, number of ratings and the 5..1 distribution. $summary from SellerRatings::summary(). --}}
@use('App\Support\Ratings\RatingPresenter')
<div class="lgr-summary">
	<div class="lgr-summary__score">
		<strong>{{ RatingPresenter::average($summary['average']) }}</strong>
		@include('ratings.partials.stars', ['value' => $summary['average'], 'size' => 20])
		<span>{{ RatingPresenter::count($summary['count']) }}</span>
	</div>
	<ul class="lgr-bars" role="list" aria-label="Raspodela ocena">
		@foreach ($summary['distribution'] as $score => $n)
			@php($pct = $summary['count'] > 0 ? round($n / $summary['count'] * 100) : 0)
			<li>
				<span class="lgr-bars__label">{{ $score }} ★</span>
				<span class="lgr-bars__track" aria-hidden="true"><span style="width: {{ $pct }}%"></span></span>
				<span class="lgr-bars__n">{{ $n }}<span class="lg-visually-hidden"> {{ \App\Support\Listing\Text::plural($n, 'ocena', 'ocene', 'ocena') }} sa {{ $score }}</span></span>
			</li>
		@endforeach
	</ul>
</div>
