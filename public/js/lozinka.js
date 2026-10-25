// Every password field gets an eye button that shows or hides what was typed.
(function () {
	var eye = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
	var eyeOff = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.2M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';

	document.querySelectorAll('input[type="password"]').forEach(function (input) {
		var wrap = document.createElement('span');
		var button = document.createElement('button');
		wrap.className = 'lg-lozinka';
		input.parentNode.insertBefore(wrap, input);
		wrap.appendChild(input);

		button.type = 'button';
		button.className = 'lg-lozinka__oko';
		button.setAttribute('aria-controls', input.id || '');
		var set = function (shown) {
			input.type = shown ? 'text' : 'password';
			button.innerHTML = shown ? eyeOff : eye;
			button.setAttribute('aria-label', shown ? 'Sakrij lozinku' : 'Prikaži lozinku');
			button.setAttribute('aria-pressed', shown ? 'true' : 'false');
		};
		set(false);
		button.addEventListener('click', function () {
			set(input.type === 'password');
			input.focus();
		});
		wrap.appendChild(button);

		// Never send the form with the password left visible in the browser's history of typed text.
		if (input.form) {
			input.form.addEventListener('submit', function () { set(false); });
		}
	});
})();
