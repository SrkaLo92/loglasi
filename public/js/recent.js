/* "Ranije pretrage" under the search box (kupujemprodajem, OLX): the last searches on this device, kept in the
   browser only. A results page with a word or a category remembers itself (search/list.blade.php sets
   <meta name="lg-recent">); a tap on the box shows the list, a tap on a row opens that search again. */
(function () {
	'use strict';

	var KEY = 'lg-recent';
	var MAX = 8;
	var read = function () {
		try { var list = JSON.parse(window.localStorage.getItem(KEY) || '[]'); return Array.isArray(list) ? list : []; } catch (e) { return []; }
	};
	var write = function (list) {
		try { window.localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX))); } catch (e) {}
	};

	// Remember this results page (first page, without sorting), newest first, no duplicates.
	var meta = document.querySelector('meta[name="lg-recent"]');
	if (meta && meta.content) {
		var url = new URL(window.location.href);
		url.searchParams.delete('stranica');
		url.searchParams.delete('page');
		url.searchParams.delete('sortiranje');
		var href = url.pathname + url.search;
		write([{ l: meta.content, u: href }].concat(read().filter(function (r) { return r.u !== href; })));
	}

	var input = document.getElementById('lg-q');
	var searchForm = input && input.closest('form');
	if (!input || !searchForm) { return; }

	// A narrow header (signed in, with Poruke and the bell) would cut the hint mid-word ("Šta tražite? r"):
	// then only the question stays.
	var fullHint = input.placeholder;
	var fitHint = function () {
		var ctx = document.createElement('canvas').getContext('2d');
		var style = window.getComputedStyle(input);
		ctx.font = style.fontSize + ' ' + style.fontFamily;
		var room = input.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
		input.placeholder = ctx.measureText(fullHint).width <= room ? fullHint : fullHint.split('?')[0] + '?';
	};
	fitHint();
	window.addEventListener('resize', fitHint);

	var box = document.createElement('div');
	box.className = 'lg-recent';
	box.hidden = true;
	box.id = 'lg-recent';
	box.innerHTML = '<div class="lg-recent__head"><span class="lg-recent__title">Ranije pretrage</span><button type="button" class="lg-recent__clear">Izbriši sve</button></div><ul class="lg-recent__list"></ul>';
	document.body.appendChild(box);
	var ul = box.querySelector('ul');
	input.setAttribute('aria-controls', 'lg-recent');
	input.setAttribute('aria-expanded', 'false');

	var place = function () {
		var r = searchForm.getBoundingClientRect();
		box.style.left = (r.left + window.scrollX) + 'px';
		box.style.top = (r.bottom + window.scrollY + 6) + 'px';
		box.style.width = r.width + 'px';
	};
	var hide = function () { box.hidden = true; input.setAttribute('aria-expanded', 'false'); };
	var show = function () {
		var list = read();
		if (!list.length || input.value.trim() !== '') { hide(); return; }
		ul.innerHTML = '';
		list.forEach(function (r, i) {
			var li = document.createElement('li');
			var a = document.createElement('a');
			a.href = r.u;
			a.className = 'lg-recent__row';
			a.textContent = r.l;
			var x = document.createElement('button');
			x.type = 'button';
			x.className = 'lg-recent__x';
			x.setAttribute('aria-label', 'Ukloni pretragu ' + r.l);
			x.textContent = '×';
			x.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
			x.addEventListener('click', function () {
				var all = read();
				all.splice(i, 1);
				write(all);
				show();
				input.focus();
			});
			li.appendChild(a);
			li.appendChild(x);
			ul.appendChild(li);
		});
		place();
		box.hidden = false;
		input.setAttribute('aria-expanded', 'true');
	};

	box.querySelector('.lg-recent__clear').addEventListener('mousedown', function (ev) { ev.preventDefault(); });
	box.querySelector('.lg-recent__clear').addEventListener('click', function () { write([]); hide(); input.focus(); });
	input.addEventListener('focus', show);
	input.addEventListener('input', show);
	input.addEventListener('blur', function () { window.setTimeout(function () { if (!box.contains(document.activeElement)) { hide(); } }, 150); });
	input.addEventListener('keydown', function (ev) {
		if (ev.key === 'Escape') { hide(); }
		if (ev.key === 'ArrowDown' && !box.hidden) { ev.preventDefault(); var first = box.querySelector('a'); if (first) { first.focus(); } }
	});
	box.addEventListener('keydown', function (ev) {
		var links = Array.prototype.slice.call(box.querySelectorAll('a'));
		var i = links.indexOf(document.activeElement);
		if (ev.key === 'ArrowDown' && i > -1 && i < links.length - 1) { ev.preventDefault(); links[i + 1].focus(); }
		if (ev.key === 'ArrowUp' && i > -1) { ev.preventDefault(); if (i === 0) { input.focus(); } else { links[i - 1].focus(); } }
		if (ev.key === 'Escape') { hide(); input.focus(); }
	});
	box.addEventListener('focusout', function () { window.setTimeout(function () { if (!box.contains(document.activeElement) && document.activeElement !== input) { hide(); } }, 0); });
	window.addEventListener('resize', function () { if (!box.hidden) { place(); } });
})();
