@use('App\Support\Listing\Icons')
@use('App\Support\Listing\Text')
<section class="lgo lgo-card lgo-seller lgo-o4 {{ $seller->isFirm ? 'lgo-seller--firm' : 'lgo-seller--private' }}" aria-label="Prodavac: {{ $seller->name }}">
	<div class="lgo-seller__head">
		<a class="lgo-seller__avatar" href="{{ $seller->profileUrl }}" tabindex="-1" aria-hidden="true">
			@if ($seller->logoUrl)
				<img src="{{ $seller->logoUrl }}" alt="" width="56" height="56" loading="lazy" decoding="async">
			@else
				<span>{{ $seller->initials }}</span>
			@endif
		</a>
		<div class="lgo-seller__who">
			<a class="lgo-seller__name" href="{{ $seller->profileUrl }}">{{ $seller->name }}</a>
			@if ($seller->user->isStaff())
				<span class="lgo-badge lgo-badge--firm">{{ Icons::listing('check') }}loglasi.rs</span>
			@elseif ($seller->isFirm)
				<span class="lgo-badge lgo-badge--firm">{{ Icons::listing('check') }}Firma</span>
			@else
				<span class="lgo-badge lgo-badge--private">Privatno lice</span>
			@endif
		</div>
	</div>
	<ul class="lgo-seller__facts">
		@if ($since = Text::memberSince($seller->user->created_at))
			<li>{{ Icons::listing('calendar') }}<span>{{ $since }}</span></li>
		@endif
		<li>{{ Icons::listing('list') }}<a href="{{ $seller->profileUrl }}">{{ Text::activeListings($seller->activeCount) }}</a></li>
		@if ($seller->user->rating_count > 0)
			<li class="lgo-seller__rating"><span class="lgo-seller__star" aria-hidden="true">★</span><a href="{{ url_slash('korisnik/'.$seller->user->slug.'/ocene') }}"><strong>{{ \App\Support\Ratings\RatingPresenter::average((float) $seller->user->rating_avg) }}</strong> ({{ \App\Support\Ratings\RatingPresenter::count((int) $seller->user->rating_count) }})</a></li>
		@endif
		@if ($seller->user->isPhoneVerified())
			<li>{{ Icons::listing('shield') }}<span>Telefon proveren</span></li>
		@endif
	</ul>
	<a class="lgo-seller__all" href="{{ $seller->profileUrl }}">
		<span>{{ $seller->isFirm ? 'Profil firme i svi oglasi' : 'Svi oglasi prodavca' }}</span>
		{{ Icons::listing('arrow') }}
	</a>
</section>
