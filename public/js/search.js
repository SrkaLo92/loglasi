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

/* Filter panel. Computer: a sidebar where every filter is a section that folds and shows what is chosen
   (mobile.de, AutoScout24). Phone: the kupujemprodajem way, a list of rows (name, what is chosen, arrow);
   a row opens its own screen with a search for long lists, a choice from a list applies at once, and the
   button counts the listings before the search is sent ("Prikaži 123 oglasa"). */
(function () {
	'use strict';

	var form = document.getElementById('sr-form');
	var panel = document.getElementById('filteri');
	if (!form || !panel) {
		return;
	}
	var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };
	var CHEVRON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
	var CHECK = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>';
	var BACK = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>';
	var phone = window.matchMedia('(max-width: 767.98px)');
	var sections = [];

	var textOf = function (node) { return (node ? node.textContent : '').replace(/\s+/g, ' ').trim(); };
	var fold = function (str) {
		return String(str).toLowerCase().replace(/[čć]/g, 'c').replace(/š/g, 's').replace(/ž/g, 'z').replace(/đ/g, 'dj').normalize('NFD').replace(/[̀-ͯ]/g, '');
	};
	var submit = function () { if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); } };

	/* What a section has chosen, in words: "Audi", "Dizel, Benzin", "od 2010. do 2015.", "do 5.000 €". */
	var summary = function (group) {
		var parts = [];
		if (group.hasAttribute('data-sr-links')) {
			var on = group.querySelector('a[aria-current]');
			return on && !on.hasAttribute('data-all') ? textOf(on.firstChild) : '';
		}
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
		each(group.querySelectorAll('input[type="text"], input[type="search"]:not(.sr-plist__find)'), function (input) {
			if (input.value.trim() !== '') { parts.push('„' + input.value.trim() + '”'); }
		});
		return parts.join(', ');
	};

	var active = 0;
	var refresh = function () {
		active = 0;
		sections.forEach(function (s) {
			var text = summary(s.group);
			if (text !== '' && s.key !== 'grupa') { active++; }
			s.value.textContent = text || (s.key === 'grupa' ? 'Sve grupe' : 'Sve');
			s.head.classList.toggle('has-value', text !== '');
			if (s.list) { s.list.update(); }
			each(document.querySelectorAll('[data-sr-quick="' + s.key + '"]'), function (chip) {
				var label = chip.querySelector('span');
				if (label) { label.textContent = text || chip.getAttribute('data-label'); }
				chip.classList.toggle('is-active', text !== '');
			});
		});
		if (title && !panel.classList.contains('is-sub')) { title.textContent = titleText + (active ? ' (' + active + ')' : ''); }
	};

	var open = function (s, show) {
		s.head.setAttribute('aria-expanded', show ? 'true' : 'false');
		s.body.hidden = !show;
		s.group.classList.toggle('is-open', show);
	};

	/* A long list as rows with a search on top (phone): one choice applies at once, several are ticked. */
	var makeList = function (s) {
		var group = s.group;
		var selects = group.querySelectorAll('select');
		var boxes = group.querySelectorAll('input[type="checkbox"]');
		var links = group.querySelectorAll('.sr-links a');
		var select = selects.length === 1 && !group.querySelector('.sr-range') ? selects[0] : null;
		if (!select && boxes.length < 2 && !links.length) { return null; }
		var count = select ? select.options.length : (links.length || boxes.length);
		var wrap = document.createElement('div');
		wrap.className = 'sr-plist';
		if (count > 8) {
			var find = document.createElement('input');
			find.type = 'search';
			find.className = 'sr-input sr-plist__find';
			find.placeholder = 'Pretražite…';
			find.setAttribute('aria-label', 'Pretražite: ' + textOf(s.head.querySelector('.sr-sec__title')));
			find.autocomplete = 'off';
			wrap.appendChild(find);
		}
		var ul = document.createElement('ul');
		ul.className = 'sr-plist__rows';
		wrap.appendChild(ul);
		var rows = [];
		var add = function (label, isOn, onPick, isAll) {
			var li = document.createElement('li');
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'sr-plist__row' + (isAll ? ' is-all' : '');
			b.innerHTML = '<span class="sr-plist__label"></span><span class="sr-plist__check">' + CHECK + '</span>';
			b.querySelector('.sr-plist__label').textContent = label;
			b.addEventListener('click', onPick);
			li.appendChild(b);
			ul.appendChild(li);
			rows.push({ li: li, b: b, f: fold(label), on: isOn, all: !!isAll });
		};
		if (select) {
			each(select.options, function (o) {
				add(o.value === '' ? 'Sve' : o.textContent.trim(), function () { return select.value === o.value; }, function () {
					select.value = o.value;
					select.dispatchEvent(new Event('change', { bubbles: true }));
					if (fromChip) { submit(); } else { back(); }
				}, o.value === '');
			});
		} else if (links.length) {
			each(links, function (a) {
				add(textOf(a.firstChild), function () { return a.hasAttribute('aria-current'); }, function () { window.location.href = a.href; }, a.hasAttribute('data-all'));
			});
		} else {
			each(boxes, function (box) {
				add(textOf(box.closest('label')), function () { return box.checked; }, function () {
					box.checked = !box.checked;
					box.dispatchEvent(new Event('change', { bubbles: true }));
				});
			});
		}
		if (find) {
			find.addEventListener('input', function () {
				// Words that start with what was typed: "au" finds Audi, not Renault.
				var q = fold(find.value.trim()).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
				var re = new RegExp('(^|[\\s(/|+-])' + q);
				rows.forEach(function (r) { r.li.hidden = q !== '' && (r.all || !re.test(r.f)); });
			});
		}
		s.body.insertBefore(wrap, s.body.firstChild);
		group.classList.add('has-plist');
		return {
			find: find || null,
			update: function () {
				rows.forEach(function (r) { var on = r.on(); r.b.classList.toggle('is-on', on); r.b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
			}
		};
	};

	each(form.querySelectorAll('.sr-group[data-sr-key]'), function (group, i) {
		if (group.hasAttribute('data-sr-fixed')) { return; }
		var label = group.querySelector('.sr-group__title');
		if (!label) { return; }
		var key = group.getAttribute('data-sr-key');
		var head = document.createElement('button');
		head.type = 'button';
		head.className = 'sr-sec__head';
		head.id = 'sr-sec-' + key;
		head.innerHTML = '<span class="sr-sec__text"><span class="sr-sec__title"></span><span class="sr-sec__value"></span></span><span class="sr-sec__chev">' + CHEVRON + '</span>';
		head.querySelector('.sr-sec__title').textContent = textOf(label);
		var body = document.createElement('div');
		body.className = 'sr-sec__body';
		body.id = 'sr-sec-body-' + key;
		body.setAttribute('role', 'region');
		body.setAttribute('aria-labelledby', head.id);
		head.setAttribute('aria-controls', body.id);
		Array.prototype.slice.call(group.childNodes).forEach(function (child) {
			if (child !== label || label.tagName !== 'LEGEND') { body.appendChild(child); }
		});
		label.classList.add('sr-sec__label');
		group.classList.add('sr-sec');
		group.appendChild(head);
		group.appendChild(body);
		var s = { key: key, group: group, head: head, body: body, value: head.querySelector('.sr-sec__value') };
		s.list = makeList(s);
		sections.push(s);
		// Computer: open what is in use and the first filters (price, place, make); the rest wait folded.
		s.desktopOpen = summary(group) !== '' || i < 4;
		head.addEventListener('click', function () {
			if (phone.matches) { sub(s, false); } else { open(s, body.hidden); }
		});
	});

	/* Phone: rows, and one screen per row. */
	var title = panel.querySelector('.sr-filters__title');
	var titleText = title ? title.textContent : 'Filteri';
	var headBar = panel.querySelector('.sr-filters__head');
	var backBtn = document.createElement('button');
	backBtn.type = 'button';
	backBtn.className = 'sr-filters__back';
	backBtn.setAttribute('aria-label', 'Nazad na sve filtere');
	backBtn.innerHTML = BACK;
	if (headBar) { headBar.insertBefore(backBtn, headBar.firstChild); }
	var button = form.querySelector('[data-sr-submit]');
	var apply = document.createElement('button');
	apply.type = 'button';
	apply.className = 'lg-btn lg-btn--green sr-apply';
	apply.textContent = 'Primeni';
	if (button) { button.parentNode.insertBefore(apply, button); }
	var current = null;
	var fromChip = false;

	var layout = function () {
		var mobile = phone.matches;
		panel.classList.toggle('is-rows', mobile);
		sections.forEach(function (s) {
			if (mobile) {
				open(s, s === current);
			} else {
				open(s, s.desktopOpen || s.body.hidden === false);
			}
		});
	};
	var sub = function (s, chip) {
		current = s;
		fromChip = !!chip;
		panel.classList.add('is-sub');
		sections.forEach(function (x) { x.group.classList.toggle('is-only', x === s); open(x, x === s); });
		if (title) { title.textContent = textOf(s.head.querySelector('.sr-sec__title')); }
		panel.scrollTop = 0;
		var panelBox = panel.querySelector('.sr-filters__panel');
		if (panelBox) { panelBox.scrollTop = 0; }
		if (s.list && s.list.find) { s.list.find.value = ''; s.list.find.dispatchEvent(new Event('input')); }
		backBtn.focus();
	};
	var back = function () {
		if (fromChip) { location.hash = ''; }
		current = null;
		fromChip = false;
		panel.classList.remove('is-sub');
		sections.forEach(function (x) { x.group.classList.remove('is-only'); open(x, false); });
		refresh();
	};
	backBtn.addEventListener('click', back);
	apply.addEventListener('click', function () { if (fromChip) { submit(); } else { back(); } });

	each(document.querySelectorAll('[data-sr-quick]'), function (chip) {
		chip.addEventListener('click', function () {
			var key = chip.getAttribute('data-sr-quick');
			var s = sections.filter(function (x) { return x.key === key; })[0];
			if (s && phone.matches) {
				// The panel opens through #filteri; then straight to this filter's own screen.
				window.setTimeout(function () { sub(s, true); }, 0);
			} else if (!key && current) {
				back();
			}
		});
	});
	form.classList.add('has-sections');
	window.addEventListener('hashchange', function () {
		if (location.hash !== '#filteri' && current) { current = null; fromChip = false; panel.classList.remove('is-sub'); sections.forEach(function (x) { x.group.classList.remove('is-only'); }); refresh(); }
	});
	if (phone.addEventListener) { phone.addEventListener('change', layout); }
	layout();
	refresh();

	/* Live count on the button. */
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
			credentials: 'same-origin',
			// Kept out of the HTTP cache here, not with a no-store reply, which would keep the list out of the back/forward cache.
			cache: 'no-store'
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
	form.addEventListener('input', function (ev) { if (ev.target.type === 'text' || (ev.target.type === 'search' && !ev.target.classList.contains('sr-plist__find'))) { changed(); } });

	/* "Obriši" in the panel head: clears the form (or only the open filter), not the category, then counts again. */
	var clear = panel.querySelector('[data-sr-clear]');
	if (clear) {
		clear.addEventListener('click', function (ev) {
			ev.preventDefault();
			var scope = current ? current.group : form;
			each(scope.querySelectorAll('input, select'), function (el) {
				if (el.classList.contains('sr-plist__find')) { return; }
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

/* Category intro: two lines on phones with "Prikaži više" (the full text stays in the page). */
(function () {
	'use strict';

	var box = document.querySelector('[data-sr-intro]');
	if (!box || !window.matchMedia('(max-width: 767.98px)').matches) {
		return;
	}
	var text = box.querySelector('.sr-intro__text');
	var more = box.querySelector('.sr-intro__more');

	box.classList.add('is-clamped');
	if (text.scrollHeight <= text.clientHeight + 2) {
		box.classList.remove('is-clamped');
		return;
	}
	more.hidden = false;
	more.addEventListener('click', function () {
		var open = box.classList.toggle('is-clamped') === false;
		more.setAttribute('aria-expanded', open ? 'true' : 'false');
		more.textContent = open ? 'Prikaži manje' : 'Prikaži više';
	});
})();
