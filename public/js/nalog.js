// Account settings: the photo is saved as soon as it is chosen, and "show passwords" toggles the password fields.
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

	document.querySelectorAll('[data-show-passwords]').forEach(function (box) {
		box.addEventListener('change', function () {
			box.closest('form').querySelectorAll('input[autocomplete$="password"]').forEach(function (field) {
				field.type = box.checked ? 'text' : 'password';
			});
		});
	});
})();
