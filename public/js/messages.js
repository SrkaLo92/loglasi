/* loglasi.rs: new messages in an open conversation, every N seconds while the tab is visible. */
(function () {
	'use strict';
	var script = document.currentScript;
	var thread = document.getElementById('lgm-thread');
	if (!script || !thread || !window.fetch) { return; }

	var url = script.getAttribute('data-poll-url');
	var seconds = parseInt(script.getAttribute('data-poll-seconds'), 10) || 20;
	var lastId = parseInt(script.getAttribute('data-last-id'), 10) || 0;
	var seen = document.getElementById('lgm-seen');
	var timer = null;
	var busy = false;

	function lastMineSent() {
		var mine = thread.querySelectorAll('.lgm-msg--mine');
		return mine.length ? mine[mine.length - 1].getAttribute('data-sent') : null;
	}

	function poll() {
		if (busy || document.visibilityState !== 'visible') { return; }
		busy = true;
		fetch(url + '?after=' + lastId, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data) { return; }
				if (data.html) {
					thread.insertAdjacentHTML('beforeend', data.html);
					lastId = data.last_id;
				}
				var sent = lastMineSent();
				if (seen && sent && data.read_until) {
					seen.hidden = new Date(data.read_until) <= new Date(sent);
				}
			})
			.catch(function () {})
			.then(function () { busy = false; });
	}

	function start() {
		stop();
		timer = window.setInterval(poll, seconds * 1000);
	}

	function stop() {
		if (timer) { window.clearInterval(timer); timer = null; }
	}

	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'visible') { poll(); start(); } else { stop(); }
	});

	if (document.visibilityState === 'visible') { start(); }
	var last = thread.lastElementChild;
	if (last && !window.location.hash) { last.scrollIntoView({ block: 'end' }); }
})();
