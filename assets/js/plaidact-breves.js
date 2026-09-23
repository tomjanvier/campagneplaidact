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
			return needsScroll;
		}

		var needsScroll = updateNav();
		window.addEventListener('resize', function () { needsScroll = updateNav(); }, { passive: true });
		if ('ResizeObserver' in window) {
			try {
				new ResizeObserver(function () { needsScroll = updateNav(); }).observe(viewport);
			} catch (_e) {}
		} else {
			// Repli : réévalue après chargement des images.
			window.addEventListener('load', function () { needsScroll = updateNav(); });
		}

		// Défilement automatique : respect du prefers-reduced-motion et pause au survol/focus/drag.
		var autoplayAttr = root.getAttribute('data-autoplay');
		var autoplayEnabled = autoplayAttr !== '0' && autoplayAttr !== 'false';
		var intervalAttr = parseInt(root.getAttribute('data-interval') || '4000', 10);
		var intervalMs = isNaN(intervalAttr) ? 4000 : Math.max(1500, Math.min(intervalAttr, 10000));
		var continuousAttr = root.getAttribute('data-continuous');
		var continuousEnabled = continuousAttr === '1' || continuousAttr === 'true';
		var speedAttr = parseInt(root.getAttribute('data-speed') || '40', 10);
		var speedPxPerSec = isNaN(speedAttr) ? 40 : Math.max(10, Math.min(speedAttr, 200));
		var prefersReduced = false;
		try { prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (_e) {}
		var autoplayTimer = null;
		var isHovered = false;
		var isFocused = false;
		var isVisible = true;
		var marqueeRaf = null;
		var lastMarqueeTime = 0;

		function canAutoplay() {
			return autoplayEnabled && needsScroll && isVisible && !prefersReduced && !isHovered && !isFocused && !isDown && !document.hidden;
		}

		function scrollToNext() {
			if (!canAutoplay() || continuousEnabled) return;
			var maxScroll = viewport.scrollWidth - viewport.clientWidth - 2;
			if (viewport.scrollLeft >= maxScroll) {
				// Retour au début en douceur
				try { viewport.scrollTo({ left: 0, behavior: 'smooth' }); } catch (_e) { viewport.scrollLeft = 0; }
			} else {
				scrollByAmount(1);
			}
		}

		function startAutoplay() {
			stopAutoplay();
			if (!autoplayEnabled || prefersReduced || continuousEnabled) return;
			autoplayTimer = window.setInterval(scrollToNext, intervalMs);
		}

		function stopAutoplay() {
			if (autoplayTimer) { window.clearInterval(autoplayTimer); autoplayTimer = null; }
		}

		// Pause au survol / focus
		root.addEventListener('mouseenter', function () { isHovered = true; stopAutoplay(); if (continuousEnabled) stopMarquee(); });
		root.addEventListener('mouseleave', function () { isHovered = false; startAutoplay(); if (continuousEnabled) startMarquee(); });
		viewport.addEventListener('focusin', function () { isFocused = true; stopAutoplay(); if (continuousEnabled) stopMarquee(); });
		viewport.addEventListener('focusout', function () { isFocused = false; startAutoplay(); if (continuousEnabled) startMarquee(); });
		// Pause pendant le drag
		viewport.addEventListener('mousedown', function () { stopAutoplay(); if (continuousEnabled) stopMarquee(); });
		viewport.addEventListener('mouseup', function () { startAutoplay(); if (continuousEnabled) startMarquee(); });
		viewport.addEventListener('touchstart', function () { stopAutoplay(); if (continuousEnabled) stopMarquee(); }, { passive: true });
		viewport.addEventListener('touchend', function () { startAutoplay(); if (continuousEnabled) startMarquee(); });
		document.addEventListener('visibilitychange', function () { if (document.hidden) { stopAutoplay(); stopMarquee(); } else { startAutoplay(); if (continuousEnabled) startMarquee(); } });
		// Pause quand l'utilisateur scroll manuellement (mode discret uniquement)
		var scrollDebounce = null;
		viewport.addEventListener('scroll', function () {
			if (!autoplayEnabled || continuousEnabled) return;
			stopAutoplay();
			if (scrollDebounce) window.clearTimeout(scrollDebounce);
			scrollDebounce = window.setTimeout(startAutoplay, 2500);
		}, { passive: true });

		// Interactions boutons : relance le timer
		if (prev) prev.addEventListener('click', function () { stopAutoplay(); window.setTimeout(startAutoplay, 3000); });
		if (next) next.addEventListener('click', function () { stopAutoplay(); window.setTimeout(startAutoplay, 3000); });

		if (autoplayEnabled && !prefersReduced && !continuousEnabled) {
			// Démarre après un court délai pour laisser le temps de voir la première carte
			window.setTimeout(startAutoplay, 1800);
		}

		// --- Mode continu (marquee) à la Focus 2030 ---
		var track = viewport.querySelector('.plaidact-breves__track');
		function cloneForMarquee() {
			if (!track || track.dataset.marqueeCloned) return;
			var originals = Array.from(track.children);
			originals.forEach(function (child) {
				var clone = child.cloneNode(true);
				clone.removeAttribute('id');
				clone.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
				clone.setAttribute('aria-hidden', 'true');
				clone.querySelectorAll('a, button, [tabindex]').forEach(function (el) { el.setAttribute('tabindex', '-1'); });
				track.appendChild(clone);
			});
			track.dataset.marqueeCloned = '1';
			viewport.style.scrollSnapType = 'none';
			viewport.style.scrollBehavior = 'auto';
		}
		function canMarquee() {
			return continuousEnabled && autoplayEnabled && needsScroll && isVisible && !prefersReduced && !isHovered && !isFocused && !isDown && !document.hidden;
		}
		function stopMarquee() {
			if (marqueeRaf) { window.cancelAnimationFrame(marqueeRaf); marqueeRaf = null; lastMarqueeTime = 0; }
		}
		function startMarquee() {
			stopMarquee();
			if (!continuousEnabled || !autoplayEnabled || prefersReduced || !needsScroll) return;
			if (track && !track.dataset.marqueeCloned) {
				cloneForMarquee();
			}
			lastMarqueeTime = 0;
			function step(timestamp) {
				if (!lastMarqueeTime) lastMarqueeTime = timestamp;
				var delta = timestamp - lastMarqueeTime;
				lastMarqueeTime = timestamp;
				if (canMarquee()) {
					viewport.scrollLeft += (speedPxPerSec * delta / 1000);
					var half = track ? track.scrollWidth / 2 : viewport.scrollWidth / 2;
					if (half > 0 && viewport.scrollLeft >= half) {
						viewport.scrollLeft -= half;
					}
				}
				marqueeRaf = window.requestAnimationFrame(step);
			}
			marqueeRaf = window.requestAnimationFrame(step);
		}
		if (continuousEnabled && autoplayEnabled && !prefersReduced) {
			window.setTimeout(function () { needsScroll = updateNav(); if (needsScroll) startMarquee(); }, 300);
			window.addEventListener('resize', function () { needsScroll = updateNav(); if (!needsScroll) stopMarquee(); else if (continuousEnabled) startMarquee(); }, { passive: true });
		}

		// Évite de maintenir une animation active quand le carrousel est hors écran.
		if ('IntersectionObserver' in window) {
			try {
				new IntersectionObserver(function (entries) {
					isVisible = !!(entries[0] && entries[0].isIntersecting);
					if (!isVisible) {
						stopAutoplay();
						stopMarquee();
					} else if (continuousEnabled) {
						startMarquee();
					} else {
						startAutoplay();
					}
				}).observe(root);
			} catch (_e) {}
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
