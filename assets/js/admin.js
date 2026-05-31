document.addEventListener('DOMContentLoaded', function () {
	const triggerSelect = document.getElementById('wp-automation-engine-trigger-type');
	const triggerRows = document.querySelectorAll('.wpae-trigger-row');

	if (!triggerSelect || !triggerRows.length) {
		return;
	}

	const toggleTriggerRows = function () {
		const triggerType = triggerSelect.value;

		triggerRows.forEach(function (row) {
			const allowedTypes = (row.getAttribute('data-trigger-types') || '').split(/\s+/);
			const isVisible = allowedTypes.includes(triggerType);

			row.classList.toggle('is-hidden', !isVisible);
		});
	};

	triggerSelect.addEventListener('change', toggleTriggerRows);
	toggleTriggerRows();
});
