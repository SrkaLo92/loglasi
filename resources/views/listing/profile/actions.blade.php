{{-- Contact buttons: firm (Pozovi, WhatsApp, Viber, Poruka) or private person (Pozovi, Poruka). --}}
@use('App\Support\Listing\Icons')
@use('App\Support\Phone')
@php
	$links = $phone !== '' ? Phone::links($phone) : [];
@endphp
<div class="lgp-actions{{ $isFirm ? '' : ' lgp-actions--compact' }}">
	@if ($links)
		<a class="lgp-btn lgp-btn--call" href="{{ $links['tel'] }}">{{ Icons::profile('phone') }}<span class="lgp-btn__text"><span>Pozovi</span><span class="lgp-btn__sub">{{ Phone::display($phone, true) }}</span></span></a>
		@if ($isFirm && $firm->messengerEnabled('whatsapp', $phone))
			<a class="lgp-btn lgp-btn--line lgp-btn--wa" href="{{ $links['whatsapp'] }}" target="_blank" rel="noopener nofollow">{{ Icons::profile('whatsapp') }}<span class="lgp-btn__text">WhatsApp</span><span class="lgp-sr"> (otvara se u novom prozoru)</span></a>
		@endif
		@if ($isFirm && $firm->messengerEnabled('viber', $phone))
			<a class="lgp-btn lgp-btn--line lgp-btn--viber" href="{{ $links['viber'] }}" rel="nofollow">{{ Icons::profile('viber') }}<span class="lgp-btn__text">Viber</span></a>
		@endif
	@endif
	@unless ($isOwner)
		<a class="lgp-btn lgp-btn--line{{ $isFirm ? ' lgp-btn--msg' : '' }}" href="{{ $messageUrl }}" rel="nofollow">{{ Icons::profile('message') }}<span class="lgp-btn__text">Poruka</span></a>
	@endunless
</div>
