/* loglasi.rs profile: live opening hours status, sticky contact bar, collapsible firm details.
   Category tabs and pagination are plain links; views are counted on the server. */
(function () {
	'use strict';

	var DAYS_ACC = { 1: 'ponedeljak', 2: 'utorak', 3: 'sredu', 4: 'četvrtak', 5: 'petak', 6: 'subotu', 7: 'nedelju' };

	function hhmm(m) {
		if (m === 1440) {
			return '24:00';
		}
		var h = Math.floor(m / 60);
		var mm = m % 60;
		return (h < 10 ? '0' : '') + h + ':' + (mm < 10 ? '0' : '') + mm;
	}

	/* Day of the week (1 = Monday) and minutes since midnight in Belgrade. */
	function belgradeNow() {
		try {
			var parts = new Intl.DateTimeFormat('en-GB', {
				timeZone: 'Europe/Belgrade',
				weekday: 'short',
				hour: '2-digit',
				minute: '2-digit',
				hourCycle: 'h23'
			}).formatToParts(new Date());
			var map = {};
			parts.forEach(function (p) { map[p.type] = p.value; });
			var days = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };
			return { day: days[map.weekday], min: parseInt(map.hour, 10) * 60 + parseInt(map.minute, 10) };
		} catch (e) {
			return null;
		}
	}

	/* Same algorithm as App\Support\Listing\WorkingHours::status(). */
	function openStatus(schedule, now) {
		var today = schedule[now.day];
		var i;
		if (today === null || today === undefined) {
			return null;
		}
		for (i = 0; i < today.length; i++) {
			if (now.min >= today[i][0] && now.min < today[i][1]) {
				return { open: true, text: 'Otvoreno do ' + hhmm(today[i][1]) };
			}
		}
		for (i = 0; i < today.length; i++) {
			if (today[i][0] > now.min) {
				return { open: false, text: 'Zatvoreno, otvara se u ' + hhmm(today[i][0]) };
			}
		}
		for (i = 1; i <= 7; i++) {
			var next = ((now.day - 1 + i) % 7) + 1;
			var ranges = schedule[next];
			if (ranges === null || ranges === undefined) {
				return null;
			}
			if (ranges.length) {
				var t = hhmm(ranges[0][0]);
				return { open: false, text: i === 1 ? 'Zatvoreno, otvara se sutra u ' + t : 'Zatvoreno, otvara se u ' + DAYS_ACC[next] + ' u ' + t };
			}
		}
		return { open: false, text: 'Zatvoreno' };
	}

	function refreshHours(root) {
		var now = belgradeNow();
		if (!now) {
			return;
		}
		root.querySelectorAll('[data-lgp-hours]').forEach(function (box) {
			var schedule;
			try {
				schedule = JSON.parse(box.getAttribute('data-lgp-hours'));
			} catch (e) {
				return;
			}
			var status = openStatus(schedule, now);
			var el = box.querySelector('[data-lgp-status]');
			if (el) {
				if (status) {
					el.textContent = status.text;
					el.classList.toggle('is-open', status.open);
					el.classList.toggle('is-closed', !status.open);
					el.hidden = false;
				} else {
					el.hidden = true;
				}
			}
			if (box.classList.contains('lgp-facts__status')) {
				box.hidden = !status;
			}
			box.querySelectorAll('tr[data-lgp-day]').forEach(function (tr) {
				tr.classList.toggle('is-today', parseInt(tr.getAttribute('data-lgp-day'), 10) === now.day);
			});
		});
	}

	/* The sticky bar shows when the header buttons are off screen. */
	function initSticky(root) {
		var bar = root.querySelector('.lgp-sticky');
		var actions = root.querySelector('.lgp-firma__actions');
		if (!bar || !actions || !('IntersectionObserver' in window)) {
			return;
		}
		bar.classList.add('is-js');
		new IntersectionObserver(function (entries) {
			var gone = !entries[0].isIntersecting && entries[0].boundingClientRect.top < 0;
			bar.classList.toggle('is-visible', gone);
		}).observe(actions);
	}

	/* Below 768 px "O firmi i kontakt" is collapsed (after the listings); always open on desktop. */
	function initCollapse(root) {
		var blocks = root.querySelectorAll('details[data-lgp-collapse]');
		if (!blocks.length || !window.matchMedia) {
			return;
		}
		var mq = window.matchMedia('(max-width: 767px)');
		var apply = function () {
			blocks.forEach(function (d) {
				d.open = !mq.matches;
			});
		};
		apply();
		if (mq.addEventListener) {
			mq.addEventListener('change', apply);
		} else if (mq.addListener) {
			mq.addListener(apply);
		}
	}

	function init() {
		initCollapse(document);
		refreshHours(document);
		initSticky(document);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
