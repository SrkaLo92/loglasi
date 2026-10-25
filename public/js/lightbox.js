/* loglasi.rs listing photos in a full screen gallery: swipe or arrows between photos, pinch or double tap
   to zoom on a phone, wheel or click to zoom on a computer. It closes with the X, Esc, the phone's back
   button, a tap or click anywhere outside the photo, or by dragging the photo up or down. Without
   JavaScript the photo link opens the large photo. No libraries. */
(function () {
	'use strict';

	var links = Array.prototype.slice.call(document.querySelectorAll('[data-lgo-full]'));
	if (!links.length) { return; }

	var ICON = {
		close: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		prev: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>',
		next: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>'
	};
	var MAX = 4;
	var photos = links.map(function (a) {
		var img = a.querySelector('img');
		return { src: a.getAttribute('data-lgo-full'), alt: img ? img.getAttribute('alt') || '' : '', thumb: a.getAttribute('data-lgo-thumb-src') || '' };
	});

	var box = null;
	var track = null;
	var counter = null;
	var thumbs = null;
	var index = 0;
	var opener = null;
	var zoom = { scale: 1, x: 0, y: 0 };

	function build() {
		box = document.createElement('div');
		box.className = 'lgb';
		box.hidden = true;
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.setAttribute('aria-label', 'Fotografije oglasa');
		box.innerHTML = '<div class="lgb__bar"><span class="lgb__count" aria-live="polite"></span>' +
			'<button class="lgb__btn lgb__close" type="button" aria-label="Zatvori galeriju">' + ICON.close + '</button></div>' +
			'<div class="lgb__track"></div>' +
			'<button class="lgb__btn lgb__nav lgb__nav--prev" type="button" aria-label="Prethodna fotografija">' + ICON.prev + '</button>' +
			'<button class="lgb__btn lgb__nav lgb__nav--next" type="button" aria-label="Sledeća fotografija">' + ICON.next + '</button>' +
			'<div class="lgb__thumbs"></div>';
		document.body.appendChild(box);
		track = box.querySelector('.lgb__track');
		counter = box.querySelector('.lgb__count');
		thumbs = box.querySelector('.lgb__thumbs');

		photos.forEach(function (photo, i) {
			var slide = document.createElement('div');
			slide.className = 'lgb__slide';
			var img = document.createElement('img');
			img.className = 'lgb__img';
			img.alt = photo.alt;
			img.decoding = 'async';
			img.draggable = false;
			img.setAttribute('data-src', photo.src);
			slide.appendChild(img);
			track.appendChild(slide);

			if (photos.length > 1) {
				var t = document.createElement('button');
				t.type = 'button';
				t.className = 'lgb__thumb';
				t.setAttribute('aria-label', 'Fotografija ' + (i + 1));
				var ti = document.createElement('img');
				ti.src = photo.thumb || photo.src;
				ti.alt = '';
				ti.loading = 'lazy';
				t.appendChild(ti);
				t.addEventListener('click', function () { go(i, true); });
				thumbs.appendChild(t);
			}
		});
		if (photos.length < 2) { box.classList.add('lgb--single'); }

		box.querySelector('.lgb__close').addEventListener('click', close);
		box.querySelector('.lgb__nav--prev').addEventListener('click', function () { go(index - 1, true); });
		box.querySelector('.lgb__nav--next').addEventListener('click', function () { go(index + 1, true); });
		track.addEventListener('scroll', function () {
			window.requestAnimationFrame(function () {
				var i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
				if (i !== index) { index = i; resetZoom(); update(); }
			});
		}, { passive: true });
		document.addEventListener('keydown', function (ev) {
			if (box.hidden) { return; }
			if (ev.key === 'Escape') { ev.preventDefault(); close(); }
			else if (ev.key === 'ArrowRight') { ev.preventDefault(); go(index + 1, true); }
			else if (ev.key === 'ArrowLeft') { ev.preventDefault(); go(index - 1, true); }
			else if (ev.key === 'Tab') {
				var f = Array.prototype.filter.call(box.querySelectorAll('button'), function (b) { return b.offsetParent !== null; });
				if (!f.length) { return; }
				if (ev.shiftKey && document.activeElement === f[0]) { ev.preventDefault(); f[f.length - 1].focus(); }
				else if (!ev.shiftKey && document.activeElement === f[f.length - 1]) { ev.preventDefault(); f[0].focus(); }
			}
		});
		bindZoom();
	}

	function load(i) {
		[i - 1, i, i + 1].forEach(function (n) {
			var slide = track.children[n];
			if (!slide) { return; }
			var img = slide.querySelector('img');
			if (img && !img.src) { img.src = img.getAttribute('data-src'); }
		});
	}

	function update() {
		load(index);
		counter.textContent = photos.length > 1 ? (index + 1) + ' / ' + photos.length : '';
		box.querySelector('.lgb__nav--prev').disabled = index === 0;
		box.querySelector('.lgb__nav--next').disabled = index === photos.length - 1;
		Array.prototype.forEach.call(thumbs.children, function (t, n) {
			if (n === index) {
				t.setAttribute('aria-current', 'true');
				t.scrollIntoView({ block: 'nearest', inline: 'center' });
			} else {
				t.removeAttribute('aria-current');
			}
		});
	}

	function go(i, smooth) {
		i = Math.max(0, Math.min(photos.length - 1, i));
		resetZoom();
		track.scrollTo({ left: i * track.clientWidth, behavior: smooth ? 'smooth' : 'auto' });
		if (!smooth) { index = i; update(); }
	}

	function open(i) {
		if (!box) { build(); }
		opener = document.activeElement;
		box.hidden = false;
		document.documentElement.classList.add('lgb-lock');
		index = i;
		load(i);
		window.requestAnimationFrame(function () {
			go(i, false);
			box.classList.add('is-open');
			box.querySelector('.lgb__close').focus();
		});
		if (window.history && history.pushState) { history.pushState({ lgb: true }, ''); }
	}

	function close(fromHistory) {
		if (!box || box.hidden) { return; }
		resetZoom();
		box.classList.remove('is-open');
		box.hidden = true;
		document.documentElement.classList.remove('lgb-lock');
		if (opener && opener.focus) { opener.focus(); }
		if (fromHistory !== true && history.state && history.state.lgb) { history.back(); }
	}
	// The phone's back button closes the gallery instead of leaving the page.
	window.addEventListener('popstate', function () { close(true); });

	/* Zoom: one image at a time, moved with transform; at scale 1 the track swipes normally. */
	function current() { return track.children[index] && track.children[index].querySelector('img'); }
	function apply() {
		var img = current();
		if (!img) { return; }
		img.style.transform = zoom.scale > 1 ? 'translate(' + zoom.x + 'px,' + zoom.y + 'px) scale(' + zoom.scale + ')' : '';
		box.classList.toggle('is-zoomed', zoom.scale > 1);
	}
	function clamp() {
		var img = current();
		if (!img) { return; }
		var maxX = Math.max(0, (img.clientWidth * zoom.scale - track.clientWidth) / 2);
		var maxY = Math.max(0, (img.clientHeight * zoom.scale - track.clientHeight) / 2);
		zoom.x = Math.max(-maxX, Math.min(maxX, zoom.x));
		zoom.y = Math.max(-maxY, Math.min(maxY, zoom.y));
	}
	function resetZoom() {
		var img = current();
		zoom = { scale: 1, x: 0, y: 0 };
		if (img) { img.style.transform = ''; }
		if (box) { box.classList.remove('is-zoomed'); }
	}
	function zoomAt(scale, cx, cy) {
		var rect = track.getBoundingClientRect();
		var ox = cx - rect.left - rect.width / 2;
		var oy = cy - rect.top - rect.height / 2;
		var next = Math.max(1, Math.min(MAX, scale));
		var k = next / zoom.scale;
		zoom.x = ox - (ox - zoom.x) * k;
		zoom.y = oy - (oy - zoom.y) * k;
		zoom.scale = next;
		if (next === 1) { zoom.x = 0; zoom.y = 0; }
		clamp();
		apply();
	}

	function bindZoom() {
		var points = {};
		var pinch = null;
		var pan = null;
		var lastTap = 0;
		var moved = false;
		var drag = null; // dragging the photo up or down to close (not zoomed, one finger)
		var CLOSE_AT = 110;

		function dragTo(dy) {
			track.style.transform = dy ? 'translateY(' + dy + 'px)' : '';
			box.style.backgroundColor = dy ? 'rgba(13, 18, 16, ' + Math.max(0.35, 1 - Math.abs(dy) / 400) + ')' : '';
		}

		track.addEventListener('pointerdown', function (ev) {
			if (!ev.target.classList.contains('lgb__img')) { return; }
			points[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
			moved = false;
			var ids = Object.keys(points);
			drag = ids.length === 1 && zoom.scale === 1 ? { x: ev.clientX, y: ev.clientY, axis: null, dy: 0 } : null;
			if (ids.length === 2) {
				var a = points[ids[0]], b = points[ids[1]];
				pinch = { dist: Math.hypot(a.x - b.x, a.y - b.y), scale: zoom.scale };
				pan = null;
				drag = null;
				dragTo(0);
			} else if (zoom.scale > 1) {
				pan = { x: ev.clientX - zoom.x, y: ev.clientY - zoom.y };
				track.setPointerCapture(ev.pointerId);
			}
		});
		track.addEventListener('pointermove', function (ev) {
			if (!points[ev.pointerId]) { return; }
			if (Math.abs(ev.clientX - points[ev.pointerId].x) + Math.abs(ev.clientY - points[ev.pointerId].y) > 6) { moved = true; }
			points[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
			if (drag) {
				var dx = ev.clientX - drag.x;
				drag.dy = ev.clientY - drag.y;
				if (!drag.axis && Math.abs(dx) + Math.abs(drag.dy) > 10) {
					drag.axis = Math.abs(drag.dy) > Math.abs(dx) ? 'y' : 'x';
					if (drag.axis === 'y') { track.setPointerCapture(ev.pointerId); }
				}
				if (drag.axis === 'y') { dragTo(drag.dy); return; }
			}
			var ids = Object.keys(points);
			if (pinch && ids.length === 2) {
				var a = points[ids[0]], b = points[ids[1]];
				zoomAt(pinch.scale * Math.hypot(a.x - b.x, a.y - b.y) / pinch.dist, (a.x + b.x) / 2, (a.y + b.y) / 2);
			} else if (pan) {
				zoom.x = ev.clientX - pan.x;
				zoom.y = ev.clientY - pan.y;
				clamp();
				apply();
			}
		});
		var end = function (ev) {
			var wasTap = points[ev.pointerId] && !moved && !pinch;
			if (drag && drag.axis === 'y') {
				var far = Math.abs(drag.dy) > CLOSE_AT && ev.type !== 'pointercancel';
				drag = null;
				delete points[ev.pointerId];
				dragTo(0);
				if (far) { close(); }
				return;
			}
			drag = null;
			delete points[ev.pointerId];
			if (Object.keys(points).length < 2) { pinch = null; }
			if (!Object.keys(points).length) { pan = null; }
			if (!wasTap || ev.type === 'pointercancel') { return; }
			var now = Date.now();
			var mouse = ev.pointerType === 'mouse';
			if (mouse || now - lastTap < 300) {
				zoomAt(zoom.scale > 1 ? 1 : 2.5, ev.clientX, ev.clientY);
				lastTap = 0;
			} else {
				lastTap = now;
			}
		};
		track.addEventListener('pointerup', end);
		track.addEventListener('pointercancel', end);
		track.addEventListener('wheel', function (ev) {
			if (!ev.target.classList.contains('lgb__img') || (!ev.ctrlKey && zoom.scale === 1 && Math.abs(ev.deltaX) > Math.abs(ev.deltaY))) { return; }
			ev.preventDefault();
			zoomAt(zoom.scale * (ev.deltaY < 0 ? 1.2 : 1 / 1.2), ev.clientX, ev.clientY);
		}, { passive: false });
		// Clicking the dark area around the photo (also the empty parts of the top bar and the thumbnail
		// strip) closes the gallery, as on most sites.
		track.addEventListener('click', function (ev) {
			if (ev.target.classList.contains('lgb__slide') && zoom.scale === 1) { close(); }
		});
		box.addEventListener('click', function (ev) {
			if (ev.target === box || ev.target.classList.contains('lgb__bar') || ev.target.classList.contains('lgb__thumbs') || ev.target.classList.contains('lgb__count')) { close(); }
		});
	}

	links.forEach(function (a, i) {
		a.addEventListener('click', function (ev) {
			if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button === 1) { return; }
			ev.preventDefault();
			open(i);
		});
	});
})();
