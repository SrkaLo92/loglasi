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

	// Category search: filters every last-level category as you type (the form also works as GET ?trazi=)
	var picker = document.querySelector('[data-pst-picker]');
	if (picker) {
		var find = picker.querySelector('[data-pst-find]');
		var results = picker.querySelector('[data-pst-results]');
		var list = picker.querySelector('[data-pst-results-list]');
		var note = picker.querySelector('[data-pst-results-note]');
		var tree = picker.querySelector('[data-pst-tree]');
		var leaves = [];
		try { leaves = JSON.parse(picker.querySelector('[data-pst-leaves]').textContent || '[]'); } catch (e) { leaves = []; }
		var fold = function (text) {
			return String(text).replace(/đ/g, 'dj').replace(/Đ/g, 'Dj').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
		};
		leaves.forEach(function (leaf) { leaf.n = fold(leaf.name); leaf.p = fold(leaf.path); });
		var render = function () {
			var words = fold(find.value).split(/\s+/).filter(Boolean);
			if (!words.length) { results.hidden = true; tree.hidden = false; return; }
			var hits = leaves.filter(function (leaf) { return words.every(function (w) { return leaf.p.indexOf(w) >= 0; }); });
			hits.sort(function (a, b) {
				var an = words.some(function (w) { return a.n.indexOf(w) >= 0; }) ? 0 : 1;
				var bn = words.some(function (w) { return b.n.indexOf(w) >= 0; }) ? 0 : 1;
				return an - bn;
			});
			hits = hits.slice(0, 30);
			list.textContent = '';
			hits.forEach(function (leaf) {
				var li = document.createElement('li');
				var button = document.createElement('button');
				button.className = 'pst-cat pst-cat--result';
				button.type = 'submit';
				button.name = 'category_id';
				button.value = leaf.id;
				var text = document.createElement('span');
				text.className = 'pst-cat__text';
				var name = document.createElement('span');
				name.className = 'pst-cat__name';
				name.textContent = leaf.name;
				var path = document.createElement('span');
				path.className = 'pst-cat__path';
				path.textContent = leaf.path;
				text.appendChild(name);
				text.appendChild(path);
				var more = document.createElement('span');
				more.className = 'pst-cat__more';
				more.setAttribute('aria-hidden', 'true');
				more.textContent = '›';
				button.appendChild(text);
				button.appendChild(more);
				li.appendChild(button);
				list.appendChild(li);
			});
			note.textContent = hits.length ? 'Kategorije za „' + find.value.trim() + '”:' : 'Nema kategorije za „' + find.value.trim() + '”. Probajte drugu reč ili izaberite sa spiska ispod.';
			results.hidden = false;
			tree.hidden = hits.length > 0;
		};
		if (find && results && list && tree) {
			find.addEventListener('input', render);
			var submit = picker.querySelector('[data-pst-find-submit]');
			if (submit) { submit.hidden = true; }
			find.form.addEventListener('submit', function (e) { e.preventDefault(); render(); });
		}
	}
})();
