{{-- Withdrawal form (Zakon o zaštiti potrošača): seller data from settings, field labels from page texts. Prints on its own. --}}
@use('App\Support\Pages\PageText')
@use('App\Support\Pages\SellerIdentity')
@php($lgSeller = SellerIdentity::rows())
<section class="lgx-wform" id="obrazac" aria-labelledby="lgx-wform-title">
	<h2 class="lgx-wform__title" id="lgx-wform-title">{{ PageText::get('odustanak.obrazac.naslov') }}</h2>
	<p class="lgx-wform__intro">{{ PageText::get('odustanak.obrazac.uvod') }}</p>

	<div class="lgx-wform__recipient">
		<p class="lgx-wform__label">{{ PageText::get('odustanak.obrazac.primalac') }}:</p>
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

	<p class="lgx-wform__statement">{{ PageText::get('odustanak.obrazac.izjava') }}</p>

	<div class="lgx-wform__fields">
		@foreach (PageText::list('odustanak.obrazac.polja') as $i => $label)
			<div class="lgx-field lgx-wform__field">
				<label for="lgx-wform-{{ $i }}">{{ $label }}</label>
				<input type="text" id="lgx-wform-{{ $i }}" autocomplete="off">
			</div>
		@endforeach
		<div class="lgx-wform__signature">
			<div><span class="lgx-wform__line" aria-hidden="true"></span><p>{{ PageText::get('odustanak.obrazac.potpis') }}</p></div>
			<div><span class="lgx-wform__line" aria-hidden="true"></span><p>{{ PageText::get('odustanak.obrazac.datum') }}</p></div>
		</div>
	</div>

	<p class="lgx-wform__instructions">{{ PageText::inline((string) PageText::raw('odustanak.obrazac.uputstvo')) }}</p>
	<p class="lgx-wform__print" data-lgx-print hidden>
		<button type="button" class="lg-btn lg-btn--green">{{ PageText::get('odustanak.obrazac.stampa') }}</button>
	</p>
</section>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
	var wrap = document.querySelector('[data-lgx-print]');
	if (!wrap || !window.print) return;
	wrap.hidden = false;
	wrap.querySelector('button').addEventListener('click', function () { window.print(); });
})();
</script>
