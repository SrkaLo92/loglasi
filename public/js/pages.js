/**
 * loglasi.rs, Cenovnik: the "Kopiraj" button for the account number.
 * Without JavaScript the button is hidden and the number is copied by hand.
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

	document.querySelectorAll('[data-lgx-copy]').forEach(function (button) {
		button.hidden = false;
	});

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-lgx-copy]') : null;
		if (!button) {
			return;
		}
		var box = button.closest('.lgx-payment__card');
		var status = box ? box.querySelector('.lgx-copy__status') : null;
		var label = button.querySelector('.lgx-copy__text');

		copyText(button.getAttribute('data-lgx-copy')).then(
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
