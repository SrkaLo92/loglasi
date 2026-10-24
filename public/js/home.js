/* Home page tabs (WAI-ARIA tabs pattern). Without JavaScript every panel stays visible. */
(function () {
	'use strict';

	document.querySelectorAll('[data-tabs]').forEach(function (root) {
		var list = root.querySelector('[role="tablist"]');
		if (!list) {
			return;
		}
		var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
		var panels = tabs.map(function (tab) {
			return document.getElementById(tab.getAttribute('aria-controls'));
		});

		function select(index, focus) {
			tabs.forEach(function (tab, i) {
				var on = i === index;
				tab.setAttribute('aria-selected', on ? 'true' : 'false');
				tab.tabIndex = on ? 0 : -1;
				if (panels[i]) {
					panels[i].hidden = !on;
				}
			});
			if (focus) {
				tabs[index].focus();
			}
		}

		tabs.forEach(function (tab, i) {
			tab.addEventListener('click', function () {
				select(i, false);
			});
			tab.addEventListener('keydown', function (event) {
				var next = null;
				if (event.key === 'ArrowRight') {
					next = (i + 1) % tabs.length;
				} else if (event.key === 'ArrowLeft') {
					next = (i - 1 + tabs.length) % tabs.length;
				} else if (event.key === 'Home') {
					next = 0;
				} else if (event.key === 'End') {
					next = tabs.length - 1;
				}
				if (next !== null) {
					event.preventDefault();
					select(next, true);
				}
			});
		});

		list.hidden = false;
		root.classList.add('is-ready');
		select(0, false);
	});
})();
