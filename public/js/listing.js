/* loglasi.rs listing page: phone reveal, sticky bar, gallery, share, loan calculator. No libraries. */
(function () {
	'use strict';

	var phone = null;

	function icon(el) {
		var svg = el.querySelector('svg');
		return svg ? svg.outerHTML : '';
	}

	/* Turns a reveal button into a real link once the number is known. */
	function toLink(btn, href, external) {
		var a = document.createElement('a');
		a.className = btn.className;
		a.href = href;
		a.rel = external ? 'nofollow noopener' : 'nofollow';
		if (external) {
			a.target = '_blank';
		}
		a.innerHTML = btn.innerHTML;
		if (btn.getAttribute('aria-label')) {
			a.setAttribute('aria-label', btn.getAttribute('aria-label'));
		}
		btn.parentNode.replaceChild(a, btn);
		return a;
	}

	function hrefFor(act) {
		if (act === 'tel') {
			return phone.tel;
		}
		if (act === 'whatsapp') {
			return phone.whatsapp;
		}
		if (act === 'viber') {
			return phone.viber;
		}
		return null;
	}

	function applyPhone() {
		document.querySelectorAll('[data-lgo-reveal-form]').forEach(function (form) {
			form.querySelectorAll('[data-lgo-num]').forEach(function (num) {
				num.textContent = phone.display;
			});
			form.querySelectorAll('[data-lgo-label]').forEach(function (label) {
				label.textContent = 'Pozovi';
			});
			form.querySelectorAll('[data-lgo-hint]').forEach(function (hint) {
				hint.textContent = 'Dodirnite ponovo za poziv';
			});
			form.querySelectorAll('button[data-lgo-act]').forEach(function (btn) {
				var act = btn.getAttribute('data-lgo-act');
				var link = toLink(btn, hrefFor(act), act === 'whatsapp');
				if (act === 'tel' && link.classList.contains('lgo-call')) {
					link.setAttribute('aria-label', 'Pozovi ' + phone.display);
				}
			});
			form.removeAttribute('data-lgo-reveal-form');
		});
	}

	function showError(text) {
		var el = document.querySelector('[data-lgo-error]');
		if (el) {
			el.textContent = text;
			el.hidden = false;
		}
	}

	function reveal(form, act) {
		var token = form.querySelector('input[name="_token"]');
		return window.fetch(form.action, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': token ? token.value : ''
			}
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					throw new Error(data && data.message ? data.message : 'Broj trenutno nije dostupan. Pokušajte ponovo.');
				}
				return data;
			});
		}).then(function (data) {
			if (!data.phone) {
				throw new Error('Prodavac nije ostavio broj telefona.');
			}
			phone = data;
			applyPhone();
			return act;
		});
	}

	function initReveal() {
		if (!window.fetch) {
			return;
		}
		document.addEventListener('click', function (ev) {
			var btn = ev.target.closest('[data-lgo-reveal-form] button[data-lgo-act]');
			if (!btn) {
				return;
			}
			ev.preventDefault();
			var form = btn.closest('form');
			var act = btn.getAttribute('data-lgo-act');
			var callNow = btn.hasAttribute('data-lgo-call-now');
			btn.disabled = true;
			reveal(form, act).then(function () {
				// The big button only shows the number; the second tap calls.
				if (act === 'tel' && !callNow) {
					return;
				}
				var href = hrefFor(act);
				if (act === 'whatsapp') {
					var win = window.open(href, '_blank');
					if (win) {
						win.opener = null;
					} else {
						window.location.href = href;
					}
				} else {
					window.location.href = href;
				}
			}).catch(function (err) {
				btn.disabled = false;
				showError(err.message);
			});
		});
	}

	/* The sticky bar hides while the card buttons are on screen. */
	function initBar() {
		var bar = document.querySelector('[data-lgo-bar]');
		var targets = document.querySelectorAll('.lgo-contact [data-lgo-actions], .lgo-contact .lgo-actions');
		if (!bar || !targets.length || !('IntersectionObserver' in window)) {
			return;
		}
		var visible = new Set();
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting && e.target.offsetParent !== null) {
					visible.add(e.target);
				} else {
					visible.delete(e.target);
				}
			});
			bar.classList.toggle('lgo-bar--skriveno', visible.size > 0);
		}, { threshold: 0.4 });
		targets.forEach(function (el) {
			io.observe(el);
		});
	}

	/* Gallery: arrows, counter, thumbnails without jumping the page. */
	function initGallery() {
		document.querySelectorAll('[data-lgo-gallery]').forEach(function (root) {
			var track = root.querySelector('[data-lgo-track]');
			if (!track) {
				return;
			}
			var slides = Array.prototype.slice.call(track.querySelectorAll('[data-lgo-slide]'));
			var thumbs = Array.prototype.slice.call(root.querySelectorAll('[data-lgo-thumb]'));
			var current = root.querySelector('[data-lgo-current]');
			var prev = root.querySelector('[data-lgo-prev]');
			var next = root.querySelector('[data-lgo-next]');
			var index = 0;

			function go(i) {
				i = Math.max(0, Math.min(slides.length - 1, i));
				track.scrollTo({ left: slides[i].offsetLeft - track.offsetLeft, behavior: 'smooth' });
			}

			function update() {
				var i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
				if (i === index) {
					return;
				}
				index = i;
				if (current) {
					current.textContent = String(i + 1);
				}
				thumbs.forEach(function (t, n) {
					if (n === i) {
						t.setAttribute('aria-current', 'true');
					} else {
						t.removeAttribute('aria-current');
					}
				});
				if (prev) {
					prev.disabled = i === 0;
				}
				if (next) {
					next.disabled = i === slides.length - 1;
				}
			}

			if (prev && next && slides.length > 1) {
				prev.hidden = false;
				next.hidden = false;
				prev.disabled = true;
				prev.addEventListener('click', function () { go(index - 1); });
				next.addEventListener('click', function () { go(index + 1); });
			}
			thumbs.forEach(function (t) {
				t.addEventListener('click', function (ev) {
					ev.preventDefault();
					go(parseInt(t.getAttribute('data-lgo-thumb'), 10) || 0);
				});
			});
			track.addEventListener('keydown', function (ev) {
				if (ev.key === 'ArrowRight') {
					ev.preventDefault();
					go(index + 1);
				} else if (ev.key === 'ArrowLeft') {
					ev.preventDefault();
					go(index - 1);
				}
			});
			track.addEventListener('scroll', function () {
				window.requestAnimationFrame(update);
			}, { passive: true });
		});
	}

	/* Share: Web Share API when available, and "Kopiraj link". */
	function initShare() {
		document.querySelectorAll('[data-lgo-share]').forEach(function (root) {
			var data = { title: root.getAttribute('data-title'), text: root.getAttribute('data-text'), url: root.getAttribute('data-url') };
			var native = root.querySelector('[data-lgo-share-native]');
			var copy = root.querySelector('[data-lgo-copy]');
			if (native && navigator.share) {
				native.hidden = false;
				native.addEventListener('click', function () {
					navigator.share(data).catch(function () {});
				});
			}
			if (copy && navigator.clipboard) {
				copy.hidden = false;
				copy.addEventListener('click', function () {
					navigator.clipboard.writeText(data.url).then(function () {
						var label = copy.querySelector('[data-lgo-copy-label]');
						if (label) {
							label.textContent = 'Link je kopiran';
						}
					});
				});
			}
		});
	}

	/* Loan calculator (same formula as App\Support\Listing\LoanCalculator). */
	function initCalculator() {
		document.querySelectorAll('[data-lgo-calc]').forEach(function (root) {
			var symbol = root.getAttribute('data-currency') || '€';
			var inputs = {};
			root.querySelectorAll('[data-lgo-calc-in]').forEach(function (el) {
				inputs[el.name] = el;
			});
			var monthlyEl = root.querySelector('[data-lgo-calc-monthly]');
			var totalEl = root.querySelector('[data-lgo-calc-total]');

			function num(name) {
				var v = parseFloat(String(inputs[name] ? inputs[name].value : '').replace(',', '.'));
				return isFinite(v) && v > 0 ? v : 0;
			}

			function money(v) {
				return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' ' + symbol;
			}

			function calc() {
				var principal = Math.max(0, num('price') - num('down'));
				var months = Math.round(num('months'));
				var r = num('rate') / 100 / 12;
				var monthly = 0;
				if (principal > 0 && months > 0) {
					monthly = r > 0 ? principal * r / (1 - Math.pow(1 + r, -months)) : principal / months;
				}
				monthlyEl.textContent = money(monthly);
				totalEl.textContent = money(num('down') + monthly * months);
			}

			Object.keys(inputs).forEach(function (k) {
				inputs[k].addEventListener('input', calc);
			});
		});
	}

	function init() {
		initReveal();
		initBar();
		initGallery();
		initShare();
		initCalculator();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
