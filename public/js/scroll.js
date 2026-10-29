/* Back to a list returns to the same place. Phone browsers (iOS Safari above all) often reload the page
   instead of keeping it in the back/forward cache and then lose the position; the last position of each
   address is kept for the tab and put back after such a reload. Everything works without this file. */
(function () {
	'use strict';

	var store;
	try {
		store = window.sessionStorage;
	} catch (e) {
		return;
	}
	var key = 'lg-scroll:' + location.pathname + location.search;

	window.addEventListener('pagehide', function () {
		try {
			store.setItem(key, String(Math.round(window.scrollY)));
		} catch (e) {}
	});

	var nav = window.performance && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
	if (!nav || nav.type !== 'back_forward') {
		return;
	}
	var y = 0;
	try {
		y = parseInt(store.getItem(key) || '0', 10);
	} catch (e) {}
	if (!(y > 0)) {
		return;
	}

	// The visitor scrolling first wins.
	var moved = false;
	var stop = function () { moved = true; };
	['touchstart', 'wheel', 'keydown'].forEach(function (type) {
		window.addEventListener(type, stop, { once: true, passive: true });
	});
	var restore = function () {
		if (!moved && window.scrollY < y - 40) {
			window.scrollTo(0, y);
		}
	};

	restore();
	if (document.readyState !== 'complete') {
		window.addEventListener('load', restore, { once: true });
	}
})();
