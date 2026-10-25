/* Heart buttons ("Sačuvaj u omiljene") on cards and on the listing page change at once, without reloading the
   page (kupujemprodajem "Pratim"). Without JavaScript the form posts and the page comes back. */
(function () {
	'use strict';

	document.addEventListener('submit', function (ev) {
		var form = ev.target;
		if (!form.matches || !form.matches('[data-lg-fav]') || !window.fetch) { return; }
		ev.preventDefault();
		var buttons = document.querySelectorAll('[data-lg-fav][action="' + form.getAttribute('action') + '"] button');
		var token = form.querySelector('input[name="_token"]');
		fetch(form.action, {
			method: 'POST',
			headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.value : '', 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin'
		}).then(function (r) {
			if (r.status === 401 || r.status === 419) { window.location.href = form.getAttribute('data-login') || '/login-registracija/'; return null; }
			return r.json();
		}).then(function (data) {
			if (!data) { return; }
			Array.prototype.forEach.call(buttons, function (b) {
				b.setAttribute('aria-pressed', data.favorite ? 'true' : 'false');
				var label = b.getAttribute('aria-label') || '';
				b.setAttribute('aria-label', label.replace(/^(Ukloni iz omiljenih|Sačuvaj u omiljene)/, data.favorite ? 'Ukloni iz omiljenih' : 'Sačuvaj u omiljene'));
				var text = b.querySelector('[data-fav-text]');
				if (text) { text.textContent = data.favorite ? 'Sačuvano' : 'Sačuvaj'; }
			});
		}).catch(function () { form.submit(); });
	});
})();
