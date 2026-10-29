// Account settings: the photo is saved as soon as it is chosen.
(function () {
	document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
		var input = form.querySelector('input[type=file]');
		form.classList.add('is-auto');
		input.addEventListener('change', function () {
			if (input.files.length) {
				form.classList.add('is-busy');
				form.submit();
			}
		});
	});
})();

// "Dodajte svoje mesto" from the overview lands on the Mesto field with its picker open.
(function () {
	if (location.hash !== '#mesto') { return; }
	window.addEventListener('load', function () {
		var field = document.getElementById('mesto');
		if (!field) { return; }
		field.scrollIntoView({ block: 'center' });
		var trigger = field.querySelector('.lp-trigger') || field.querySelector('select');
		if (trigger) {
			trigger.focus({ preventScroll: true });
			if (trigger.classList.contains('lp-trigger')) { trigger.click(); }
		}
	});
})();
