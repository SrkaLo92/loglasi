{{-- Cookie bar: shown only when the Google tag runs and the visitor has not chosen yet. --}}
@use('App\Support\Settings')
@if (app()->environment('production') && preg_match('/^(GT|G|AW)-[A-Z0-9]{4,20}$/', trim((string) Settings::get('google_tag_id'))) === 1)
{{-- Slim bar at the bottom (above the phone's bottom nav): "U redu" turns measuring on, "Ne, hvala" keeps it off. --}}
<div class="lg-cookies" id="lg-cookies" role="region" aria-label="Kolačići" hidden>
	<p>Koristimo kolačiće za merenje posećenosti. <a href="{{ url_slash('/politika-privatnosti') }}">Politika privatnosti</a></p>
	<button type="button" class="lg-cookies__no" data-cookies="ne">Ne, hvala</button>
	<button type="button" class="lg-cookies__ok" data-cookies="da">U redu</button>
</div>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
	var bar = document.getElementById('lg-cookies');
	if (!bar) return;
	var body = document.body, pad = body.style.paddingBottom;
	function show() {
		bar.hidden = false;
		// Room at the end of the page, so the bar never hides the last lines of the footer.
		body.style.paddingBottom = ((parseFloat(getComputedStyle(body).paddingBottom) || 0) + bar.offsetHeight) + 'px';
	}
	bar.addEventListener('click', function (e) {
		var value = e.target.getAttribute('data-cookies');
		if (!value) return;
		// The stored key keeps its old name, so earlier choices still count.
		try { localStorage.setItem('lg-kolacici', value); } catch (err) {}
		if (window.gtag) { gtag('consent', 'update', {analytics_storage: value === 'da' ? 'granted' : 'denied'}); }
		bar.hidden = true;
		body.style.paddingBottom = pad;
	});
	// "Podešavanje kolačića" in the footer: the choice can be changed at any time.
	var change = document.querySelector('[data-cookies-change]');
	if (change) {
		change.hidden = false;
		change.addEventListener('click', show);
	}
	var choice = null;
	try { choice = localStorage.getItem('lg-kolacici'); } catch (e) {}
	if (!choice) show();
})();
</script>
@endif
