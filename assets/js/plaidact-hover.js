(function () {
	'use strict';

	function positionCard(term, card) {
		var rect = term.getBoundingClientRect();
		var maxWidth = Math.min(360, window.innerWidth - 24);
		card.style.maxWidth = maxWidth + 'px';
		card.style.left = '12px';
		card.style.top = '12px';
		card.classList.add('is-open');

		var cardRect = card.getBoundingClientRect();
		var left = rect.left + (rect.width / 2) - (cardRect.width / 2);
		left = Math.max(12, Math.min(left, window.innerWidth - cardRect.width - 12));
		var top = rect.bottom + 10;
		if (top + cardRect.height > window.innerHeight - 12) {
			top = rect.top - cardRect.height - 10;
		}
		if (top < 12) {
			top = 12;
		}

		card.style.left = left + 'px';
		card.style.top = top + 'px';
	}

	document.addEventListener('DOMContentLoaded', function () {
		var terms = [];
		document.querySelectorAll('.plaidact-hover-term').forEach(function (term) {
			var card = term.querySelector('.plaidact-hover-card');
			if (!card) {
				return;
			}
			var closeTimer = null;

			var open = function () {
				if (closeTimer) {
					window.clearTimeout(closeTimer);
					closeTimer = null;
				}
				positionCard(term, card);
			};
			var close = function () {
				if (closeTimer) {
					window.clearTimeout(closeTimer);
				}
				closeTimer = window.setTimeout(function () {
					card.classList.remove('is-open');
				}, 90);
			};
			var closeImmediately = function () {
				if (closeTimer) {
					window.clearTimeout(closeTimer);
					closeTimer = null;
				}
				card.classList.remove('is-open');
			};

			term.addEventListener('mouseenter', open);
			term.addEventListener('focusin', open);
			term.addEventListener('mouseleave', close);
			term.addEventListener('focusout', close);
			card.addEventListener('mouseenter', open);
			card.addEventListener('mouseleave', close);

			term.addEventListener('click', function (event) {
				if (card.classList.contains('is-open')) {
					closeImmediately();
					return;
				}
				open();
				event.preventDefault();
			});

			terms.push({ term: term, closeImmediately: closeImmediately });

			window.addEventListener('scroll', function () {
				if (card.classList.contains('is-open')) {
					positionCard(term, card);
				}
			}, { passive: true });
			window.addEventListener('resize', function () {
				if (card.classList.contains('is-open')) {
					positionCard(term, card);
				}
			});
		});

		document.addEventListener('click', function (event) {
			terms.forEach(function (entry) {
				if (!entry.term.contains(event.target)) {
					entry.closeImmediately();
				}
			});
		});
	});
})();
