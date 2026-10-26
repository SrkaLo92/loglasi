/* loglasi.rs pickers: a searchable sheet instead of the native select (select[data-pick]) and for long
   checkbox lists ([data-pick-multi]). The native controls stay in the form and keep the value, so the
   page works without this file and the dependent fields (fields.js) keep working. */
(function () {
	'use strict';

	var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };
	var fold = function (text) {
		return String(text || '').toLowerCase().replace(/đ/g, 'dj').normalize('NFD').replace(/[̀-ͯ]/g, '');
	};
	var matches = function (query, text) {
		if (!query) { return true; }
		var hay = fold(text);
		return query.split(/\s+/).every(function (word) { return hay.indexOf(word) >= 0; });
	};
	var el = function (tag, cls, text) {
		var node = document.createElement(tag);
		if (cls) { node.className = cls; }
		if (text !== undefined) { node.textContent = text; }
		return node;
	};
	var ICON_CHECK = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
	var ICON_CLOSE = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
	var ICON_SEARCH = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>';
	var ICON_MORE = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

	/* One sheet for the whole page, filled for the control that opened it. */
	var sheet = null;
	var build = function () {
		var root = el('div', 'lp-sheet');
		root.hidden = true;
		root.innerHTML = '<div class="lp-scrim" data-lp-close></div>' +
			'<div class="lp-panel" role="dialog" aria-modal="true" aria-labelledby="lp-title">' +
			'<div class="lp-grab" aria-hidden="true"></div>' +
			'<div class="lp-head"><div class="lp-head__text"><p class="lp-title" id="lp-title"></p><p class="lp-sub"></p></div>' +
			'<button class="lp-x" type="button" aria-label="Zatvori" data-lp-close>' + ICON_CLOSE + '</button></div>' +
			'<div class="lp-find"><span class="lp-find__icon">' + ICON_SEARCH + '</span><input class="lp-find__input" type="search" autocomplete="off" enterkeyhint="search" aria-controls="lp-body"></div>' +
			'<div class="lp-body" id="lp-body"></div>' +
						'</div>';
		document.body.appendChild(root);
		sheet = {
			root: root,
			title: root.querySelector('.lp-title'),
			sub: root.querySelector('.lp-sub'),
			input: root.querySelector('.lp-find__input'),
			body: root.querySelector('.lp-body'),
			panel: root.querySelector('.lp-panel'),
			current: null
		};
		// A dropdown under its field follows the page, so it closes when the page moves.
		window.addEventListener('scroll', function () { if (root.classList.contains('is-pop') && Date.now() - sheet.openedAt > 400) { close(); } }, { passive: true });
		window.addEventListener('resize', function () { if (root.classList.contains('is-pop')) { close(); } });
		each(root.querySelectorAll('[data-lp-close]'), function (button) {
			button.addEventListener('click', function () { close(); });
		});
		sheet.input.addEventListener('input', function () { render(); });
		root.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { event.preventDefault(); close(); return; }
			if (event.key === 'Enter' && event.target === sheet.input) {
				event.preventDefault();
				var first = sheet.body.querySelector('.lp-opt');
				if (first) { first.click(); }
				return;
			}
			if (event.key === 'ArrowDown' && event.target === sheet.input) {
				event.preventDefault();
				var next = sheet.body.querySelector('.lp-opt');
				if (next) { next.focus(); }
				return;
			}
			if (event.key === 'Tab') {
				var focusable = Array.prototype.filter.call(root.querySelectorAll('button, input'), function (node) {
					return !node.hidden && node.offsetParent !== null && !node.closest('[hidden]');
				});
				if (!focusable.length) { return; }
				var firstNode = focusable[0];
				var lastNode = focusable[focusable.length - 1];
				if (event.shiftKey && document.activeElement === firstNode) { event.preventDefault(); lastNode.focus(); }
				else if (!event.shiftKey && document.activeElement === lastNode) { event.preventDefault(); firstNode.focus(); }
			}
		});
	};

	var render = function () {
		var query = fold(sheet.input.value.trim());
		sheet.body.textContent = '';
		sheet.current.render(query, sheet.body);
		if (!sheet.body.querySelector('.lp-opt')) {
			var empty = el('div', 'lp-empty');
			empty.appendChild(el('p', 'lp-empty__title', 'Nema rezultata za „' + sheet.input.value.trim() + '”'));
			empty.appendChild(el('p', 'lp-empty__text', 'Proverite naziv ili izaberite „Ostalo”.'));
			sheet.body.appendChild(empty);
		}
	};

	var open = function (control) {
		if (!sheet) { build(); }
		sheet.current = control;
		sheet.openedAt = Date.now();
		sheet.title.textContent = control.title;
		sheet.input.value = '';
		sheet.input.placeholder = control.placeholder;
		sheet.input.setAttribute('aria-label', 'Pretraga: ' + control.title);
		control.refreshSub();
		render();
		window.clearTimeout(sheet.hideTimer);
		sheet.root.classList.remove('is-closing', 'is-open', 'is-pop');
		sheet.panel.removeAttribute('style');
		// The dropdown class goes on before the sheet is shown, so the opening transition never starts
		// from the centred dialog (the panel used to fly in from the middle of the screen).
		var pop = window.matchMedia('(min-width: 720px)').matches && control.trigger;
		sheet.root.classList.toggle('is-pop', !!pop);
		sheet.root.hidden = false;
		// A short list on a phone: the sheet is only as tall as its options.
		sheet.root.classList.toggle('is-short', control.count() <= 8);
		if (pop) { place(control.trigger); }
		else { document.documentElement.classList.add('lp-lock'); }
		void sheet.panel.offsetHeight; // start the opening transition from the closed state
		window.requestAnimationFrame(function () {
			sheet.root.classList.add('is-open');
			// The keyboard on a phone would cover half the list, so focus the search there only for long lists.
			if (window.matchMedia('(min-width: 720px)').matches || control.count() > 12) { sheet.input.focus(); }
			else { sheet.root.querySelector('.lp-x').focus(); }
			var chosen = sheet.body.querySelector('.lp-list:not(.lp-list--chips) .lp-opt[aria-selected="true"]');
			if (chosen) { chosen.scrollIntoView({ block: 'center' }); }
		});
	};

	/* On a computer the list opens as a dropdown under its field (below, or above when there is no room). */
	var place = function (trigger) {
		var rect = trigger.getBoundingClientRect();
		var gap = 6;
		var edge = 16;
		var width = Math.min(Math.max(rect.width, 340), 440, window.innerWidth - edge * 2);
		var left = Math.max(edge, Math.min(rect.left, window.innerWidth - width - edge));
		var below = window.innerHeight - rect.bottom - gap - edge;
		var above = rect.top - gap - edge;
		var style = sheet.panel.style;
		sheet.root.classList.add('is-pop');
		style.left = left + 'px';
		style.width = width + 'px';
		if (below >= 320 || below >= above) {
			style.top = (rect.bottom + gap) + 'px';
			style.maxHeight = Math.min(520, below) + 'px';
		} else {
			style.bottom = (window.innerHeight - rect.top + gap) + 'px';
			style.maxHeight = Math.min(520, above) + 'px';
		}
	};

	var close = function () {
		if (!sheet || !sheet.current) { return; }
		var control = sheet.current;
		sheet.current = null;
		var root = sheet.root;
		var panel = sheet.panel;
		root.classList.add('is-closing');
		root.classList.remove('is-open');
		document.documentElement.classList.remove('lp-lock');
		// Hidden once the closing transition has run (shorter with reduced motion).
		var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		sheet.hideTimer = window.setTimeout(function () {
			root.classList.remove('is-closing', 'is-pop');
			panel.removeAttribute('style');
			root.hidden = true;
		}, reduce || root.classList.contains('is-pop') ? 0 : 230);
		if (control.trigger) { control.trigger.focus(); }
	};

	var group = function (body, title) {
		var section = el('div', 'lp-group');
		if (title) { section.appendChild(el('p', 'lp-group__title', title)); }
		var list = el('div', 'lp-list');
		list.setAttribute('role', 'listbox');
		section.appendChild(list);
		body.appendChild(section);
		return list;
	};

	/* Single choice, from a select. */
	var single = function (select) {
		var field = select.closest('.pst-field, .sr-group') || select.parentNode;
		var label = (select.id && field.querySelector('label[for="' + select.id + '"]')) || select.closest('label');
		var legend = field.querySelector('legend');
		var ownText = function (node) {
			return Array.prototype.filter.call(node.childNodes, function (child) { return child.nodeType === 3; })
				.map(function (child) { return child.textContent; }).join(' ').trim();
		};
		var title;
		if (label && label.contains(select)) {
			// "Od" / "Do" of a range: "Godište od".
			title = ((legend ? legend.textContent.trim() + ' ' : '') + ownText(label).toLowerCase()).trim();
		} else {
			title = (label ? label.textContent : (legend ? legend.textContent : select.name)).replace(/\s*\*\s*$/, '').trim();
		}
		var isYear = select.getAttribute('data-pick') === 'year';
		var popular = (select.getAttribute('data-pick-popular') || '').split(',').filter(Boolean);
		var placeholderOf = function () {
			var first = select.options[0];
			return first && first.value === '' ? first.textContent : 'Izaberite';
		};

		var trigger = el('button', 'lp-trigger');
		trigger.type = 'button';
		trigger.innerHTML = '<span class="lp-trigger__text"></span><span class="lp-trigger__icon">' + ICON_MORE + '</span>';
		var text = trigger.querySelector('.lp-trigger__text');
		if (select.getAttribute('aria-invalid') === 'true') { trigger.setAttribute('aria-invalid', 'true'); }
		if (select.getAttribute('aria-describedby')) { trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby')); }
		if (select.classList.contains('sr-input')) { trigger.classList.add('lp-trigger--sm'); }
		if (select.getAttribute('data-pick') === 'bar') {
			// Part of a bar (the header search): the button keeps the select's own classes and look.
			trigger.className += ' lp-trigger--bar ' + select.className;
		}
		select.classList.add('lp-native');
		select.setAttribute('tabindex', '-1');
		select.setAttribute('aria-hidden', 'true');
		select.parentNode.insertBefore(trigger, select.nextSibling);

		var sync = function () {
			var option = select.options[select.selectedIndex];
			var empty = !option || option.value === '';
			text.textContent = empty ? placeholderOf() : option.textContent;
			trigger.classList.toggle('is-empty', empty);
			trigger.disabled = select.disabled;
			trigger.setAttribute('aria-label', title + ': ' + text.textContent);
		};
		select.addEventListener('change', sync);
		new MutationObserver(sync).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });
		sync();

		var options = function () {
			return Array.prototype.filter.call(select.options, function (option) { return option.value !== ''; }).map(function (option) {
				var parent = option.parentNode && option.parentNode.tagName === 'OPTGROUP' ? option.parentNode.label : '';
				return { value: option.value, label: option.textContent, group: parent };
			});
		};
		var choose = function (value) {
			select.value = value;
			select.dispatchEvent(new Event('change', { bubbles: true }));
			close();
		};
		var row = function (list, option) {
			var button = el('button', 'lp-opt');
			button.type = 'button';
			button.setAttribute('role', 'option');
			var on = option.value === select.value;
			button.setAttribute('aria-selected', on ? 'true' : 'false');
			button.appendChild(el('span', 'lp-opt__label', option.label));
			if (on) { button.insertAdjacentHTML('beforeend', '<span class="lp-opt__tick">' + ICON_CHECK + '</span>'); }
			button.addEventListener('click', function () { choose(option.value); });
			list.appendChild(button);
		};

		var control = {
			trigger: trigger,
			title: title,
			placeholder: isYear ? 'Upišite godinu, npr. 2012' : 'Pretražite: ' + title.toLowerCase(),
			count: function () { return options().length; },
			refreshSub: function () {
				var n = options().length;
				sheet.sub.textContent = isYear ? '' : n + (n % 10 === 1 && n % 100 !== 11 ? ' mogućnost' : ' mogućnosti');
			},
			render: function (query, body) {
				var all = options().filter(function (o) { return matches(query, o.label + ' ' + o.group); });
				if (select.value !== '' && !query) {
					var clear = el('button', 'lp-opt lp-opt--clear');
					clear.type = 'button';
					clear.appendChild(el('span', 'lp-opt__label', placeholderOf()));
					clear.addEventListener('click', function () { choose(''); });
					group(body, '').appendChild(clear);
				}
				if (isYear) {
					var decades = {};
					var order = [];
					all.forEach(function (o) {
						var decade = o.value.slice(0, 3) + '0';
						if (!decades[decade]) { decades[decade] = []; order.push(decade); }
						decades[decade].push(o);
					});
					order.forEach(function (decade) {
						var list = group(body, decade + '–' + (Number(decade) + 9));
						list.classList.add('lp-list--grid');
						decades[decade].forEach(function (o) { row(list, o); });
					});
					return;
				}
				if (popular.length && !query) {
					var shown = popular.map(function (value) {
						return all.filter(function (o) { return o.value === value; })[0];
					}).filter(Boolean);
					if (shown.length) {
						var chips = group(body, 'Najtraženije');
						chips.classList.add('lp-list--chips');
						shown.forEach(function (o) { row(chips, o); });
					}
				}
				var hasGroups = all.some(function (o) { return o.group; });
				if (hasGroups) {
					var byGroup = {};
					var groups = [];
					all.forEach(function (o) {
						if (!byGroup[o.group]) { byGroup[o.group] = []; groups.push(o.group); }
						byGroup[o.group].push(o);
					});
					groups.forEach(function (name) {
						var list = group(body, name);
						byGroup[name].forEach(function (o) { row(list, o); });
					});
					return;
				}
				if (all.length > 20 && !query) {
					var letters = {};
					var seen = [];
					all.forEach(function (o) {
						var letter = /^ostal/i.test(o.label) ? 'Ostalo' : fold(o.label).charAt(0).toUpperCase();
						if (!letters[letter]) { letters[letter] = []; seen.push(letter); }
						letters[letter].push(o);
					});
					seen.forEach(function (letter) {
						var list = group(body, letter);
						letters[letter].forEach(function (o) { row(list, o); });
					});
					return;
				}
				var flat = group(body, query && all.length ? 'Rezultati (' + all.length + ')' : '');
				all.forEach(function (o) { row(flat, o); });
			}
		};

		trigger.addEventListener('click', function () { open(control); });
		if (label) {
			label.addEventListener('click', function (event) {
				if (trigger.contains(event.target)) { return; }
				event.preventDefault();
				if (!trigger.disabled) { open(control); }
			});
		}
	};

	/* Several choices: a long checkbox list folds into a header that shows what is chosen,
	   and opens in place with a search over its options. */
	var accordions = 0;
	var multi = function (box) {
		var inputs = Array.prototype.slice.call(box.querySelectorAll('input[type="checkbox"]'));
		if (!inputs.length) { return; }
		var title = box.getAttribute('data-pick-multi') || 'Izaberite';
		var hint = box.getAttribute('data-pick-hint') || 'Izaberite sve što važi';
		var holder = box.closest('fieldset') || box.parentNode;
		var labelOf = function (input) {
			var label = input.closest('label');
			return (label ? label.textContent : input.value).trim();
		};
		var panelId = 'lp-acc-' + (++accordions);

		holder.classList.add('lp-acc-on');
		var wrap = el('div', 'lp-acc');
		var head = el('button', 'lp-acc__head');
		head.type = 'button';
		head.setAttribute('aria-expanded', 'false');
		head.setAttribute('aria-controls', panelId);
		head.innerHTML = '<span class="lp-acc__main"><span class="lp-acc__top"><span class="lp-acc__title"></span><span class="lp-acc__count"></span></span><span class="lp-summary__chips"></span></span><span class="lp-acc__chev">' + ICON_MORE + '</span>';
		head.querySelector('.lp-acc__title').textContent = title;
		var count = head.querySelector('.lp-acc__count');
		var chips = head.querySelector('.lp-summary__chips');

		var panel = el('div', 'lp-acc__panel');
		panel.id = panelId;
		panel.hidden = true;
		var tools = el('div', 'lp-acc__tools');
		var search = null;
		if (inputs.length > 12) {
			var find = el('div', 'lp-acc__find');
			find.innerHTML = '<span class="lp-find__icon">' + ICON_SEARCH + '</span>';
			search = el('input', 'lp-find__input');
			search.type = 'search';
			search.autocomplete = 'off';
			search.placeholder = 'Pretražite: ' + title.toLowerCase();
			search.setAttribute('aria-label', 'Pretraga: ' + title);
			find.appendChild(search);
			tools.appendChild(find);
		}
		var clear = el('button', 'lp-acc__clear', 'Poništi izbor');
		clear.type = 'button';
		tools.appendChild(clear);
		var none = el('p', 'lp-acc__none');
		none.hidden = true;

		box.parentNode.insertBefore(wrap, box);
		wrap.appendChild(head);
		wrap.appendChild(panel);
		panel.appendChild(tools);
		panel.appendChild(box);
		panel.appendChild(none);
		// "Prikaži još" from the page without JavaScript is not needed inside the open group.
		each(box.querySelectorAll('details'), function (details) { details.open = true; details.classList.add('lp-flat'); });

		var sync = function () {
			var chosen = inputs.filter(function (input) { return input.checked; }).map(labelOf);
			count.textContent = chosen.length ? 'izabrano ' + chosen.length : '';
			chips.textContent = '';
			if (head.getAttribute('aria-expanded') !== 'true') {
				if (chosen.length) {
					chosen.slice(0, 5).forEach(function (name) { chips.appendChild(el('span', 'lp-chip', name)); });
					if (chosen.length > 5) { chips.appendChild(el('span', 'lp-chip lp-chip--more', '+' + (chosen.length - 5))); }
				} else {
					chips.appendChild(el('span', 'lp-acc__hint', hint + ' (' + inputs.length + ')'));
				}
			}
			clear.hidden = !chosen.length;
			wrap.classList.toggle('has-value', chosen.length > 0);
		};
		var filter = function () {
			var query = fold(search ? search.value.trim() : '');
			var shown = 0;
			inputs.forEach(function (input) {
				var label = input.closest('label') || input;
				var hit = matches(query, labelOf(input));
				label.hidden = !hit;
				if (hit) { shown++; }
			});
			none.hidden = shown > 0;
			none.textContent = shown ? '' : 'Nema rezultata za „' + search.value.trim() + '”.';
		};
		var toggle = function (show) {
			head.setAttribute('aria-expanded', show ? 'true' : 'false');
			panel.hidden = !show;
			wrap.classList.toggle('is-open', show);
			sync();
		};

		head.addEventListener('click', function () { toggle(panel.hidden); });
		clear.addEventListener('click', function () {
			inputs.forEach(function (input) {
				if (input.checked) {
					input.checked = false;
					input.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
			sync();
		});
		if (search) {
			search.addEventListener('input', filter);
			search.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); } });
		}
		each(inputs, function (input) { input.addEventListener('change', sync); });
		// A group with an error stays open so the message is seen.
		toggle(!!holder.querySelector('.pst-error, [aria-invalid="true"]'));
	};

	/* Radio pills: a second tap clears the choice (a select could be set back to "Izaberite"). */
	var pills = function (input) {
		var wasChecked = false;
		input.addEventListener('pointerdown', function () { wasChecked = input.checked; });
		input.addEventListener('click', function () {
			if (wasChecked) {
				input.checked = false;
				var empty = input.form && input.form.querySelector('input[type="radio"][name="' + input.name + '"][value=""]');
				if (empty) { empty.checked = true; }
				input.dispatchEvent(new Event('change', { bubbles: true }));
			}
			wasChecked = false;
		});
	};

	/* "180000" becomes "180.000" when leaving the field (the server reads both). */
	var thousands = function (input) {
		input.addEventListener('blur', function () {
			var digits = input.value.replace(/\D/g, '');
			if (digits !== '' && /^[\d\s.]*$/.test(input.value)) { input.value = Number(digits).toLocaleString('de-DE'); }
		});
	};

	/* kW with the horse power under it, as polovniautomobili shows it. */
	var horsepower = function (input) {
		var note = el('span', 'pst-note');
		note.setAttribute('aria-live', 'polite');
		(input.closest('.pst-field') || input.parentNode).appendChild(note);
		var sync = function () {
			var kw = Number(input.value.replace(',', '.').replace(/[^\d.]/g, ''));
			note.textContent = kw > 0 && kw < 2000 ? '= ' + Math.round(kw * 1.35962) + ' KS' : '';
		};
		input.addEventListener('input', sync);
		sync();
	};

	each(document.querySelectorAll('select[data-pick]'), single);
	each(document.querySelectorAll('[data-pick-multi]'), multi);
	each(document.querySelectorAll('.pst-pill input[type="radio"]'), pills);
	each(document.querySelectorAll('input[data-thousands]'), thousands);
	each(document.querySelectorAll('input[data-kw]'), horsepower);
})();
