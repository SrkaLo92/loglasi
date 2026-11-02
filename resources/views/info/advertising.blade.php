@extends('info.layout', ['section' => 'oglasavanje', 'title' => 'Oglašavanje za firme iz Loznice', 'description' => 'Istaknuta firma u imeniku, baneri, promo tekstovi i video za firme iz Loznice i okoline. Cene i paketi.', 'canonical' => \App\Support\Info\InfoUrl::to('oglasavanje')])
@use('App\Models\InfoAdInquiry')
@use('App\Support\Info\InfoAds')
@use('App\Support\Info\InfoUrl')
@php
	$p = fn (string $key) => InfoAds::format(InfoAds::price($key));
	$packs = [
		['package_neighbour', 'Komšija', 'Za radnju ili majstora koji želi da ga ljudi iz kraja znaju.', false, ['Istaknuta firma u imeniku', '1 promo tekst mesečno', '1 objava na Instagramu i Facebooku', 'Vrh vaše delatnosti u imeniku']],
		['package_local', 'Lokalni partner', 'Za firme koje svakog meseca žele nove kupce.', true, ['Sve iz paketa Komšija', 'Baner na naslovnoj portala', '2 promo teksta mesečno', '1 kratak video sa glasom', 'Mesečni izveštaj']],
		['package_partner', 'Partner kraja', 'Najvidljivije mesto u Loznici, ceo mesec.', false, ['Sve iz paketa Lokalni partner', 'Sponzor „Danas u Loznici“', 'Svi baneri na portalu', '4 promo teksta i 2 videa', 'Max paket na loglasi.rs']],
	];
	$check = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2b7a64" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
