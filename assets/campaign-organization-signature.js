(function () {
	function setRequired(input, isRequired) {
		if (!input) {
			return;
		}

		input.required = isRequired;
		input.toggleAttribute('required', isRequired);
	}

	function initOrganizationSignature(form) {
		var organizationToggle = form.querySelector('[name="petitioner_sign_as_organization"]');
		var personalityToggle = form.querySelector('[name="petitioner_sign_as_personality"]');

		if (!organizationToggle && !personalityToggle) {
			return;
		}

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

				var wrapper = input.closest('.petitioner__input');
				if (wrapper) {
					wrapper.hidden = !isVisible;
					wrapper.classList.toggle('plaidact-petitioner-field--hidden', !isVisible);
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
			organizationToggle.addEventListener('change', syncFields);
		}
		if (personalityToggle) {
			personalityToggle.addEventListener('change', syncFields);
		}
		syncFields();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('form[id^="petitioner-form-"]').forEach(initOrganizationSignature);
	});
})();
