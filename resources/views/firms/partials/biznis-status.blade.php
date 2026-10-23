{{-- Biznis package status box. $status from FirmAccount::status(). --}}
@php
	$until = $status['until']?->copy()->timezone(config('app.timezone'))->format('j. n. Y.');
	$days = $status['days_left'];
	$daysLabel = $days === null ? '' : $days.' '.($days % 10 === 1 && $days % 100 !== 11 ? 'dan' : 'dana');
@endphp
<section class="lgf-card lgf-status" aria-labelledby="biznis-status">
	<div>
		<div class="lgf-status__title">
			<h2 id="biznis-status">Biznis paket</h2>
			@switch($status['state'])
				@case('active')<span class="lgf-badge lgf-badge--ok">Aktivan</span>@break
				@case('expiring')<span class="lgf-badge lgf-badge--warn">Ističe uskoro</span>@break
				@case('expired')<span class="lgf-badge lgf-badge--off">Istekao</span>@break
				@default<span class="lgf-badge lgf-badge--off">Nije aktivan</span>
			@endswitch
			@if ($status['founding'])<span class="lgf-badge lgf-badge--founding">Osnivačka firma</span>@endif
			@if ($status['verified'])<span class="lgf-badge lgf-badge--ok">Proverena firma</span>@endif
		</div>

		@if (in_array($status['state'], ['active', 'expiring'], true))
			<p>Važi do <strong>{{ $until }}</strong>{{ $days !== null && $days >= 0 ? ' (još '.$daysLabel.')' : '' }}.</p>
		@elseif ($status['state'] === 'expired')
			<p>Paket je istekao {{ $until }}. Podaci firme su sačuvani, ali profil se sada prikazuje kao profil privatnog lica, firma nije u imeniku i važi limit oglasa privatnog lica.</p>
		@else
			<p>Firma još nema Biznis paket. Podaci koje unesete ovde biće vidljivi čim se paket uključi.</p>
		@endif

		@if ($status['founding'] && in_array($status['state'], ['active', 'expiring'], true))
			<p class="lgf-help">Vaša firma je među prvim firmama na loglasi.rs i prvu godinu ima paket besplatno.</p>
		@endif

		<p class="lgf-help">
			@if ($status['limit']['unlimited'])
				Oglasi ovog meseca: {{ $status['limit']['used_this_month'] }}, bez ograničenja.
			@else
				Oglasi ovog meseca: {{ $status['limit']['used_this_month'] }} od {{ $status['limit']['monthly_limit'] }}.
			@endif
		</p>

		@if ($status['state'] !== 'active')
			<ul>
				@foreach ($status['benefits'] as $benefit)
					<li>{{ $benefit }}</li>
				@endforeach
			</ul>
		@endif
	</div>

	<div class="lgf-status__actions">
		@if ($status['pending_order'])
			<p class="lgf-help" role="status">Narudžbina {{ $status['pending_order']->reference }} čeka uplatu. Paket se produžava kada uplata stigne.</p>
		@elseif ($status['state'] !== 'active')
			<a class="lg-btn lg-btn--orange" href="{{ $status['renew_url'] }}">{{ $status['state'] === 'none' ? 'Uključite Biznis paket' : 'Produžite Biznis paket' }}</a>
			@if ($status['price_rsd'])
				<p class="lgf-help">{{ number_format($status['price_rsd'], 0, ',', '.') }} din za 12 meseci, uplata na račun.</p>
			@endif
		@else
			<a class="lg-btn lg-btn--outline" href="{{ $status['renew_url'] }}">Cenovnik</a>
		@endif
	</div>
</section>
