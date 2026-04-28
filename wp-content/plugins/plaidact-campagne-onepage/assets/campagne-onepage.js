(() => {
	const shareTextField = document.getElementById('plaidact-share-text');
	if (!shareTextField) {
		return;
	}

	const links = Array.from(document.querySelectorAll('.plaidact-share-link[data-share-target]'));
	const pageUrl = window.location.href;

	const updateShareLinks = () => {
		const text = shareTextField.value.trim();
		links.forEach((link) => {
			const target = link.dataset.shareTarget;
			if (target === 'whatsapp') {
				link.href = `https://api.whatsapp.com/send?text=${encodeURIComponent(`${text} ${pageUrl}`.trim())}`;
			}

			if (target === 'x') {
				link.href = `https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl)}&text=${encodeURIComponent(text)}`;
			}
		});
	};

	shareTextField.addEventListener('input', updateShareLinks, { passive: true });

	links.forEach((link) => {
		link.addEventListener('click', async () => {
			if (link.dataset.shareTarget !== 'instagram') {
				return;
			}

			const text = shareTextField.value.trim();
			if (!text || !navigator.clipboard) {
				return;
			}

			try {
				await navigator.clipboard.writeText(`${text} ${pageUrl}`.trim());
			} catch (error) {
				// noop.
			}
		});
	});

	updateShareLinks();
})();
