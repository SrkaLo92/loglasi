{{-- Contact buttons. The full number is never in the HTML before "Prikaži broj" (POST, counted). --}}
@use('App\Support\Listing\Icons')
@use('App\Http\Controllers\Listing\ListingController')
@php
	$links = $revealed ? $seller->links() : [];
	$waText = ListingController::whatsappText($listing);
	$formId = 'lgo-otkrij-'.$listing->id;
@endphp
@if (! $seller->hasPhone())
	<div class="lgo-actions lgo-actions--without-phone" id="kontakt">
		<a class="lgo-btn lgo-btn--msg" href="{{ $seller->messageUrl }}" rel="nofollow">{{ Icons::listing('message') }}<span>Pošalji poruku</span></a>
	</div>
@elseif ($revealed)
	<div class="lgo-actions" id="kontakt" data-lgo-actions>
		<a class="lgo-call" id="kontakt-tel" href="{{ $links['tel'] }}" rel="nofollow" aria-label="Pozovi {{ $seller->display() }}">{{ Icons::listing('phone', 20) }}<span class="lgo-call__txt"><span class="lgo-call__label">Pozovi</span> <span class="lgo-call__num">{{ $seller->display() }}</span></span></a>
		<p class="lgo-call__hint">Dodirnite za poziv</p>
		<div class="lgo-actions__row">
			<a class="lgo-btn lgo-btn--msg" href="{{ $seller->messageUrl }}" rel="nofollow">{{ Icons::listing('message') }}<span>Poruka</span></a>
			@if ($seller->whatsapp())
				<a class="lgo-btn lgo-btn--wa" id="kontakt-whatsapp" href="{{ $links['whatsapp'] }}?text={{ rawurlencode($waText) }}" target="_blank" rel="nofollow noopener" aria-label="WhatsApp poruka prodavcu (otvara se u novom prozoru)">{{ Icons::listing('whatsapp') }}<span>WhatsApp</span></a>
			@endif
			@if ($seller->viber())
				<a class="lgo-btn lgo-btn--viber" id="kontakt-viber" href="{{ $links['viber'] }}" rel="nofollow" aria-label="Viber poruka prodavcu">{{ Icons::listing('viber') }}<span>Viber</span></a>
			@endif
		</div>
	</div>
@else
	<form class="lgo-actions" id="kontakt" method="post" action="{{ url_slash('oglas/'.$listing->slug.'/telefon') }}" data-lgo-reveal-form data-lgo-actions>
		@csrf
		<button class="lgo-call" type="submit" name="next" value="tel" data-lgo-act="tel" data-lgo-reveal aria-label="Prikaži broj telefona prodavca">{{ Icons::listing('phone', 20) }}<span class="lgo-call__txt"><span class="lgo-call__label" data-lgo-label>Prikaži broj</span> <span class="lgo-call__num" data-lgo-num>{{ $seller->masked() }}</span></span></button>
		<p class="lgo-call__hint" data-lgo-hint>Dodirnite da vidite ceo broj</p>
		<div class="lgo-actions__row">
			<a class="lgo-btn lgo-btn--msg" href="{{ $seller->messageUrl }}" rel="nofollow">{{ Icons::listing('message') }}<span>Poruka</span></a>
			@if ($seller->whatsapp())
				<button class="lgo-btn lgo-btn--wa" type="submit" name="next" value="whatsapp" data-lgo-act="whatsapp" aria-label="WhatsApp poruka prodavcu">{{ Icons::listing('whatsapp') }}<span>WhatsApp</span></button>
			@endif
			@if ($seller->viber())
				<button class="lgo-btn lgo-btn--viber" type="submit" name="next" value="viber" data-lgo-act="viber" aria-label="Viber poruka prodavcu">{{ Icons::listing('viber') }}<span>Viber</span></button>
			@endif
		</div>
	</form>
@endif
