/* loglasi.rs: tabs „Prijava / Novi nalog” on /login-registracija/. Without JS the tabs are plain links. */
(function () {
	'use strict';

	var root = document.querySelector('[data-lgn-tabs]');
	if (!root) return;

	var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));

	function select(name, focus) {
		tabs.forEach(function (tab) {
			var on = tab.getAttribute('data-lgn-tab') === name;
			var panel = document.getElementById(tab.getAttribute('aria-controls'));
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.tabIndex = on ? 0 : -1;
			if (panel) panel.hidden = !on;
			if (on && focus) tab.focus();
			if (on && window.history && history.replaceState) {
				history.replaceState(null, '', tab.getAttribute('href'));
			}
		});
	}

	root.addEventListener('click', function (e) {
		var link = e.target.closest('[data-lgn-tab]');
		if (!link || e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;
		e.preventDefault();
		select(link.getAttribute('data-lgn-tab'), true);
	});

	root.querySelector('[role="tablist"]').addEventListener('keydown', function (e) {
		var i = tabs.indexOf(document.activeElement);
		if (i < 0) return;
		var next = {ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1}[e.key];
		if (next === undefined) return;
		e.preventDefault();
		next = (next + tabs.length) % tabs.length;
		select(tabs[next].getAttribute('data-lgn-tab'), true);
	});
})();
