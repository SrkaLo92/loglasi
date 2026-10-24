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

/* Filter panel (KP, OLX, AutoScout24 style): every filter is a section that folds and shows what is
   chosen, a quick filter on the phone opens only its own section, and the button counts the
   listings before the search is sent ("Prikaži 123 oglasa"). */
(function () {
	'use strict';

	var form = document.getElementById('sr-form');
	var panel = document.getElementById('filteri');
	if (!form || !panel) {
		return;
	}
	var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };
	var CHEVRON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
	var sections = [];

	var textOf = function (node) { return (node ? node.textContent : '').replace(/\s+/g, ' ').trim(); };

	/* What a section has chosen, in words: "Audi", "Dizel, Benzin", "od 2010. do 2015.", "do 5.000 €". */
	var summary = function (group) {
		var parts = [];
		var range = group.querySelector('.sr-range');
		if (range) {
			var bits = [];
			each(range.querySelectorAll('label'), function (label) {
				var control = label.querySelector('input, select');
				if (!control || control.name === 'valuta' || String(control.value).trim() === '') { return; }
				var value = control.tagName === 'SELECT' ? control.options[control.selectedIndex].textContent : control.value.trim();
				if (/^\d{4,}$/.test(value.replace(/[.\s]/g, '')) && control.tagName !== 'SELECT') { value = Number(value.replace(/[.\s]/g, '')).toLocaleString('de-DE'); }
				bits.push(textOf(label.firstChild).toLowerCase() + ' ' + value);
			});
			if (bits.length) {
				var currency = range.querySelector('select[name="valuta"]');
				parts.push(bits.join(' ') + (currency ? ' ' + currency.options[currency.selectedIndex].textContent : ''));
			}
			return parts.join('');
		}
		each(group.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked'), function (input) {
			if (input.value === '') { return; }
			parts.push(textOf(input.closest('label')));
		});
		each(group.querySelectorAll('select'), function (select) {
			if (select.value !== '') { parts.push(select.options[select.selectedIndex].textContent.trim()); }
		});
		each(group.querySelectorAll('input[type="text"], input[type="search"]'), function (input) {
			if (input.value.trim() !== '') { parts.push('„' + input.value.trim() + '”'); }
		});
		return parts.join(', ');
	};

	var refresh = function () {
		sections.forEach(function (s) {
			var text = summary(s.group);
			s.value.textContent = text || 'Sve';
			s.head.classList.toggle('has-value', text !== '');
			each(document.querySelectorAll('[data-sr-quick="' + s.key + '"]'), function (chip) {
				var label = chip.querySelector('span');
				if (label) { label.textContent = text || chip.getAttribute('data-label'); }
				chip.classList.toggle('is-active', text !== '');
			});
		});
	};

	var open = function (s, show) {
		s.head.setAttribute('aria-expanded', show ? 'true' : 'false');
		s.body.hidden = !show;
		s.group.classList.toggle('is-open', show);
	};

	each(form.querySelectorAll('.sr-group[data-sr-key]'), function (group, i) {
		if (group.hasAttribute('data-sr-fixed')) { return; }
		var title = group.querySelector('.sr-group__title');
		if (!title) { return; }
		var key = group.getAttribute('data-sr-key');
		var head = document.createElement('button');
		head.type = 'button';
		head.className = 'sr-sec__head';
		head.id = 'sr-sec-' + key;
		head.innerHTML = '<span class="sr-sec__text"><span class="sr-sec__title"></span><span class="sr-sec__value"></span></span><span class="sr-sec__chev">' + CHEVRON + '</span>';
		head.querySelector('.sr-sec__title').textContent = textOf(title);
		var body = document.createElement('div');
		body.className = 'sr-sec__body';
		body.id = 'sr-sec-body-' + key;
		body.setAttribute('role', 'region');
		body.setAttribute('aria-labelledby', head.id);
		head.setAttribute('aria-controls', body.id);
		Array.prototype.slice.call(group.childNodes).forEach(function (child) {
			if (child !== title || title.tagName !== 'LEGEND') { body.appendChild(child); }
		});
		title.classList.add('sr-sec__label');
		group.classList.add('sr-sec');
		group.appendChild(head);
		group.appendChild(body);
		var s = { key: key, group: group, head: head, body: body, value: head.querySelector('.sr-sec__value') };
		sections.push(s);
		// Open: what is in use, and the first filters (price, place, make); the rest wait folded.
		open(s, summary(group) !== '' || i < 4);
		head.addEventListener('click', function () { open(s, body.hidden); });
	});
	refresh();

	/* A quick filter on the phone: the panel with only that section. */
	var title = panel.querySelector('.sr-filters__title');
	var titleText = title ? title.textContent : '';
	var single = function (key) {
		panel.classList.toggle('is-single', !!key);
		sections.forEach(function (s) {
			s.group.classList.toggle('is-only', s.key === key);
			if (s.key === key) { open(s, true); }
		});
		if (title) {
			var s = sections.filter(function (x) { return x.key === key; })[0];
			title.textContent = s ? textOf(s.head.querySelector('.sr-sec__title')) : titleText;
		}
	};
	each(document.querySelectorAll('[data-sr-quick]'), function (chip) {
		chip.addEventListener('click', function (ev) {
			var key = chip.getAttribute('data-sr-quick');
			var s = sections.filter(function (x) { return x.key === key; })[0];
			var selects = s ? s.group.querySelectorAll('select[data-pick]') : [];
			var trigger = s ? s.group.querySelector('.lp-trigger') : null;
			// One list (make, place): its search sheet right away, and the choice shows the results.
			if (s && selects.length === 1 && !s.group.querySelector('.sr-range') && trigger) {
				ev.preventDefault();
				selects[0].addEventListener('change', function () {
					if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
				}, { once: true });
				trigger.click();
				return;
			}
			single(key);
		});
	});
	form.classList.add('has-sections');
	window.addEventListener('hashchange', function () { if (location.hash !== '#filteri') { single(''); } });

	/* Live count on the button. */
	var button = form.querySelector('[data-sr-submit]');
	var timer = null;
	var asked = 0;
	var word = function (n) { return n % 10 === 1 && n % 100 !== 11 ? 'oglas' : 'oglasa'; };
	var count = function () {
		if (!button || !window.fetch) { return; }
		var params = new URLSearchParams();
		new FormData(form).forEach(function (value, name) {
			if (String(value).trim() !== '') { params.append(name, value); }
		});
		var mine = ++asked;
		button.classList.add('is-counting');
		fetch(form.action + (params.toString() ? '?' + params.toString() : ''), {
			headers: { 'X-Lg-Count': '1', 'Accept': 'application/json' },
			credentials: 'same-origin'
		}).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
			if (mine !== asked || !data || typeof data.count !== 'number') { return; }
			button.textContent = data.count ? 'Prikaži ' + data.count.toLocaleString('de-DE') + ' ' + word(data.count) : 'Nema oglasa za ovaj izbor';
			button.classList.toggle('is-empty', data.count === 0);
		}).catch(function () {}).then(function () {
			if (mine === asked) { button.classList.remove('is-counting'); }
		});
	};
	var changed = function () {
		refresh();
		clearTimeout(timer);
		timer = setTimeout(count, 350);
	};
	form.addEventListener('change', changed);
	form.addEventListener('input', function (ev) { if (ev.target.type === 'text' || ev.target.type === 'search') { changed(); } });

	/* "Obriši" in the panel head: clears the form (not the category), then counts again. */
	var clear = panel.querySelector('[data-sr-clear]');
	if (clear) {
		clear.addEventListener('click', function (ev) {
			ev.preventDefault();
			var only = panel.classList.contains('is-single') ? sections.filter(function (s) { return s.group.classList.contains('is-only'); })[0] : null;
			var scope = only ? only.group : form;
			each(scope.querySelectorAll('input, select'), function (el) {
				if (el.type === 'checkbox') { el.checked = false; }
				else if (el.type === 'radio') { el.checked = el.value === ''; }
				else if (el.tagName === 'SELECT') { if (el.name !== 'valuta') { el.value = ''; } }
				else if (el.type !== 'hidden') { el.value = ''; }
				if (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio') { el.dispatchEvent(new Event('change', { bubbles: true })); }
			});
			changed();
		});
	}
})();
