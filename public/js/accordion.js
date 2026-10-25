/* loglasi.rs: questions (<details name="...">) with the same name are one group where one answer is open
   at a time. Browsers do this themselves; this covers the ones that do not know the name attribute yet. */
(function () {
	'use strict';

	if ('name' in document.createElement('details')) { return; }
	document.addEventListener('toggle', function (event) {
		var details = event.target;
		if (!details.open || details.tagName !== 'DETAILS' || !details.getAttribute('name')) { return; }
		Array.prototype.forEach.call(document.querySelectorAll('details[name="' + details.getAttribute('name') + '"][open]'), function (other) {
			if (other !== details) { other.open = false; }
		});
	}, true);
})();
