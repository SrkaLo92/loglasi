/* "Sve kategorije": a sheet with a search over every category (kupujemprodajem, Leboncoin). Any [data-lg-cats]
   link opens it; without JavaScript the link goes to the list of all listings. */
(function () {
	'use strict';

	var box = document.getElementById('lg-cats');
	if (!box) { return; }
	var input = box.querySelector('[data-lgc-find]');
	var results = box.querySelector('[data-lgc-results]');
	var none = box.querySelector('[data-lgc-none]');
	var browse = box.querySelector('[data-lgc-browse]');
	var keyword = box.querySelector('[data-lgc-keyword]');
	var index = [];
	var indexState = ''; // '' | 'loading' | 'ready'
	var opener = null;

	// Search without š/č/ć/ž/đ worries: "basta" finds "Bašta".
	var fold = function (s) {
		return String(s).toLowerCase().replace(/[čć]/g, 'c').replace(/š/g, 's').replace(/ž/g, 'z').replace(/đ/g, 'dj').normalize('NFD').replace(/[̀-ͯ]/g, '');
	};

	// The index (every category with its count) is fetched the first time someone types or focuses the search.
	var load = function () {
		if (indexState) { return; }
		indexState = 'loading';
		fetch(box.getAttribute('data-lgc-index'), { headers: { Accept: 'application/json' } })
			.then(function (r) { return r.ok ? r.json() : []; })
			.then(function (list) {
				index = Array.isArray(list) ? list : [];
				index.forEach(function (c) { c.f = fold((c.p ? c.p + ' ' : '') + c.n); });
				indexState = 'ready';
				render();
			})
			.catch(function () { indexState = ''; });
	};

	var render = function () {
		var q = fold(input.value.trim());
		if (q && indexState !== 'ready') { load(); browse.hidden = true; return; }
		browse.hidden = q !== '';
		results.innerHTML = '';
		if (!q) { results.hidden = true; none.hidden = true; return; }
		var words = q.split(/\s+/);
		var found = index.filter(function (c) {
			return words.every(function (w) { return c.f.indexOf(w) !== -1; });
		}).sort(function (a, b) { return b.c - a.c; }).slice(0, 40);
		found.forEach(function (c) {
			var li = document.createElement('li');
			var a = document.createElement('a');
			a.href = c.u;
			var name = document.createElement('span');
			name.className = 'lgc-results__name';
			name.textContent = c.n;
			a.appendChild(name);
			if (c.p) {
				var path = document.createElement('span');
				path.className = 'lgc-results__path';
				path.textContent = c.p;
				a.appendChild(path);
			}
			var n = document.createElement('span');
			n.className = 'lgc-results__n';
			n.textContent = c.c ? c.c.toLocaleString('de-DE') : '';
			a.appendChild(n);
			li.appendChild(a);
			results.appendChild(li);
		});
		results.hidden = !found.length;
		none.hidden = !!found.length;
		if (keyword) { keyword.href = keyword.href.split('?')[0] + '?keyword=' + encodeURIComponent(input.value.trim()); }
	};

	var open = function () {
		opener = document.activeElement;
		box.hidden = false;
		document.documentElement.classList.add('lgc-lock');
		window.requestAnimationFrame(function () { box.classList.add('is-open'); });
		if (window.matchMedia('(min-width: 768px)').matches) { input.focus(); } else { box.querySelector('[data-lgc-close]').focus(); }
		if (history.pushState) { history.pushState({ lgc: true }, ''); }
	};
	var close = function (fromHistory) {
		if (box.hidden) { return; }
		box.classList.remove('is-open');
		box.hidden = true;
		document.documentElement.classList.remove('lgc-lock');
		if (opener && opener.focus) { opener.focus(); }
		if (fromHistory !== true && history.state && history.state.lgc) { history.back(); }
	};

	document.addEventListener('click', function (ev) {
		var link = ev.target.closest && ev.target.closest('[data-lg-cats]');
		if (link) { ev.preventDefault(); open(); return; }
		if (ev.target === box || (ev.target.closest && ev.target.closest('[data-lgc-close]'))) { close(); }
	});
	document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !box.hidden) { close(); } });
	window.addEventListener('popstate', function () { close(true); });
	input.addEventListener('focus', load);
	input.addEventListener('input', render);
	input.addEventListener('keydown', function (ev) {
		if (ev.key === 'Enter') {
			var first = results.querySelector('a');
			if (first) { ev.preventDefault(); window.location.href = first.href; }
		}
	});
})();
