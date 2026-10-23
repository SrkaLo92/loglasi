@extends('layouts.app')
@use('App\Support\Listing\Icons')
@use('App\Support\Listing\Text')
@use('App\Support\Listing\WorkingHours')
@use('App\Support\Phone')
@use('App\Support\Seo')

@push('head')
<link rel="stylesheet" href="{{ asset_v('css/listing-profile.css') }}">
@endpush

@push('scripts')
<script src="{{ asset_v('js/listing-profile.js') }}" defer></script>
@endpush

@section('content')
@php
	$name = $firm->name ?: $user->name;
	$cover = $firm->coverUrl();
	$logo = $firm->logoUrl();
	$links = $phone !== '' ? Phone::links($phone) : [];
	$address = trim((string) $firm->address);
	$website = trim((string) $firm->website);
	$views = (int) $firm->profile_views;
	$scheduleJson = json_encode((object) $schedule);
	$mapUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(implode(', ', array_filter([$address, $city, 'Srbija'])));
	$today = (int) now(Text::TIMEZONE)->format('N');
	$prettyUrl = function (string $url): string {
		$host = (string) parse_url($url, PHP_URL_HOST);
		$path = (string) parse_url($url, PHP_URL_PATH);
		$text = preg_replace('/^www\./i', '', $host).($path === '/' ? '' : $path);

		return $text !== '' ? $text : $url;
	};
	$statusHtml = function (?array $status, string $class = '') {
		$state = $status === null ? '' : ($status['open'] ? ' is-open' : ' is-closed');

		return new \Illuminate\Support\HtmlString('<span class="lgp-status '.e($class).$state.'" data-lgp-status'.($status === null ? ' hidden' : '').'>'.e($status['text'] ?? '').'</span>');
	};
	$hint = function (string $text) use ($canEdit, $editUrl) {
		return view('listing.profile.hint', compact('text', 'canEdit', 'editUrl'));
	};
