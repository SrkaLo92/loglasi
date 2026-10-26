/* loglasi.rs: questions (<details name="...">) with the same name are one group where one answer is open
   at a time. Browsers do this themselves; this covers the ones that do not know the name attribute yet. */
(function () {
	'use strict';

	// When an answer above closes, the question just tapped would jump up the screen: keep it where it was.
	var anchor = null;
	document.addEventListener('click', function (event) {
		var summary = event.target.closest && event.target.closest('details[name] > summary');
		anchor = summary ? { el: summary, top: summary.getBoundingClientRect().top } : null;
	}, true);
	document.addEventListener('toggle', function (event) {
		var details = event.target;
		if (!anchor || !details.open || details.tagName !== 'DETAILS' || anchor.el.parentNode !== details) { return; }
		var a = anchor;
		anchor = null;
		window.requestAnimationFrame(function () {
			var shift = a.el.getBoundingClientRect().top - a.top;
			if (Math.abs(shift) > 1) { window.scrollBy(0, shift); }
		});
	}, true);

	if ('name' in document.createElement('details')) { return; }
	document.addEventListener('toggle', function (event) {
		var details = event.target;
		if (!details.open || details.tagName !== 'DETAILS' || !details.getAttribute('name')) { return; }
		Array.prototype.forEach.call(document.querySelectorAll('details[name="' + details.getAttribute('name') + '"][open]'), function (other) {
			if (other !== details) { other.open = false; }
		});
	}, true);
})();