@endphp
@section('content')
<div class="inf-wrap inf-stack">
	<section class="inf-adhero">
		<div class="inf-adhero__text">
			<span class="inf-tag inf-tag--warm">Za firme iz Loznice i okoline</span>
			<h1>Neka vas vide komšije, a ne ceo internet.</h1>
			<p>Vaša firma na dva mesta odjednom: na Info portalu, koji ljudi iz kraja otvaraju zbog struje, apoteke i dešavanja, i na loglasi.rs, gde isti ti ljudi kupuju i traže majstore.</p>
			<div class="inf-btns">
				<a class="inf-btn" href="#upit">Pošaljite upit</a>
				<a class="inf-btn inf-btn--line" href="#paketi">Pogledajte pakete</a>
			</div>
		</div>
		<div class="inf-benefits">
			<div class="inf-benefit">{!! $check !!}<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><strong>Samo naš kraj</strong><span>Loznica, Banja, Lešnica, Mali Zvornik, Krupanj, Ljubovija. Ne plaćate prikaze u Beogradu.</span></div>
			<div class="inf-benefit">{!! $check !!}<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14h3"/></svg><strong>Mesto samo za vas</strong><span>Jedna firma po reklamnom mestu. Ne vrtimo vas sa još dve konkurencije.</span></div>
			<div class="inf-benefit">{!! $check !!}<path d="M4 19V5M4 19h16M8 15l3-4 3 2 5-6"/></svg><strong>Izveštaj svakog meseca</strong><span>Koliko ljudi je kliknulo na vašu reklamu i otvorilo vaš profil.</span></div>
			<div class="inf-benefit">{!! $check !!}<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/></svg><strong>Video sa pravim glasom</strong><span>Snimimo kratak video o vašoj firmi i objavimo ga na Instagramu, Facebooku i TikToku.</span></div>
		</div>
	</section>

	<section class="inf-adsec" aria-labelledby="inf-imenik">
		<div class="inf-adsec__head"><h2 id="inf-imenik">Imenik firmi</h2><p>Svaka firma iz kraja može besplatno da bude u imeniku. Istaknuta firma dobija ceo profil i prvo mesto u svojoj delatnosti.</p></div>
		<div class="inf-grid2">
			<div class="inf-plan">
				<span class="inf-plan__name">Osnovni upis</span>
				<span class="inf-plan__price"><b>Besplatno</b></span>
				<ul><li>Naziv, delatnost, adresa i telefon</li><li>Mesto u pretrazi i delatnosti</li><li>Sami ispravljate podatke</li></ul>
				<a class="inf-btn inf-btn--line" href="#upit">Dodajte firmu</a>
			</div>
			<div class="inf-plan inf-plan--green">
				<span class="inf-plan__name">Istaknuta firma <span class="inf-tag">Uključeno u Pro i Max</span></span>
				<span class="inf-plan__price"><b>{{ $p('featured_month') }}</b><span>mesečno ili {{ $p('featured_year') }} godišnje</span></span>
				<ul><li>Prvo mesto u delatnosti i oznaka Istaknuto</li><li>Galerija fotografija, radno vreme i opis</li><li>Dugmad Pozovi, Viber i Navigacija</li><li>Vaši oglasi sa loglasi.rs se sami prikazuju na profilu</li></ul>
				<a class="inf-btn" href="#upit">Želim Istaknutu firmu</a>
			</div>
		</div>
	</section>

	<section class="inf-adsec" aria-labelledby="inf-mesta">
		<div class="inf-adsec__head"><h2 id="inf-mesta">Reklamna mesta</h2><p>Cene su mesečne. Svako mesto ima samo jednu firmu u istom periodu.</p></div>
		<div class="inf-pricetable" role="table" aria-label="Reklamna mesta">
			<div class="inf-prow" role="row"><span role="columnheader">Mesto</span><span role="columnheader">Gde se vidi</span><span role="columnheader">Cena</span></div>
			<div class="inf-prow" role="row"><span class="inf-prow__what" role="cell"><strong>Sponzor „Danas u Loznici“</strong><span>Vaš naziv uz vreme, apoteku i struju. Najčitaniji deo portala.</span></span><span class="inf-prow__where" role="cell">Info naslovna i servisne informacije</span><b role="cell">{{ $p('today_sponsor') }}</b></div>
			<div class="inf-prow" role="row"><span class="inf-prow__what" role="cell"><strong>Baner ispod teksta vesti</strong><span>Na svakoj vesti, odmah posle teksta.</span></span><span class="inf-prow__where" role="cell">Sve vesti i priče</span><b role="cell">{{ $p('article_bottom') }}</b></div>
			<div class="inf-prow" role="row"><span class="inf-prow__what" role="cell"><strong>Baner na naslovnoj</strong><span>Između vesti i kalendara dešavanja.</span></span><span class="inf-prow__where" role="cell">Info naslovna</span><b role="cell">{{ $p('home_middle') }}</b></div>
			<div class="inf-prow" role="row"><span class="inf-prow__what" role="cell"><strong>Vrh delatnosti u imeniku</strong><span>Na primer prvi među auto servisima ili pekarama.</span></span><span class="inf-prow__where" role="cell">Imenik firmi</span><b role="cell">{{ $p('category_top') }}</b></div>
		</div>
		<p class="inf-small">Izrada banera je besplatna uz zakup od 3 meseca, inače {{ $p('banner_design') }}. Za 3 meseca 10% popusta, za 6 meseci 15%, za godinu dana 25%.</p>
	</section>

	<section class="inf-adsec" aria-labelledby="inf-objave">
		<div class="inf-adsec__head"><h2 id="inf-objave">Objave i video</h2><p>Promo tekst uvek nosi oznaku Promo i ostaje na sajtu trajno, pa ga ljudi nalaze i preko Google-a.</p></div>
		<div class="inf-grid4">
			<div class="inf-offer"><strong>Najava događaja</strong><b>{{ $p('event') }}</b><span>U kalendaru i na našim mrežama. Za škole, udruženja i kulturu besplatno.</span></div>
			<div class="inf-offer"><strong>Promo tekst iz vašeg materijala</strong><b>{{ $p('promo_own') }}</b><span>Uredimo vaš tekst, do 3 fotografije i link ka vama.</span></div>
			<div class="inf-offer"><strong>Promo tekst koji mi pišemo</strong><b>{{ $p('promo_written') }}</b><span>Razgovaramo sa vama i napišemo priču, do 5 fotografija.</span></div>
			<div class="inf-offer inf-offer--dark"><strong>Tekst i kratak video</strong><b>{{ $p('promo_video') }}</b><span>Promo tekst, video sa glasom i objava na Instagramu, Facebooku i TikToku.</span></div>
		</div>
	</section>

	<section class="inf-adsec inf-packs" id="paketi" aria-labelledby="inf-paketi">
		<div class="inf-svhead">
			<div class="inf-adsec__head"><h2 id="inf-paketi">Paketi</h2><p>Sve na jednom računu, uz popust u odnosu na pojedinačne cene.</p></div>
			<fieldset class="inf-period">
				<legend class="inf-visually-hidden">Period plaćanja</legend>
				<label><input type="radio" name="period" id="period-monthly" checked><span>Mesečno</span></label>
				<label><input type="radio" name="period" id="period-yearly"><span>Godišnje, 2 meseca gratis</span></label>
			</fieldset>
		</div>
		<div class="inf-grid3">
			@foreach ($packs as [$key, $name, $lead, $popular, $items])
				<div @class(['inf-plan', 'inf-plan--warm' => $popular])>
					@if ($popular)<span class="inf-plan__badge">Najčešći izbor</span>@endif
					<span class="inf-plan__name">{{ $name }}</span>
					<span class="inf-plan__lead">{{ $lead }}</span>
					<span class="inf-plan__price"><b><span class="inf-monthly">{{ $p($key) }}</span><span class="inf-yearly">{{ InfoAds::format(InfoAds::price($key) * 10) }}</span></b><span><span class="inf-monthly">mesečno</span><span class="inf-yearly">godišnje</span></span></span>
					<span class="inf-plan__save"><span class="inf-monthly">Bez ugovora, otkazujete kad hoćete</span><span class="inf-yearly">Plaćate 10 meseci, dobijate 12</span></span>
					<ul>@foreach ($items as $item)<li>{{ $item }}</li>@endforeach</ul>
					<a @class(['inf-btn', 'inf-btn--warm' => $popular, 'inf-btn--soft' => ! $popular]) href="#upit">Izaberi paket</a>
				</div>
			@endforeach
		</div>
	</section>

	<section class="inf-inquiry" id="upit" aria-labelledby="inf-upit">
		<div>
			<h2 id="inf-upit">Pošaljite upit</h2>
			<p>Javimo se u toku jednog radnog dana sa predlogom i tačnom cenom. Plaćanje je po predračunu, uplatnicom ili IPS QR kodom.</p>
			<p class="inf-small">Ne objavljujemo političke reklame, kladionice ni obmanjujuće oglase. Svaka plaćena objava ima vidljivu oznaku Oglas ili Promo.</p>
		</div>
		<form class="inf-form" method="post" action="{{ InfoUrl::to('oglasavanje') }}">
			@csrf
			<div class="inf-hp" aria-hidden="true"><label>Sajt <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
			<label>Naziv firme<input type="text" name="company" value="{{ old('company') }}" required maxlength="160" placeholder="npr. Pekara Zlatni klas" autocomplete="organization"></label>
			<div class="inf-form__two">
				<label>Telefon<input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" placeholder="06x xxx xxxx" autocomplete="tel"></label>
				<label>Mejl<input type="email" name="email" value="{{ old('email') }}" maxlength="190" placeholder="ime@firma.rs" autocomplete="email"></label>
			</div>
			<label>Šta vas zanima<select name="interest" data-pick>@foreach (InfoAdInquiry::INTERESTS as $key => $label)<option value="{{ $key }}" @selected(old('interest', 'package') === $key)>{{ $label }}</option>@endforeach</select></label>
			<label>Poruka (nije obavezno)<textarea name="message" rows="3" maxlength="2000">{{ old('message') }}</textarea></label>
			@if ($errors->any())<p class="inf-error" role="alert">{{ $errors->first() }}</p>@endif
			<button class="inf-btn" type="submit">Pošalji upit</button>
		</form>
	</section>
</div>
@endsection
