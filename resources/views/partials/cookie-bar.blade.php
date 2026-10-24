{{-- Cookie bar: shown only when the Google tag runs and the visitor has not chosen yet. --}}
@use('App\Support\Settings')
@if (app()->environment('production') && preg_match('/^(GT|G|AW)-[A-Z0-9]{4,20}$/', trim((string) Settings::get('google_tag_id'))) === 1)
<div class="lg-kolacici" id="lg-kolacici" role="region" aria-label="Kolačići" hidden>
	<p>Koristimo kolačiće za merenje posećenosti, da bismo poboljšali sajt. <a href="{{ url_slash('/politika-privatnosti') }}">Više o privatnosti</a></p>
	<div class="lg-kolacici__dugmad">
		<button type="button" class="lg-btn lg-btn--green" data-kolacici="da">Prihvatam</button>
		<button type="button" class="lg-btn lg-kolacici__ne" data-kolacici="ne">Odbijam</button>
	</div>
</div>
<script>
(function () {
	var bar = document.getElementById('lg-kolacici');
	var choice = null;
	try { choice = localStorage.getItem('lg-kolacici'); } catch (e) {}
	if (!bar || choice) return;
	bar.hidden = false;
	bar.addEventListener('click', function (e) {
		var value = e.target.getAttribute('data-kolacici');
		if (!value) return;
		try { localStorage.setItem('lg-kolacici', value); } catch (err) {}
		if (value === 'da' && window.gtag) { gtag('consent', 'update', {analytics_storage: 'granted'}); }
		bar.hidden = true;
	});
})();
</script>
@endif
