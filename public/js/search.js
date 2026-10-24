/* Listing list: the sort select submits the filter form, empty fields are not sent
   (shorter addresses, no redirect). Everything works without this file. */
(function () {
	'use strict';

	var form = document.getElementById('sr-form');
	if (!form) {
		return;
	}

	form.addEventListener('submit', function () {
		Array.prototype.forEach.call(form.elements, function (el) {
			if (el.name && !el.disabled && (el.tagName === 'INPUT' || el.tagName === 'SELECT')
				&& el.type !== 'checkbox' && el.type !== 'radio' && String(el.value).trim() === '') {
				el.disabled = true;
			}
		});
		document.querySelectorAll('[form="sr-form"]').forEach(function (el) {
			if (el.name && String(el.value).trim() === '') {
				el.disabled = true;
			}
		});
		// Enable again when the page is restored from the back/forward cache.
		window.addEventListener('pageshow', function () {
			Array.prototype.forEach.call(form.elements, function (el) {
				el.disabled = false;
			});
		}, { once: true });
	});

	document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
		el.addEventListener('change', function () {
			if (typeof form.requestSubmit === 'function') {
				form.requestSubmit();
			} else {
				form.submit();
			}
		});
	});
})();