@endphp
<div class="lgp lgp-firma-strana{{ $hasSticky ? ' has-sticky' : '' }}" data-lgp-user="{{ $user->id }}">
	<section class="lgp-firma" aria-labelledby="lgp-ime-{{ $user->id }}">
		<div class="lgp-firma__cover{{ $cover ? '' : ' lgp-firma__cover--empty' }}">
			@if ($cover)
				<img class="lgp-firma__cover-img" src="{{ $cover }}" alt="" width="1240" height="220" loading="eager" fetchpriority="high" decoding="async">
			@endif
		</div>
		<div class="lgp-firma__head">
			<div class="lgp-firma__logo">
				@if ($logo)
					<img class="lgp-firma__logo-img" src="{{ $logo }}" alt="Logo: {{ $name }}" width="120" height="120" loading="eager" decoding="async">
				@else
					<span class="lgp-firma__initials" aria-hidden="true">{{ Text::initials($name) }}</span>
				@endif
			</div>
			<div class="lgp-firma__title">
				<h1 class="lgp-firma__name" id="lgp-ime-{{ $user->id }}">{{ Seo::get()->getH1() ?? $name }}</h1>
				<ul class="lgp-badges" aria-label="Oznake">
					<li class="lgp-badge lgp-badge--firm">{{ Icons::profile('shop', 14) }}Firma</li>
					@if ($firm->verified_at)
						<li class="lgp-badge lgp-badge--ok" title="Podatke o firmi je proverio loglasi.rs">{{ Icons::profile('check', 14) }}Proverena firma</li>
					@endif
					@if ($phone !== '' && $user->isPhoneVerified())
						<li class="lgp-badge lgp-badge--soft">{{ Icons::profile('phone', 14) }}Telefon proveren</li>
					@endif
				</ul>
				@if ($firm->activity)
					<p class="lgp-firma__activity">{{ $firm->activity }}</p>
				@endif
				<ul class="lgp-facts">
					<li>{{ Icons::profile('tag', 16) }}<a href="#lgp-oglasi">{{ Text::activeListings($total) }}</a></li>
					<li>{{ Icons::profile('pin', 16) }}<span>{{ $address !== '' ? $address.', '.$city : $city }}</span></li>
					@if (($since = Text::memberSince($user->created_at)) !== '')
						<li>{{ Icons::profile('calendar', 16) }}<span>{{ $since }}</span></li>
					@endif
					@if ($views >= 10)
						<li>{{ Icons::profile('eye', 16) }}<span>{{ Text::number($views) }} {{ Text::plural($views, 'pregled profila', 'pregleda profila', 'pregleda profila') }}</span></li>
					@endif
					@if ($hasHours)
						<li class="lgp-facts__status" data-lgp-hours="{{ $scheduleJson }}"@if ($status === null) hidden @endif>{{ Icons::profile('clock', 16) }}{{ $statusHtml($status) }}</li>
					@endif
				</ul>
			</div>
			<div class="lgp-firma__actions">
				@include('listing.profile.actions', ['isFirm' => true])
			</div>
		</div>
	</section>

	<div class="lgp-firma__cols">
		<div class="lgp-firma__main">
			@include('listing.profile.listings', ['title' => 'Oglasi firme'])
		</div>
		<aside class="lgp-firma__side" aria-label="Podaci o firmi">
			<details class="lgp-firma__more" open data-lgp-collapse>
				<summary class="lgp-firma__more-sum">{{ Icons::profile('shop', 20) }}<span>O firmi i kontakt</span><span class="lgp-firma__more-chev" aria-hidden="true"></span></summary>
				<div class="lgp-firma__more-in">
					@if ($firm->description || $canEdit)
						<section class="lgp-box" aria-labelledby="lgp-o-firmi-{{ $user->id }}">
							<h2 class="lgp-box__title" id="lgp-o-firmi-{{ $user->id }}">{{ Icons::profile('shop', 20) }}<span>O firmi</span></h2>
							@if ($firm->description)
								<div class="lgp-box__text">{{ Text::description($firm->description) }}</div>
							@else
								{{ $hint('Dodajte kratak opis firme: čime se bavite i šta nudite.') }}
							@endif
							@unless ($cover)
								{{ $hint('Dodajte naslovnu sliku i logo.') }}
							@endunless
						</section>
					@endif

					<section class="lgp-box" aria-labelledby="lgp-kontakt-{{ $user->id }}">
						<h2 class="lgp-box__title" id="lgp-kontakt-{{ $user->id }}">{{ Icons::profile('phone', 20) }}<span>Kontakt</span></h2>
						<ul class="lgp-list">
							@if ($links)
								<li>{{ Icons::profile('phone', 18) }}<a href="{{ $links['tel'] }}"><span class="lgp-sr">Telefon: </span>{{ Phone::display($phone, true) }}</a></li>
							@endif
							@if ($website !== '' && preg_match('#^https?://#i', $website))
								<li>{{ Icons::profile('globe', 18) }}<a href="{{ $website }}" target="_blank" rel="noopener nofollow"><span class="lgp-sr">Sajt: </span>{{ $prettyUrl($website) }}</a></li>
							@endif
							@unless ($isOwner)
								<li>{{ Icons::profile('message', 18) }}<a href="{{ $messageUrl }}" rel="nofollow">Pošaljite poruku preko sajta</a></li>
							@endunless
						</ul>
						@if ($phone === '')
							{{ $hint('Dodajte broj telefona da bi se pojavila dugmad Pozovi, WhatsApp i Viber.') }}
						@endif
					</section>

					@if ($hasHours)
						<section class="lgp-box" aria-labelledby="lgp-vreme-{{ $user->id }}">
							<h2 class="lgp-box__title" id="lgp-vreme-{{ $user->id }}">{{ Icons::profile('clock', 20) }}<span>Radno vreme</span></h2>
							<div class="lgp-hours" data-lgp-hours="{{ $scheduleJson }}">
								{{ $statusHtml($status, 'lgp-status--block') }}
								<table class="lgp-hours__table">
									<caption class="lgp-sr">Radno vreme po danima</caption>
									<tbody>
										@foreach (WorkingHours::DAYS as $day => [$full])
											<tr data-lgp-day="{{ $day }}"@if ($day === $today) class="is-today"@endif>
												<th scope="row">{{ $full }}</th>
												<td>{{ $hours[$day] !== '' ? $hours[$day] : (($schedule[$day] ?? null) === [] ? 'Zatvoreno' : '–') }}</td>
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</section>
					@elseif ($canEdit)
						<section class="lgp-box" aria-labelledby="lgp-vreme-{{ $user->id }}">
							<h2 class="lgp-box__title" id="lgp-vreme-{{ $user->id }}">{{ Icons::profile('clock', 20) }}<span>Radno vreme</span></h2>
							{{ $hint('Dodajte radno vreme da bi kupci videli da li ste sada otvoreni.') }}
						</section>
					@endif

					@if ($address !== '')
						<section class="lgp-box" aria-labelledby="lgp-mapa-{{ $user->id }}">
							<h2 class="lgp-box__title" id="lgp-mapa-{{ $user->id }}">{{ Icons::profile('map', 20) }}<span>Mapa</span></h2>
							<p class="lgp-box__text">{{ $address }}<br>{{ $city }}</p>
							<a class="lgp-map" href="{{ $mapUrl }}" target="_blank" rel="noopener nofollow"><span class="lgp-map__pin">{{ Icons::profile('pin', 28) }}</span><span class="lgp-map__label">Pogledaj na mapi</span><span class="lgp-sr"> (Google mape, otvara se u novom prozoru)</span></a>
						</section>
					@elseif ($canEdit)
						<section class="lgp-box" aria-labelledby="lgp-mapa-{{ $user->id }}">
							<h2 class="lgp-box__title" id="lgp-mapa-{{ $user->id }}">{{ Icons::profile('map', 20) }}<span>Mapa</span></h2>
							{{ $hint('Dodajte adresu da bi vas kupci lakše našli na mapi.') }}
						</section>
					@endif

					@php
						$socials = array_filter([
							'facebook' => [trim((string) $firm->facebook), 'Facebook'],
							'instagram' => [trim((string) $firm->instagram), 'Instagram'],
						], fn ($s) => preg_match('#^https?://#i', $s[0]));
					@endphp
					@if ($socials)
						<section class="lgp-box" aria-labelledby="lgp-mreze-{{ $user->id }}">
							<h2 class="lgp-box__title" id="lgp-mreze-{{ $user->id }}">{{ Icons::profile('globe', 20) }}<span>Mreže</span></h2>
							<ul class="lgp-socials">
								@foreach ($socials as $icon => [$url, $label])
									<li><a class="lgp-social" href="{{ $url }}" target="_blank" rel="noopener nofollow">{{ Icons::profile($icon, 20) }}<span>{{ $label }}</span><span class="lgp-sr"> (otvara se u novom prozoru)</span></a></li>
								@endforeach
							</ul>
						</section>
					@endif

					@if ($stats !== null)
						<section class="lgp-box lgp-stats" aria-labelledby="lgp-statistika-{{ $user->id }}">
							<h2 class="lgp-box__title" id="lgp-statistika-{{ $user->id }}">{{ Icons::profile('chart', 20) }}<span>Statistika (30 dana)</span></h2>
							<ul class="lgp-stats__grid">
								@foreach (['views' => 'Pregledi oglasa', 'phone_reveals' => 'Prikazi broja', 'messages' => 'Poruke', 'favorites' => 'Sačuvano'] as $key => $label)
									<li class="lgp-stats__item"><span class="lgp-stats__num">{{ Text::number($stats[$key]) }}</span><span class="lgp-stats__label">{{ $label }}</span></li>
								@endforeach
							</ul>
							<p class="lgp-stats__note">Vidite samo vi (i administratori). Pregledi profila ukupno: {{ Text::number($views) }}.</p>
						</section>
					@endif
				</div>
			</details>
		</aside>
	</div>

	@if ($hasSticky)
		<div class="lgp-sticky" role="region" aria-label="Brzi kontakt">
			<a class="lgp-btn lgp-btn--call" href="{{ $links['tel'] }}">{{ Icons::profile('phone') }}<span class="lgp-btn__text">Pozovi firmu</span></a>
			@unless ($isOwner)
				<a class="lgp-btn lgp-btn--line lgp-btn--icon lgp-btn--msg" href="{{ $messageUrl }}" rel="nofollow" aria-label="Pošaljite poruku firmi">{{ Icons::profile('message', 22) }}</a>
			@endunless
			@if ($firm->messengerEnabled('whatsapp', $phone))
				<a class="lgp-btn lgp-btn--line lgp-btn--icon lgp-btn--wa" href="{{ $links['whatsapp'] }}" target="_blank" rel="noopener nofollow" aria-label="WhatsApp poruka firmi (otvara se u novom prozoru)">{{ Icons::profile('whatsapp', 22) }}</a>
			@endif
		</div>
	@endif
</div>
@endsection
