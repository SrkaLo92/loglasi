{{-- Firm package status box. $status from FirmAccount::status(). Never says "Founder" (owner's rule). --}}
@php
	$tz = config('app.timezone');
	$until = $status['until']?->copy()->timezone($tz)->format('j. n. Y.');
	$days = $status['days_left'];
	$daysLabel = $days === null ? '' : $days.' '.($days % 10 === 1 && $days % 100 !== 11 ? 'dan' : 'dana');
	$num = fn ($n) => number_format((int) $n, 0, ',', '.');
	$active = in_array($status['state'], ['active', 'expiring'], true);
@endphp
<section class="lgf-card lgf-status" aria-labelledby="paket-status">
	<div>
		<div class="lgf-status__title">
			<h2 id="paket-status">{{ $active && $status['label'] ? $status['label'] : 'Paket za firme' }}</h2>
			@switch($status['state'])
				@case('active')<span class="lgf-badge lgf-badge--ok">Aktivan</span>@break
				@case('expiring')<span class="lgf-badge lgf-badge--warn">Ističe uskoro</span>@break
				@case('expired')<span class="lgf-badge lgf-badge--off">Istekao</span>@break
				@default<span class="lgf-badge lgf-badge--off">Nije aktivan</span>
			@endswitch
			@if ($status['verified'])<span class="lgf-badge lgf-badge--ok">Proverena firma</span>@endif
		</div>

		@if ($active)
			<p>
				@if ($status['free_program'])
					Besplatna prva godina za prvih 30 firmi, važi do <strong>{{ $until }}</strong>{{ $days !== null && $days >= 0 ? ' (još '.$daysLabel.')' : '' }}.
				@else
					{{ $status['period'] === 'yearly' ? 'Godišnja' : 'Mesečna' }} pretplata, važi do <strong>{{ $until }}</strong>{{ $days !== null && $days >= 0 ? ' (još '.$daysLabel.')' : '' }}.
				@endif
			</p>
			@if ($status['free_program'])
				<p class="lgf-help">Posle besplatne godine nema automatske naplate: paket birate sami, ili firma prelazi na besplatan nalog sa limitom privatnog lica.</p>
			@else
				<p class="lgf-help">Pretplata se ne obnavlja sama. Produžavate je uplatom pre isteka.</p>
			@endif
			@if ($status['credits'] !== null && array_sum($status['credits_granted']) > 0)
				<p>Uključeno ovog meseca:
					podizanja {{ $status['credits']['bumps'] }} od {{ $status['credits_granted']['bumps'] }}@if ($status['credits_granted']['highlights']), isticanja {{ $status['credits']['highlights'] }} od {{ $status['credits_granted']['highlights'] }}@endif @if ($status['credits_granted']['premium_xl']), Premium XL {{ $status['credits']['premium_xl'] }} od {{ $status['credits_granted']['premium_xl'] }}@endif.
				</p>
			@endif
		@elseif ($status['state'] === 'expired')
			<p>{{ $status['label'] ?: 'Paket' }} je istekao {{ $until }}. Podaci firme su sačuvani, ali profil se sada prikazuje kao profil privatnog lica, firma nije u imeniku i važi limit oglasa privatnog lica.</p>
		@else
			<p>Firma još nema paket. Podaci koje unesete ovde biće vidljivi čim se paket uključi.</p>
		@endif

		<p class="lgf-help">
			@if ($status['limit']['unlimited'])
				Aktivni oglasi: {{ $status['limit']['used_this_month'] }}, bez ograničenja.
			@else
				Aktivni oglasi: {{ $status['limit']['used_this_month'] }} od {{ $status['limit']['monthly_limit'] }}.
			@endif
		</p>

		@if ($status['state'] !== 'active')
			<ul>
				@foreach ($status['packages'] as $package)
					@if ($package['monthly'] || $package['yearly'])
						<li><strong>{{ $package['label'] }}</strong>:
							@if ($package['monthly']){{ $num($package['monthly']) }} din mesečno@endif{{ $package['monthly'] && $package['yearly'] ? ' ili ' : '' }}@if ($package['yearly']){{ $num($package['yearly']) }} din godišnje@endif
						</li>
					@endif
				@endforeach
			</ul>
			<ul>
				@foreach ($status['benefits'] as $benefit)
					<li>{{ $benefit }}</li>
				@endforeach
			</ul>
		@endif
	</div>

	<div class="lgf-status__actions">
		@if ($status['pending_order'])
			<p class="lgf-help" role="status">Narudžbina {{ $status['pending_order']->reference }} čeka uplatu. Paket se uključuje kada uplata stigne.</p>
		@elseif ($status['state'] !== 'active')
			<a class="lg-btn lg-btn--orange" href="{{ $status['renew_url'] }}">{{ $status['state'] === 'none' || $status['free_program'] ? 'Izaberite paket' : 'Produžite paket' }}</a>
		@else
			<a class="lg-btn lg-btn--outline" href="{{ $status['renew_url'] }}">Paketi i cene</a>
		@endif
	</div>
</section>
