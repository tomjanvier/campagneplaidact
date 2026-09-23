(function () {
	'use strict';

	/**
	 * Amélioration progressive du carrousel des brèves : drag à la souris,
	 * navigation au clavier et boutons précédent/suivant.
	 * Le défilement de base reste 100% CSS (scroll-snap + scrollbar native)
	 * pour garantir performance et accessibilité sans JS.
	 */

	function init(root) {
		if (!root || root.dataset.plaidactBrevesInit) {
			return;
		}
		root.dataset.plaidactBrevesInit = '1';

		var viewport = root.querySelector('.plaidact-breves__viewport');
		if (!viewport) {
			return;
		}

		var prev = root.querySelector('.plaidact-breves__arrow--prev');
		var next = root.querySelector('.plaidact-breves__arrow--next');
		var GAP = 24;

		function scrollByAmount(dir) {
			var card = viewport.querySelector('.plaidact-breve');
			var amount = card ? card.getBoundingClientRect().width + GAP : viewport.clientWidth * 0.85;
			try {
				viewport.scrollBy({ left: dir * amount, behavior: 'smooth' });
			} catch (_e) {
				viewport.scrollLeft += dir * amount;
			}
		}

		if (prev) {
			prev.addEventListener('click', function () {
				scrollByAmount(-1);
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				scrollByAmount(1);
			});
		}

		// Navigation clavier lorsque le carrousel a le focus.
		viewport.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') {
				e.preventDefault();
				scrollByAmount(-1);
			} else if (e.key === 'ArrowRight') {
				e.preventDefault();
				scrollByAmount(1);
			} else if (e.key === 'Home') {
				e.preventDefault();
				viewport.scrollTo({ left: 0, behavior: 'smooth' });
			} else if (e.key === 'End') {
				e.preventDefault();
				viewport.scrollTo({ left: viewport.scrollWidth, behavior: 'smooth' });
			}
		});

		// Drag à la souris (desktop) : conserve le scroll natif tactile sur mobile.
		var isDown = false;
		var startX = 0;
		var scrollLeft = 0;

		viewport.addEventListener('mousedown', function (e) {
			isDown = true;
			viewport.classList.add('is-dragging');
			startX = e.pageX - viewport.offsetLeft;
			scrollLeft = viewport.scrollLeft;
		});

		viewport.addEventListener('mouseleave', function () {
			isDown = false;
			viewport.classList.remove('is-dragging');
		});

		viewport.addEventListener('mouseup', function () {
			isDown = false;
			viewport.classList.remove('is-dragging');
		});

		viewport.addEventListener('mousemove', function (e) {
			if (!isDown) {
				return;
			}
			e.preventDefault();
			var x = e.pageX - viewport.offsetLeft;
			var walk = (x - startX) * 1.2;
			viewport.scrollLeft = scrollLeft - walk;
		});

		// Masque les flèches quand le contenu ne dépasse pas.
		function updateNav() {
			var max = viewport.scrollWidth - viewport.clientWidth - 2;
			var needsScroll = max > 10;
			root.classList.toggle('plaidact-breves--no-scroll', !needsScroll);
			if (prev) {
				prev.hidden = !needsScroll;
			}
			if (next) {
				next.hidden = !needsScroll;
			}
		}

		updateNav();
		window.addEventListener('resize', updateNav, { passive: true });
		if ('ResizeObserver' in window) {
			try {
				new ResizeObserver(updateNav).observe(viewport);
			} catch (_e) {}
		} else {
			// Repli : réévalue après chargement des images.
			window.addEventListener('load', updateNav);
		}
	}

	function boot() {
		document.querySelectorAll('[data-plaidact-breves]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	// Prise en charge des blocs injectés dynamiquement (aperçu Gutenberg).
	if ('MutationObserver' in window && document.body) {
		var obs = new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				m.addedNodes.forEach(function (n) {
					if (n.nodeType !== 1) {
						return;
					}
					if (n.matches && n.matches('[data-plaidact-breves]')) {
						init(n);
					}
					if (n.querySelectorAll) {
						n.querySelectorAll('[data-plaidact-breves]').forEach(init);
					}
				});
			});
		});
		obs.observe(document.body, { childList: true, subtree: true });
	}
})();
