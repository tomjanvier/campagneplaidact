/**
 * Signature enrichie PLAID·ACT : bascule organisation / personnalité.
 *
 * Masque et rend obligatoires les bons blocs de champs selon l'état des
 * cases « signer en tant qu'organisation » et « titre et fonction ».
 */
(function () {
	"use strict";

	// Marqueur posé sur un formulaire déjà initialisé (idempotence).
	var INIT_FLAG = "plaidactOrgSignatureReady";

	function setRequired(input, isRequired) {
		if (!input) {
			return;
		}

		input.required = isRequired;
	}

	function initOrganizationSignature(form) {
		if (!form || form.dataset[INIT_FLAG] === "1") {
			return;
		}

		var organizationToggle = form.querySelector('[name="petitioner_sign_as_organization"]');
		var personalityToggle = form.querySelector('[name="petitioner_sign_as_personality"]');

		// Rien à faire si le formulaire ne porte pas la signature enrichie
		// (fonctionnalité désactivée dans Réglages → PLAID·ACT).
		if (!organizationToggle && !personalityToggle) {
			return;
		}

		form.dataset[INIT_FLAG] = "1";

		var personFields = [
			form.querySelector('[name="petitioner_fname"]'),
			form.querySelector('[name="petitioner_lname"]')
		];
		var organizationFields = [
			form.querySelector('[name="petitioner_organization_name"]'),
			form.querySelector('[name="petitioner_organization_logo"]'),
			form.querySelector('[name="petitioner_organization_public"]')
		];
		var personalityFields = [
			form.querySelector('[name="petitioner_signer_title"]'),
			form.querySelector('[name="petitioner_signer_function"]')
		];

		// État initial du required défini par le serveur : on s'en sert de
		// référence pour ne jamais rendre obligatoire un champ optionnel.
		var originallyRequired = new WeakMap();

		personFields.concat(organizationFields, personalityFields).forEach(function (input) {
			if (input) {
				originallyRequired.set(input, input.required);
			}
		});

		function setGroupVisibility(inputs, isVisible) {
			inputs.forEach(function (input) {
				if (!input) {
					return;
				}

				var wrapper = input.closest(".petitioner__input");

				if (wrapper) {
					wrapper.hidden = !isVisible;
					wrapper.classList.toggle("plaidact-petitioner-field--hidden", !isVisible);
				}
			});
		}

		function syncFields() {
			var signsAsOrganization = organizationToggle ? organizationToggle.checked : false;
			var signsAsPersonality = personalityToggle ? personalityToggle.checked : false;

			setGroupVisibility(personFields, !signsAsOrganization);
			setGroupVisibility(organizationFields, signsAsOrganization);
			setGroupVisibility(personalityFields, signsAsPersonality && !signsAsOrganization);

			personFields.forEach(function (input) {
				setRequired(input, !signsAsOrganization && originallyRequired.get(input));
			});

			organizationFields.forEach(function (input) {
				setRequired(input, signsAsOrganization && originallyRequired.get(input));
			});

			personalityFields.forEach(function (input) {
				setRequired(input, signsAsPersonality && !signsAsOrganization && originallyRequired.get(input));
			});
		}

		if (organizationToggle) {
			organizationToggle.addEventListener("change", syncFields);
		}
		if (personalityToggle) {
			personalityToggle.addEventListener("change", syncFields);
		}

		syncFields();
	}

	function initAllForms(root) {
		(root || document)
			.querySelectorAll('form[id^="petitioner-form-"]')
			.forEach(initOrganizationSignature);
	}

	document.addEventListener("DOMContentLoaded", function () {
		initAllForms(document);

		// Les pétitions peuvent être injectées après chargement (bloc édité,
		// contenu re-rendu). Un observateur borné ré-initialise au besoin.
		if (typeof MutationObserver === "undefined") {
			return;
		}

		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i += 1) {
				var addedNodes = mutations[i].addedNodes;

				for (var j = 0; j < addedNodes.length; j += 1) {
					var node = addedNodes[j];

					if (!(node instanceof HTMLElement)) {
						continue;
					}

					if (node.matches('form[id^="petitioner-form-"]')) {
						initOrganizationSignature(node);
					} else if (node.querySelector) {
						initAllForms(node);
					}
				}
			}
		});

		observer.observe(document.body, { childList: true, subtree: true });
	});
})();
