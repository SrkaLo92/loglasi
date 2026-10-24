/* loglasi.rs posting wizard: only enhancements (live card preview, drag & drop upload, drag reorder).
   Every step works without this file. */
(function () {
	'use strict';
	document.documentElement.classList.add('pst-has-js');
	var root = document.querySelector('.pst');
	if (root) { root.classList.add('pst-has-js'); }
	var token = (document.querySelector('input[name="_token"]') || {}).value || '';

	// Live preview of the card on the basics step
	var form = document.querySelector('[data-pst-preview-form]');
	if (form) {
		var el = function (name) { return form.querySelector('[data-pst-preview="' + name + '"]'); };
		var title = document.querySelector('[data-pst-card-title]');
		var price = document.querySelector('[data-pst-card-price]');
		var place = document.querySelector('[data-pst-card-location]');
		var row = document.querySelector('[data-pst-price-row]');
		var format = function (raw, currency) {
			var v = String(raw || '').replace(/[\s€]/g, '');
			if (v.indexOf(',') >= 0) { v = v.replace(/\./g, '').replace(',', '.'); }
			else if (/^\d{1,3}(\.\d{3})+$/.test(v)) { v = v.replace(/\./g, ''); }
			var n = parseFloat(v);
			if (!isFinite(n)) { return 'Cena'; }
			var whole = Math.floor(n) === n;
			var text = n.toLocaleString('de-DE', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 });
			return text + (currency === 'RSD' ? ' din' : ' €');
		};
		var update = function () {
			var t = el('title').value.trim();
			title.textContent = t || 'Naslov oglasa';
			var negotiable = form.querySelector('input[name="price_type"][value="negotiable"]').checked;
			price.textContent = negotiable ? 'Dogovor' : format(el('price').value, el('currency').value);
			if (row) { row.classList.toggle('is-off', negotiable); }
			var loc = el('location');
			place.textContent = loc.value ? loc.options[loc.selectedIndex].text : 'Mesto';
		};
		form.addEventListener('input', update);
		form.addEventListener('change', update);
		update();
	}

	// Upload: choose or drop files, send one by one, then reload the step
	var upload = document.querySelector('[data-pst-upload]');
	if (upload && window.fetch && window.FormData) {
		var input = upload.querySelector('input[type="file"]');
		var zone = upload.querySelector('[data-pst-drop]');
		var status = upload.querySelector('[data-pst-upload-status]');
		var send = function (files) {
			files = Array.prototype.slice.call(files);
			if (!files.length) { return; }
			var done = 0, errors = [];
			var next = function () {
				if (!files.length) {
					status.textContent = errors.join(' ');
					if (done) { window.location.reload(); }
					return;
				}
				var file = files.shift();
				status.textContent = 'Otpremanje: ' + file.name + '…';
				var data = new FormData();
				data.append('_token', token);
				data.append('images[]', file);
				fetch(upload.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
					.then(function (r) { return r.json().catch(function () { return { errors: ['Greška pri otpremanju. Pokušajte ponovo.'] }; }); })
					.then(function (json) {
						done += (json.images || []).length;
						errors = errors.concat(json.errors || []);
						next();
					})
					.catch(function () { errors.push(file.name + ': greška pri otpremanju.'); next(); });
			};
			next();
		};
		input.addEventListener('change', function () { send(input.files); });
		['dragenter', 'dragover'].forEach(function (type) {
			zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
		});
		['dragleave', 'drop'].forEach(function (type) {
			zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.remove('is-over'); });
		});
		zone.addEventListener('drop', function (e) { if (e.dataTransfer) { send(e.dataTransfer.files); } });
	}

	// Reorder by dragging; saved with one request
	var list = document.querySelector('[data-pst-sortable]');
	if (list && window.fetch) {
		var dragged = null;
		Array.prototype.forEach.call(list.children, function (item) {
			item.setAttribute('draggable', 'true');
			item.addEventListener('dragstart', function () { dragged = item; item.classList.add('is-dragging'); });
			item.addEventListener('dragend', function () {
				item.classList.remove('is-dragging');
				var order = Array.prototype.map.call(list.children, function (li) { return li.getAttribute('data-id'); });
				var data = new FormData();
				data.append('_token', token);
				order.forEach(function (id) { data.append('order[]', id); });
				fetch(list.getAttribute('data-pst-order-url'), { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
					.then(function () { window.location.reload(); });
			});
			item.addEventListener('dragover', function (e) {
				e.preventDefault();
				if (!dragged || dragged === item) { return; }
				var box = item.getBoundingClientRect();
				var after = (e.clientX - box.left) > box.width / 2;
				list.insertBefore(dragged, after ? item.nextSibling : item);
			});
		});
	}
})();
