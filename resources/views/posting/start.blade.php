@extends('posting.frame')

@section('step')
@if ($limitMessage)
	<section class="pst-card pst-limit" aria-labelledby="pst-limit-title">
		<h2 id="pst-limit-title">Sva besplatna mesta su popunjena</h2>
		<p>{{ $limitMessage }}</p>
		<p class="pst-help">Mesto se oslobađa kad oglas istekne, kad ga označite kao prodat ili obrišete u delu <a href="{{ url_slash('moj-nalog/oglasi') }}">Moji oglasi</a>. Dodatna mesta možete kupiti po <a href="{{ url_slash('cenovnik') }}">cenovniku</a>. Firme imaju veći limit: <a href="{{ url_slash('za-firme') }}">pogledajte ponudu za firme</a>.</p>
	</section>
@else
	@if ($drafts->isNotEmpty())
		<section class="pst-card pst-drafts" aria-labelledby="pst-drafts-title">
			<h2 id="pst-drafts-title">Nastavite započeti oglas</h2>
			<ul role="list">
				@foreach ($drafts as $draft)
					<li>
						<a href="{{ url_slash('postavi-oglas/'.$draft->id.'/osnovno') }}">{{ $draft->title !== '' ? $draft->title : 'Oglas bez naslova' }}</a>
						<span class="pst-help">{{ $draft->category?->name }}, započet {{ $draft->created_at->timezone(config('app.timezone'))->format('j. n. Y.') }}</span>
					</li>
				@endforeach
			</ul>
		</section>
	@endif

	<section class="pst-card" aria-labelledby="pst-cat-title">
		<h2 id="pst-cat-title">Izaberite kategoriju</h2>
		@unless ($limits['unlimited'])
			<p class="pst-help">Aktivni oglasi: {{ $limits['used_this_month'] }} od {{ $limits['monthly_limit'] }} besplatnih. Preostalo: {{ $limits['remaining'] }}.</p>
		@endunless
		@include('posting.partials.picker', ['action' => $action, 'baseUrl' => url_slash('postavi-oglas')])
	</section>
@endif
@endsection
