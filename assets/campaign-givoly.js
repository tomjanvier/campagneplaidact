(function () {
    function getField(formData, names) {
        for (var i = 0; i < names.length; i += 1) {
            var value = formData.get(names[i]);
            if (value) {
                return String(value);
            }
        }

        return '';
    }

    function appendQuery(url, params) {
        var target;

        try {
            target = new URL(url, window.location.href);
        } catch (error) {
            return url;
        }

        Object.keys(params).forEach(function (key) {
            if (params[key]) {
                target.searchParams.set(key, params[key]);
            }
        });

        return target.toString();
    }

    document.addEventListener('petitionerFormSubmit', function (event) {
        if (!event.detail || !event.detail.success || !event.detail.formData || !event.detail.wrapperEl) {
            return;
        }

        var wrapper = event.detail.wrapperEl.closest('.plaidact-petition-block');
        if (!wrapper) {
            return;
        }

        var cta = wrapper.querySelector('.plaidact-givoly-cta');
        if (!cta) {
            return;
        }

        var link = cta.querySelector('a');
        if (!link) {
            return;
        }

        var formData = event.detail.formData;
        var firstName = getField(formData, ['petitioner_fname', 'fname', 'first_name', 'prenom']);
        var lastName = getField(formData, ['petitioner_lname', 'lname', 'last_name', 'nom']);
        var fullName = getField(formData, ['petitioner_name', 'name', 'full_name']);
        var email = getField(formData, ['petitioner_email', 'email']);
        var postcode = getField(formData, ['petitioner_postal_code', 'postal_code', 'postcode', 'zip']);
        var phone = getField(formData, ['petitioner_phone', 'phone', 'telephone']);

        if (!firstName && !lastName && fullName) {
            var parts = fullName.trim().split(/\s+/);
            firstName = parts.shift() || '';
            lastName = parts.join(' ');
        }

        var enrichedUrl = appendQuery(wrapper.getAttribute('data-plaidact-givoly-url') || link.href, {
            givoly_first_name: firstName,
            givoly_last_name: lastName,
            givoly_name: fullName || [firstName, lastName].filter(Boolean).join(' '),
            givoly_email: email,
            givoly_postcode: postcode,
            givoly_phone: phone,
            first_name: firstName,
            last_name: lastName,
            name: fullName || [firstName, lastName].filter(Boolean).join(' '),
            email: email,
            postcode: postcode,
            phone: phone
        });

        link.href = enrichedUrl;
        if (wrapper.getAttribute('data-plaidact-givoly-url')) {
            wrapper.setAttribute('data-redirect-url', enrichedUrl);
        }

        cta.hidden = false;
    }, true);
}());
