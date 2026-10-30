/* Profil firme: logo and cover are checked and, when too large, shrunk in the browser before sending, so a big
   phone photo or a 2x screenshot is saved instead of failing on the server; a wrong image says so at once. */
(function () {
	'use strict';

	var LIMITS = {
		logo: { minW: 100, minH: 100, maxBytes: 5 * 1024 * 1024, maxSide: 1200, label: 'Logo mora biti najmanje 100 × 100 piksela.' },
		cover: { minW: 800, minH: 250, maxBytes: 8 * 1024 * 1024, maxSide: 2400, label: 'Naslovna slika mora biti najmanje 800 × 250 piksela.' }
	};

	var showError = function (input, text) {
		var field = input.closest('.lgf-field');
		var box = field.querySelector('.lgf-error--live');
		if (!text) {
			if (box) { box.remove(); }
			input.removeAttribute('aria-invalid');
			return;
		}
		if (!box) {
			box = document.createElement('p');
			box.className = 'lgf-error lgf-error--live';
			box.setAttribute('role', 'alert');
			field.querySelector('.lgf-help').after(box);
		}
		box.textContent = text;
		input.setAttribute('aria-invalid', 'true');
	};

	var shrink = function (img, type, maxSide) {
		var scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
		var canvas = document.createElement('canvas');
		canvas.width = Math.round(img.naturalWidth * scale);
		canvas.height = Math.round(img.naturalHeight * scale);
		canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
		return new Promise(function (resolve) { canvas.toBlob(resolve, type, 0.88); });
	};

	Object.keys(LIMITS).forEach(function (name) {
		var input = document.querySelector('input[type=file][name=' + name + ']');
		if (!input || !window.URL || !window.DataTransfer) { return; }
		var limit = LIMITS[name];
		var busy = false;

		input.addEventListener('change', function () {
			if (busy) { return; }
			var file = input.files && input.files[0];
			showError(input, '');
			if (!file) { return; }
			if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
				showError(input, 'Izaberite JPG, PNG ili WebP sliku.');
				input.value = '';
				return;
			}
			var url = URL.createObjectURL(file);
			var img = new Image();
			img.onload = function () {
				URL.revokeObjectURL(url);
				if (img.naturalWidth < limit.minW || img.naturalHeight < limit.minH) {
					showError(input, limit.label + ' Ova ima ' + img.naturalWidth + ' × ' + img.naturalHeight + '.');
					input.value = '';
					return;
				}
				if (file.size <= limit.maxBytes && Math.max(img.naturalWidth, img.naturalHeight) <= limit.maxSide * 2) { return; }
				// Too large to send as it is: a smaller copy with the same look (PNG stays PNG for transparent logos).
				var type = file.type === 'image/png' && name === 'logo' ? 'image/png' : 'image/jpeg';
				shrink(img, type, limit.maxSide).then(function (blob) {
					if (!blob) { return; }
					var copy = new File([blob], file.name.replace(/\.\w+$/, '') + (type === 'image/png' ? '.png' : '.jpg'), { type: type });
					var list = new DataTransfer();
					list.items.add(copy);
					busy = true;
					input.files = list.files;
					busy = false;
				});
			};
			img.onerror = function () {
				URL.revokeObjectURL(url);
				showError(input, 'Slika ne može da se otvori. Probajte drugu sliku.');
				input.value = '';
			};
			img.src = url;
		});
	});

	// After a failed save the first marked field is brought into view, so the reason is never off screen.
	var first = document.querySelector('.lgf-form [aria-invalid="true"]');
	if (first) {
		first.scrollIntoView({ block: 'center' });
		first.focus({ preventScroll: true });
	}
})();
