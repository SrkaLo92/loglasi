{{-- Back (or reload) returns to the same place on the page. Phone browsers, iOS Safari above all, often load the
     page again instead of keeping it in the back/forward cache and then lose the position, so the site keeps the last
     position of each address for the tab and puts it back itself. Inline in <head> so it runs before the first paint. --}}
<script nonce="{{ Vite::cspNonce() }}">
(function () {
	'use strict';
	var store;
	try { store = window.sessionStorage; store.getItem('lg'); } catch (e) { return; }
	var key = 'lg-scroll:' + location.pathname + location.search;
	var read = function () { try { return parseInt(store.getItem(key) || '0', 10) || 0; } catch (e) { return 0; } };
	var save = function () { try { store.setItem(key, String(Math.round(window.scrollY))); } catch (e) {} };
	if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }
	window.addEventListener('pagehide', save);
	document.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('a[href]')) { save(); } }, true);

	var y = 0;
	var moved = false;
	var restore = function () {
		if (!moved && y > 0 && Math.abs(window.scrollY - y) > 40) { window.scrollTo(0, y); }
	};
	// Kept in the back/forward cache: the place is normally kept too, put it back only if it was lost.
	window.addEventListener('pageshow', function (e) {
		if (e.persisted) { y = read(); moved = false; restore(); }
	});

	var nav = window.performance && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
	if (!nav || (nav.type !== 'back_forward' && nav.type !== 'reload')) { return; }
	y = read();
	if (!(y > 0)) { return; }
	// The visitor scrolling first wins.
	['touchstart', 'wheel', 'keydown'].forEach(function (type) {
		window.addEventListener(type, function () { moved = true; }, { once: true, passive: true });
	});
	// No page transition on the way back, the page is shown at once in its place.
	window.addEventListener('pagereveal', function (e) { if (e.viewTransition) { e.viewTransition.skipTransition(); } restore(); }, { once: true });
	document.addEventListener('DOMContentLoaded', restore, { once: true });
	window.addEventListener('load', function () { restore(); setTimeout(restore, 150); setTimeout(restore, 500); }, { once: true });
})();
</script>
