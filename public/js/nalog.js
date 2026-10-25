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
