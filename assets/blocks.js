(function (blocks, element, components, blockEditor, i18n) {
	const el = element.createElement;
	const __ = i18n.__;
	const InspectorControls = blockEditor.InspectorControls;
	const PanelBody = components.PanelBody;
	const TextControl = components.TextControl;
	const RangeControl = components.RangeControl;
	const __experimentalNumberControl = components.__experimentalNumberControl;
	const NumberControl = __experimentalNumberControl || TextControl;

	function PlaceholderCard(props) {
		return el(
			'div',
			{ className: props.className, style: { border: '1px dashed #8c8f94', padding: '1rem', borderRadius: '8px' } },
			el('strong', null, props.title),
			el('p', null, props.description),
			el('code', null, props.shortcode)
		);
	}

	blocks.registerBlockType('plaidact/newsletter', {
		title: __('PLAID·ACT — Newsletter', 'plaidact-campaign-core'),
		icon: 'email-alt',
		category: 'widgets',
		description: __('Affiche le formulaire newsletter PLAID·ACT.', 'plaidact-campaign-core'),
		attributes: {
			title: { type: 'string', default: '' },
			intro: { type: 'string', default: '' },
			buttonLabel: { type: 'string', default: '' },
		},
		edit: function (props) {
			const attrs = props.attributes;
			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Réglages newsletter', 'plaidact-campaign-core') },
						el(TextControl, {
							label: __('Titre', 'plaidact-campaign-core'),
							value: attrs.title,
							onChange: function (value) { props.setAttributes({ title: value }); },
							help: __('Laissez vide pour utiliser le titre global.', 'plaidact-campaign-core'),
						}),
						el(TextControl, {
							label: __('Texte', 'plaidact-campaign-core'),
							value: attrs.intro,
							onChange: function (value) { props.setAttributes({ intro: value }); },
							help: __('Laissez vide pour utiliser le texte global.', 'plaidact-campaign-core'),
						}),
						el(TextControl, {
							label: __('Libellé du bouton', 'plaidact-campaign-core'),
							value: attrs.buttonLabel,
							onChange: function (value) { props.setAttributes({ buttonLabel: value }); },
							help: __('Laissez vide pour utiliser le libellé global.', 'plaidact-campaign-core'),
						})
					)
				),
				el(PlaceholderCard, {
					title: attrs.title || __('Bloc newsletter', 'plaidact-campaign-core'),
					description: __('Le formulaire réel sera rendu sur le site public avec ces textes et les réglages Brevo du site.', 'plaidact-campaign-core'),
					shortcode: '[plaid_newsletter_form]',
				})
			);
		},
		save: function () {
			return null;
		},
	});


	blocks.registerBlockType('plaidact/petition-gauge', {
		title: __('PLAID·ACT — Jauge de signatures', 'plaidact-campaign-core'),
		icon: 'chart-bar',
		category: 'widgets',
		description: __('Affiche la progression des signatures pour une pétition Petitioner donnée.', 'plaidact-campaign-core'),
		attributes: {
			id: { type: 'number', default: 0 },
			title: { type: 'string', default: '' },
			width: { type: 'number', default: 34 },
			height: { type: 'number', default: 0 },
		},
		edit: function (props) {
			const petitionId = props.attributes.id || 0;
			const gaugeWidth = props.attributes.width || 34;
			const gaugeHeight = props.attributes.height || 0;

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Réglages de la jauge', 'plaidact-campaign-core') },
						el(NumberControl, {
							label: __('ID de la pétition Petitioner', 'plaidact-campaign-core'),
							value: petitionId,
							min: 0,
							onChange: function (value) { props.setAttributes({ id: parseInt(value, 10) || 0 }); },
							help: __('Laissez 0 pour utiliser la pétition configurée dans Campagne → Réglages.', 'plaidact-campaign-core'),
						}),
						el(TextControl, {
							label: __('Titre', 'plaidact-campaign-core'),
							value: props.attributes.title,
							onChange: function (value) { props.setAttributes({ title: value }); },
						}),
						el(RangeControl, {
							label: __('Largeur maximale (rem)', 'plaidact-campaign-core'),
							value: gaugeWidth,
							min: 12,
							max: 96,
							onChange: function (value) { props.setAttributes({ width: value }); },
						}),
						el(RangeControl, {
							label: __('Hauteur de la barre (rem, 0 = défaut)', 'plaidact-campaign-core'),
							value: gaugeHeight,
							min: 0,
							max: 6,
							step: 0.125,
							onChange: function (value) { props.setAttributes({ height: value }); },
						})
					)
				),
				el(PlaceholderCard, {
					title: props.attributes.title || __('Jauge de signatures', 'plaidact-campaign-core'),
					description: petitionId
						? __('Le site public affichera la progression de la pétition sélectionnée.', 'plaidact-campaign-core')
						: __('Le site public affichera la progression de la pétition configurée pour la campagne.', 'plaidact-campaign-core'),
					shortcode: '[plaid_petition_gauge' + (petitionId ? ' id="' + petitionId + '"' : '') + ' width="' + gaugeWidth + '"' + (gaugeHeight ? ' height="' + gaugeHeight + '"' : '') + ']',
				})
			);
		},
		save: function () {
			return null;
		},
	});

	blocks.registerBlockType('plaidact/partners', {
		title: __('PLAID·ACT — Partenaires', 'plaidact-campaign-core'),
		icon: 'groups',
		category: 'widgets',
		description: __('Affiche la grille des organisations partenaires.', 'plaidact-campaign-core'),
		attributes: {
			title: { type: 'string', default: '' },
			limit: { type: 'number', default: -1 },
		},
		edit: function (props) {
			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Réglages partenaires', 'plaidact-campaign-core') },
						el(TextControl, {
							label: __('Titre', 'plaidact-campaign-core'),
							value: props.attributes.title,
							onChange: function (value) { props.setAttributes({ title: value }); },
						}),
						el(RangeControl, {
							label: __('Nombre de partenaires (-1 = tous)', 'plaidact-campaign-core'),
							value: props.attributes.limit,
							min: -1,
							max: 24,
							onChange: function (value) { props.setAttributes({ limit: value }); },
						})
					)
				),
				el(PlaceholderCard, {
					title: props.attributes.title || __('Bloc partenaires', 'plaidact-campaign-core'),
					description: __('La grille réelle sera rendue sur le site public à partir des organisations porteuses.', 'plaidact-campaign-core'),
					shortcode: '[plaid_partners]',
				})
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
