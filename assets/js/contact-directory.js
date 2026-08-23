(function () {
	function commissionMatches(value, selected) {
		if (!selected) return true;
		var parts = (value || '').toLowerCase().split(/\s*[|;,]\s*/).filter(Boolean);
		return parts.includes(selected);
	}

	function buildFilteredDownloadUrl(root) {
		var link = root.querySelector('.plaidact-fcd-filtered-download');
		if (!link) return;
		var baseUrl = link.dataset.baseUrl;
		if (!baseUrl) return;

		var url = new URL(baseUrl, window.location.origin);
		url.searchParams.set('filtered', '1');
		url.searchParams.set('search', root.querySelector('.plaidact-fcd-search')?.value || '');
		url.searchParams.set('custom', root.querySelector('.plaidact-fcd-select-filter[data-filter="custom"]')?.value || '');
		url.searchParams.set('commission', root.querySelector('.plaidact-fcd-select-filter[data-filter="commission"]')?.value || '');
		url.searchParams.set('groupe', root.querySelector('.plaidact-fcd-select-filter[data-filter="groupe"]')?.value || '');
		link.href = url.toString();
	}

	function filterRows(root) {
		var search = (root.querySelector('.plaidact-fcd-search')?.value || '').toLowerCase();
		var custom = root.querySelector('.plaidact-fcd-select-filter[data-filter="custom"]')?.value || '';
		var commission = root.querySelector('.plaidact-fcd-select-filter[data-filter="commission"]')?.value || '';
		var groupe = root.querySelector('.plaidact-fcd-select-filter[data-filter="groupe"]')?.value || '';

		root.querySelectorAll('tbody tr').forEach(function (row) {
			var matchesSearch = row.innerText.toLowerCase().includes(search);
			var matchesCustom = !custom || (row.dataset.custom || '') === custom;
			var matchesCommission = commissionMatches(row.dataset.commission || '', commission);
			var matchesGroupe = !groupe || (row.dataset.groupe || '') === groupe;
			row.style.display = matchesSearch && matchesCustom && matchesCommission && matchesGroupe ? '' : 'none';
		});

		buildFilteredDownloadUrl(root);
	}

	function toggleColumns(root) {
		var table = root.querySelector('.plaidact-fcd-table');
		if (!table) return;
		var headerRow = table.querySelector('thead tr');
		if (!headerRow) return;

		['groupe', 'commission', 'custom', 'social'].forEach(function (column) {
			var checked = root.querySelector('.plaidact-fcd-column-toggle[data-column="' + column + '"]')?.checked;
			var visible = checked !== false;
			var headerCell = headerRow.querySelector('th[data-column="' + column + '"]');
			if (!headerCell) return;
			var index = Array.prototype.indexOf.call(headerRow.children, headerCell);
			if (index < 0) return;

			table.querySelectorAll('tr').forEach(function (row) {
				if (row.children[index]) {
					row.children[index].style.display = visible ? '' : 'none';
				}
			});
		});
	}

	document.addEventListener('input', function (event) {
		var root = event.target.closest('.plaidact-fcd');
		if (!root) return;
		if (event.target.matches('.plaidact-fcd-search, .plaidact-fcd-select-filter')) filterRows(root);
	});

	document.addEventListener('change', function (event) {
		var root = event.target.closest('.plaidact-fcd');
		if (!root) return;
		if (event.target.matches('.plaidact-fcd-column-toggle')) toggleColumns(root);
		if (event.target.matches('.plaidact-fcd-select-filter')) filterRows(root);
	});

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.plaidact-fcd').forEach(function (root) {
			filterRows(root);
			toggleColumns(root);
		});
	});
})();
