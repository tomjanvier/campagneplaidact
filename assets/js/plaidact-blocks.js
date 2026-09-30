(function (blocks, element, components, blockEditor, i18n) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ServerSideRender = window.wp.serverSideRender && (window.wp.serverSideRender.default || window.wp.serverSideRender);
	var useSelect = window.wp.data && window.wp.data.useSelect;

	function useWpTerms(taxonomy) {
		return useSelect
			? useSelect(function (select) { return select('core').getEntityRecords('taxonomy', taxonomy, { per_page: 100, hide_empty: false, _fields: 'id,name,slug' }); }, [taxonomy]) || []
			: [];
	}

	function termOptions(terms, selectedSlug, emptyLabel) {
		var options = [{ label: emptyLabel || __('Toutes les catégories', 'plaidact-campaign-core'), value: '' }];
		if (selectedSlug && !terms.some(function (term) { return term.slug === selectedSlug; })) {
			options.push({ label: selectedSlug, value: selectedSlug });
		}
		return options.concat(terms.map(function (term) { return { label: term.name, value: term.slug }; }));
	}

	function BlockPreview(props) {
		var blockProps = blockEditor.useBlockProps ? blockEditor.useBlockProps({ className: 'plaidact-block-preview' }) : { className: 'plaidact-block-preview' };
		var fallback = el(PlaceholderCard, props);
		var preview = ServerSideRender
			? el(ServerSideRender, {
				block: props.block,
				attributes: props.attributes,
				EmptyResponsePlaceholder: fallback,
				ErrorResponsePlaceholder: el(PlaceholderCard, {
					title: props.title,
					description: __('Aperçu indisponible. Le bloc sera rendu sur le site avec les données actuelles.', 'plaidact-campaign-core'),
					shortcode: props.shortcode,
				}),
			})
			: fallback;
		return el('div', blockProps, preview);
	}

	function PlaceholderCard(props) {
		return el(
			'div',
			{ className: 'plaidact-block-placeholder', style: { border: '1px dashed #8c8f94', padding: '1rem', borderRadius: '8px' } },
			el('strong', null, props.title),
			el('p', null, props.description),
			el('code', null, props.shortcode)
		);
	}

	blocks.registerBlockType('plaidact/timeline', {
		title: __('PLAID·ACT — Timeline agenda', 'plaidact-campaign-core'),
		icon: 'calendar-alt',
		category: 'widgets',
		description: __('Affiche les événements d’une timeline agenda.', 'plaidact-campaign-core'),
		attributes: {
			term: { type: 'string', default: '' },
			title: { type: 'string', default: '' },
			showTitle: { type: 'boolean', default: true },
			showDownload: { type: 'boolean', default: true },
			layout: { type: 'string', default: 'vertical' },
			columns: { type: 'number', default: 3 },
			fillEmptyMonths: { type: 'boolean', default: false },
			eventsPerColumn: { type: 'number', default: 0 }
		},
		edit: function (props) {
			var attrs = props.attributes;
			var timelines = useWpTerms('agenda_timeline');
			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Réglages timeline', 'plaidact-campaign-core') },
						el(SelectControl, {
							label: __('Timeline', 'plaidact-campaign-core'),
							value: attrs.term,
							options: termOptions(timelines, attrs.term, __('Choisissez une timeline', 'plaidact-campaign-core')),
							onChange: function (value) { props.setAttributes({ term: value }); },
							help: __('Choisissez une timeline existante.', 'plaidact-campaign-core')
						}),
						el(TextControl, {
							label: __('Titre (vide = nom de la timeline)', 'plaidact-campaign-core'),
							value: attrs.title,
							onChange: function (value) { props.setAttributes({ title: value }); }
						}),
						el(ToggleControl, {
							label: __('Afficher le titre', 'plaidact-campaign-core'),
							checked: !!attrs.showTitle,
							onChange: function (value) { props.setAttributes({ showTitle: !!value }); }
						}),
						el(ToggleControl, {
							label: __('Proposer le téléchargement iCal', 'plaidact-campaign-core'),
							checked: !!attrs.showDownload,
							onChange: function (value) { props.setAttributes({ showDownload: !!value }); }
						}),
						el(SelectControl, {
							label: __('Disposition', 'plaidact-campaign-core'),
							value: attrs.layout,
							options: [
								{ label: __('Verticale', 'plaidact-campaign-core'), value: 'vertical' },
								{ label: __('Horizontale', 'plaidact-campaign-core'), value: 'horizontal' }
							],
							onChange: function (value) { props.setAttributes({ layout: value }); }
						}),
						el(RangeControl, {
							label: __('Colonnes', 'plaidact-campaign-core'),
							value: attrs.columns,
							min: 1,
							max: 6,
							onChange: function (value) { props.setAttributes({ columns: value }); }
						}),
						el(ToggleControl, {
							label: __('Afficher les mois vides', 'plaidact-campaign-core'),
							checked: !!attrs.fillEmptyMonths,
							onChange: function (value) { props.setAttributes({ fillEmptyMonths: !!value }); }
						}),
						el(TextControl, {
							label: __('Événements par colonne (0 = tous)', 'plaidact-campaign-core'),
							value: String(attrs.eventsPerColumn || 0),
							onChange: function (value) { props.setAttributes({ eventsPerColumn: parseInt(value, 10) || 0 }); }
						})
					)
				),
				el(BlockPreview, {
					block: 'plaidact/timeline',
					attributes: attrs,
					title: attrs.title || __('Bloc timeline agenda', 'plaidact-campaign-core'),
					description: attrs.term
						? __('Le site public affichera la timeline sélectionnée.', 'plaidact-campaign-core')
						: __('Renseignez le slug de la timeline dans les réglages du bloc.', 'plaidact-campaign-core'),
					shortcode: '[plaidact_timeline term="' + (attrs.term || '') + '"]'
				})
			);
		},
		save: function () {
			return null;
		}
	});

	blocks.registerBlockType('plaidact/asso-cause-list', {
		title: __('PLAID·ACT — Associations par cause', 'plaidact-campaign-core'),
		icon: 'groups',
		category: 'widgets',
		description: __('Affiche le répertoire des associations filtré par cause.', 'plaidact-campaign-core'),
		attributes: {
			cause: { type: 'string', default: '' },
			postsToShow: { type: 'number', default: 9 }
		},
		edit: function (props) {
			var attrs = props.attributes;
			var causes = useWpTerms('associations');
			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Réglages répertoire', 'plaidact-campaign-core') },
						el(SelectControl, {
							label: __('Catégorie', 'plaidact-campaign-core'),
							value: attrs.cause,
							options: termOptions(causes, attrs.cause, __('Toutes les catégories', 'plaidact-campaign-core')),
							onChange: function (value) { props.setAttributes({ cause: value }); }
						}),
						el(RangeControl, {
							label: __('Nombre de fiches', 'plaidact-campaign-core'),
							value: attrs.postsToShow,
							min: 1,
							max: 24,
							onChange: function (value) { props.setAttributes({ postsToShow: value }); }
						})
					)
				),
				el(BlockPreview, {
					block: 'plaidact/asso-cause-list',
					attributes: attrs,
					title: __('Bloc répertoire des associations', 'plaidact-campaign-core'),
					description: __('La liste réelle sera rendue sur le site public.', 'plaidact-campaign-core'),
					shortcode: '[plaidact_asso_directory]'
				})
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
