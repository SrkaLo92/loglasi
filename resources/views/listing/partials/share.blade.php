@use('App\Support\Listing\Icons')
@php
	$url = url_slash('oglas/'.$listing->slug);
	$text = $listing->title.' – '.$listing->priceLabel();
@endphp
<div class="lgo lgo-share" data-lgo-share data-url="{{ $url }}" data-title="{{ $listing->title }}" data-text="{{ $text }}">
	<span class="lgo-share__label">{{ Icons::listing('share', 16) }}<span>Podeli oglas</span></span>
	<button type="button" class="lgo-share__btn lgo-share__native" data-lgo-share-native hidden>Podeli</button>
	<a class="lgo-share__btn" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($url) }}" target="_blank" rel="noopener nofollow" aria-label="Podeli na Facebooku (otvara se u novom prozoru)">{{ Icons::listing('facebook', 16) }}<span>Facebook</span></a>
	<a class="lgo-share__btn" href="https://wa.me/?text={{ rawurlencode($text.' '.$url) }}" target="_blank" rel="noopener nofollow" aria-label="Pošalji preko WhatsApp-a (otvara se u novom prozoru)">{{ Icons::listing('whatsapp', 16) }}<span>WhatsApp</span></a>
	<a class="lgo-share__btn" href="viber://forward?text={{ rawurlencode($text.' '.$url) }}" rel="nofollow" aria-label="Pošalji preko Vibera">{{ Icons::listing('viber', 16) }}<span>Viber</span></a>
	<button type="button" class="lgo-share__btn" data-lgo-copy hidden>{{ Icons::listing('link', 16) }}<span data-lgo-copy-label>Kopiraj link</span></button>
</div>
