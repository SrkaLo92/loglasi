/**
 * loglasi.rs, Cenovnik: dugme „Kopiraj” za broj računa.
 * Bez JavaScript-a dugme je skriveno, a broj se kopira ručno.
 */
(function () {
	'use strict';

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var area = document.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', '');
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild(area);
			area.select();
			try {
				document.execCommand('copy') ? resolve() : reject();
			} catch (e) {
				reject(e);
			}
			document.body.removeChild(area);
		});
	}

	document.querySelectorAll('[data-lgx-kopiraj]').forEach(function (button) {
		button.hidden = false;
	});

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-lgx-kopiraj]') : null;
		if (!button) {
			return;
		}
		var box = button.closest('.lgx-uplata__kartica');
		var status = box ? box.querySelector('.lgx-kopiraj__status') : null;
		var label = button.querySelector('.lgx-kopiraj__tekst');

		copyText(button.getAttribute('data-lgx-kopiraj')).then(
			function () {
				if (label) {
					label.textContent = 'Kopirano';
					window.setTimeout(function () {
						label.textContent = 'Kopiraj';
					}, 2000);
				}
				if (status) {
					status.textContent = 'Broj računa je kopiran.';
				}
			},
			function () {
				if (status) {
					status.textContent = 'Kopiranje nije uspelo. Označite broj i kopirajte ga ručno.';
				}
			}
		);
	});
}());
