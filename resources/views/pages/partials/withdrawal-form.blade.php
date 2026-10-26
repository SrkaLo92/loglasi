{{-- Withdrawal form (Zakon o zaštiti potrošača): seller data from settings, field labels from page texts. Prints on its own. --}}
@use('App\Support\Pages\PageText')
@use('App\Support\Pages\SellerIdentity')
@php($lgSeller = SellerIdentity::rows())
<section class="lgx-obrazac" id="obrazac" aria-labelledby="lgx-obrazac-naslov">
	<h2 class="lgx-obrazac__naslov" id="lgx-obrazac-naslov">{{ PageText::get('odustanak.obrazac.naslov') }}</h2>
	<p class="lgx-obrazac__uvod">{{ PageText::get('odustanak.obrazac.uvod') }}</p>

	<div class="lgx-obrazac__primalac">
		<p class="lgx-obrazac__oznaka">{{ PageText::get('odustanak.obrazac.primalac') }}:</p>
		@if ($lgSeller === [])
			<p>{{ PageText::inline('Podatke o prodavcu šaljemo na zahtev preko [kontakt forme](/kontakt/).') }}</p>
		@else
			<dl>
				@foreach ($lgSeller as $row)
					<div><dt>{{ $row['label'] }}</dt><dd>{{ $row['value'] }}</dd></div>
				@endforeach
			</dl>
		@endif
	</div>

	<p class="lgx-obrazac__izjava">{{ PageText::get('odustanak.obrazac.izjava') }}</p>

	<div class="lgx-obrazac__polja">
		@foreach (PageText::list('odustanak.obrazac.polja') as $i => $label)
			<div class="lgx-polje lgx-obrazac__polje">
				<label for="lgx-obrazac-{{ $i }}">{{ $label }}</label>
				<input type="text" id="lgx-obrazac-{{ $i }}" autocomplete="off">
			</div>
		@endforeach
		<div class="lgx-obrazac__potpis">
			<div><span class="lgx-obrazac__linija" aria-hidden="true"></span><p>{{ PageText::get('odustanak.obrazac.potpis') }}</p></div>
			<div><span class="lgx-obrazac__linija" aria-hidden="true"></span><p>{{ PageText::get('odustanak.obrazac.datum') }}</p></div>
		</div>
	</div>

	<p class="lgx-obrazac__uputstvo">{{ PageText::inline((string) PageText::raw('odustanak.obrazac.uputstvo')) }}</p>
	<p class="lgx-obrazac__stampa" data-lgx-stampa hidden>
		<button type="button" class="lg-btn lg-btn--green">{{ PageText::get('odustanak.obrazac.stampa') }}</button>
	</p>
</section>
<script>
(function () {
	var wrap = document.querySelector('[data-lgx-stampa]');
	if (!wrap || !window.print) return;
	wrap.hidden = false;
	wrap.querySelector('button').addEventListener('click', function () { window.print(); });
})();
</script>
