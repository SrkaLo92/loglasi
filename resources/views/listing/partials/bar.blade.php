{{-- Sticky contact bar on phones (< 768 px); hidden while the card buttons are on screen. --}}
@use('App\Support\Listing\Icons')
@php
	$hasPhone = $seller->hasPhone();
	$buttons = $hasPhone ? 1 + 1 + ($seller->whatsapp() ? 1 : 0) : 1;
	$links = $revealed ? $seller->links() : [];
@endphp
<div class="lgo lgo-bar" role="region" aria-label="Kontakt sa prodavcem" data-lgo-bar>
	@if ($hasPhone && ! $revealed)
		<form class="lgo-bar__in lgo-bar__in--{{ $buttons }}" method="post" action="{{ url_slash('oglas/'.$listing->slug.'/telefon') }}" data-lgo-reveal-form data-lgo-bar-form>
			@csrf
			<button type="submit" name="next" value="tel" class="lgo-bar__btn lgo-bar__btn--tel" data-lgo-act="tel" data-lgo-call-now aria-label="Pozovi prodavca">{{ Icons::listing('phone') }}<span>Pozovi</span></button>
			<a class="lgo-bar__btn lgo-bar__btn--msg" href="{{ $seller->messageUrl }}" rel="nofollow" aria-label="Pošalji poruku prodavcu">{{ Icons::listing('message') }}<span>Poruka</span></a>
			@if ($seller->whatsapp())
				<button type="submit" name="next" value="whatsapp" class="lgo-bar__btn lgo-bar__btn--wa" data-lgo-act="whatsapp" aria-label="Piši prodavcu na WhatsApp">{{ Icons::listing('whatsapp') }}<span>WhatsApp</span></button>
			@endif
		</form>
	@else
		<div class="lgo-bar__in lgo-bar__in--{{ $buttons }}">
			@if ($hasPhone)
				<a class="lgo-bar__btn lgo-bar__btn--tel" href="{{ $links['tel'] }}" rel="nofollow" aria-label="Pozovi prodavca">{{ Icons::listing('phone') }}<span>Pozovi</span></a>
			@endif
			<a class="lgo-bar__btn lgo-bar__btn--msg" href="{{ $seller->messageUrl }}" rel="nofollow" aria-label="Pošalji poruku prodavcu">{{ Icons::listing('message') }}<span>Poruka</span></a>
			@if ($hasPhone && $seller->whatsapp())
				<a class="lgo-bar__btn lgo-bar__btn--wa" href="{{ $links['whatsapp'] }}?text={{ rawurlencode(\App\Http\Controllers\Listing\ListingController::whatsappText($listing)) }}" target="_blank" rel="nofollow noopener" aria-label="Piši prodavcu na WhatsApp (otvara se u novom prozoru)">{{ Icons::listing('whatsapp') }}<span>WhatsApp</span></a>
			@endif
		</div>
	@endif
</div>
