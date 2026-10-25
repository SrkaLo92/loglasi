{{-- Cookie bar: shown only when the Google tag runs and the visitor has not chosen yet. --}}
@use('App\Support\Settings')
@if (app()->environment('production') && preg_match('/^(GT|G|AW)-[A-Z0-9]{4,20}$/', trim((string) Settings::get('google_tag_id'))) === 1)
{{-- Slim bar at the bottom (above the phone's bottom nav): "U redu" turns measuring on, "Ne, hvala" keeps it off. --}}
<div class="lg-kolacici" id="lg-kolacici" role="region" aria-label="Kolačići" hidden>
	<p>Koristimo kolačiće za merenje posećenosti. <a href="{{ url_slash('/politika-privatnosti') }}">Politika privatnosti</a></p>
	<button type="button" class="lg-kolacici__ne" data-kolacici="ne">Ne, hvala</button>
	<button type="button" class="lg-kolacici__ok" data-kolacici="da">U redu</button>
</div>
<script>
(function () {
	var bar = document.getElementById('lg-kolacici');
	if (!bar) return;
	var body = document.body, pad = body.style.paddingBottom;
	function show() {
		bar.hidden = false;
		// Room at the end of the page, so the bar never hides the last lines of the footer.
		body.style.paddingBottom = ((parseFloat(getComputedStyle(body).paddingBottom) || 0) + bar.offsetHeight) + 'px';
	}
	bar.addEventListener('click', function (e) {
		var value = e.target.getAttribute('data-kolacici');
		if (!value) return;
		try { localStorage.setItem('lg-kolacici', value); } catch (err) {}
		if (window.gtag) { gtag('consent', 'update', {analytics_storage: value === 'da' ? 'granted' : 'denied'}); }
		bar.hidden = true;
		body.style.paddingBottom = pad;
	});
	// "Podešavanje kolačića" in the footer: the choice can be changed at any time.
	var change = document.querySelector('[data-kolacici-izmena]');
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
