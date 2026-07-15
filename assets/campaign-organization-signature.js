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

		if (!organizationToggle) {
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
		var originallyRequired = new WeakMap();

		personFields.concat(organizationFields).forEach(function (input) {
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
			var signsAsOrganization = organizationToggle.checked;

			setGroupVisibility(personFields, !signsAsOrganization);
			setGroupVisibility(organizationFields, signsAsOrganization);

			personFields.forEach(function (input) {
				setRequired(input, !signsAsOrganization && originallyRequired.get(input));
			});

			organizationFields.forEach(function (input) {
				setRequired(input, signsAsOrganization && originallyRequired.get(input));
			});
		}

		organizationToggle.addEventListener('change', syncFields);
		syncFields();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('form[id^="petitioner-form-"]').forEach(initOrganizationSignature);
	});
})();
