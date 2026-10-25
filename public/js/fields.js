/* loglasi.rs dependent fields (model depends on make), for the posting form and the search filters.
   Short lists are all in the page (without this file the child lists every option, grouped by parent);
   long ones (data-options-url, car models) are loaded for the chosen parents and kept for the visit. */
(function () {
	'use strict';

	var parentValues = function (form, key) {
		var select = form.querySelector('select[data-field-key="' + key + '"]');
		if (select) { return select.value ? [select.value] : []; }
		return Array.prototype.filter.call(form.querySelectorAll('input[data-field-key="' + key + '"]'), function (input) {
			return input.checked;
		}).map(function (input) { return input.value; });
	};

	Array.prototype.forEach.call(document.querySelectorAll('select[data-depends-on]'), function (child) {
		var form = child.form;
		var key = child.getAttribute('data-depends-on');
		if (!form || !key) { return; }

		var placeholder = child.getAttribute('data-placeholder') || '';
		var waiting = child.getAttribute('data-waiting') || '';
		var needsParent = child.hasAttribute('data-needs-parent');
		var options = Array.prototype.map.call(child.querySelectorAll('option[data-parent]'), function (option) {
			var group = option.parentNode && option.parentNode.tagName === 'OPTGROUP' ? option.parentNode.label : '';
			return { value: option.value, label: option.textContent, parent: option.getAttribute('data-parent'), group: group };
		});

		var url = child.getAttribute('data-options-url');
		var loaded = {};
		options.forEach(function (o) { loaded[o.parent] = true; });
		var load = function (parents) {
			var missing = parents.filter(function (p) { return !loaded[p]; });
			if (!url || !missing.length) { return false; }
			missing.forEach(function (p) { loaded[p] = true; });
			child.setAttribute('aria-busy', 'true');
			fetch(url + '?parent=' + encodeURIComponent(missing.join(',')), { headers: { Accept: 'application/json' } })
				.then(function (r) { return r.ok ? r.json() : []; })
				.then(function (list) {
					(Array.isArray(list) ? list : []).forEach(function (o) {
						options.push({ value: o.value, label: o.label, parent: o.parent, group: '' });
					});
				})
				.catch(function () { missing.forEach(function (p) { delete loaded[p]; }); })
				.then(function () { child.removeAttribute('aria-busy'); sync(); });
			return true;
		};

		var add = function (target, value, label) {
			var option = document.createElement('option');
			option.value = value;
			option.textContent = label;
			target.appendChild(option);
			return option;
		};

		var sync = function () {
			var parents = parentValues(form, key);
			if (load(parents)) { return; }
			var current = child.value;
			var list = parents.length ? options.filter(function (o) { return parents.indexOf(o.parent) >= 0; }) : (needsParent ? [] : options);

			child.textContent = '';
			add(child, '', parents.length || !needsParent ? placeholder : (waiting || placeholder));
			var grouped = parents.length !== 1;
			var groups = {};
			list.forEach(function (o) {
				var target = child;
				if (grouped && o.group) {
					if (!groups[o.group]) {
						groups[o.group] = document.createElement('optgroup');
						groups[o.group].label = o.group;
						child.appendChild(groups[o.group]);
					}
					target = groups[o.group];
				}
				add(target, o.value, o.label).setAttribute('data-parent', o.parent);
			});

			child.disabled = needsParent && !parents.length;
			child.value = list.some(function (o) { return o.value === current; }) ? current : '';
		};

		Array.prototype.forEach.call(form.querySelectorAll('[data-field-key="' + key + '"]'), function (input) {
			input.addEventListener('change', sync);
		});
		sync();
	});
})();
