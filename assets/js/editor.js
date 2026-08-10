/**
 * Consent Gate block editor integration (PLAN.md §7.5).
 *
 * Dependency-free of any build step: plain JS against the wp.* globals.
 * Two jobs: the per-block "Gate this embed" override on blocks that carry
 * embeds, and the withdrawal-control block.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.blocks ) {
		return;
	}

	var __ = wp.i18n.__;
	var el = wp.element.createElement;

	// Blocks that can carry third-party embeds. core/html because pasted
	// iframe markup lives there; the rest are the embed-bearing cores.
	var GATED_BLOCKS = [ 'core/embed', 'core/html', 'core/video', 'core/audio' ];

	function isGatedBlock( name ) {
		return GATED_BLOCKS.indexOf( name ) !== -1;
	}

	wp.hooks.addFilter(
		'blocks.registerBlockType',
		'consent-gate/attribute',
		function ( settings, name ) {
			if ( ! isGatedBlock( name ) ) {
				return settings;
			}
			settings.attributes = Object.assign( {}, settings.attributes, {
				consentGate: { type: 'string', default: '' },
				// Owner-supplied poster (§5.4): the ID is what the server
				// renders from; the URL exists only for the inspector preview.
				consentGatePoster: { type: 'number', default: 0 },
				consentGatePosterUrl: { type: 'string', default: '' }
			} );
			return settings;
		}
	);

	wp.hooks.addFilter(
		'editor.BlockEdit',
		'consent-gate/inspector',
		wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( ! isGatedBlock( props.name ) ) {
					return el( BlockEdit, props );
				}
				var value = props.attributes.consentGate || '';
				var posterUrl = props.attributes.consentGatePosterUrl || '';

				// Poster picker (§5.4, owner-supplied variant): images come
				// from the media library so the placeholder stays site-origin.
				var posterControls = el(
					wp.blockEditor.MediaUploadCheck,
					null,
					el( wp.blockEditor.MediaUpload, {
						allowedTypes: [ 'image' ],
						value: props.attributes.consentGatePoster || 0,
						onSelect: function ( media ) {
							var large = media && media.sizes && media.sizes.large ? media.sizes.large.url : '';
							props.setAttributes( {
								consentGatePoster: media && media.id ? media.id : 0,
								consentGatePosterUrl: large || ( media && media.url ? media.url : '' )
							} );
						},
						render: function ( obj ) {
							return el(
								'div',
								{ className: 'cg-poster-control' },
								posterUrl ? el( 'img', {
									className: 'cg-poster-control__preview',
									src: posterUrl,
									alt: ''
								} ) : null,
								el( wp.components.Button, {
									variant: 'secondary',
									onClick: obj.open
								}, posterUrl
									? __( 'Replace poster image', 'consent-gate' )
									: __( 'Set poster image', 'consent-gate' ) ),
								posterUrl ? el( wp.components.Button, {
									variant: 'link',
									isDestructive: true,
									onClick: function () {
										props.setAttributes( { consentGatePoster: 0, consentGatePosterUrl: '' } );
									}
								}, __( 'Remove poster image', 'consent-gate' ) ) : null,
								el(
									'p',
									{ className: 'cg-poster-control__help' },
									__( 'Shown behind the consent panel until the visitor loads the embed. The image is served from your own media library, never fetched from the provider.', 'consent-gate' )
								)
							);
						}
					} )
				);

				return el(
					wp.element.Fragment,
					null,
					el( BlockEdit, props ),
					el(
						wp.blockEditor.InspectorControls,
						null,
						el(
							wp.components.PanelBody,
							{ title: __( 'Consent Gate', 'consent-gate' ), initialOpen: false },
							el( wp.components.SelectControl, {
								label: __( 'Gate this embed', 'consent-gate' ),
								value: value,
								options: [
									{ value: '', label: __( 'Site default', 'consent-gate' ) },
									{ value: 'always', label: __( 'Always gate', 'consent-gate' ) },
									{ value: 'never', label: __( 'Never gate', 'consent-gate' ) }
								],
								onChange: function ( next ) {
									props.setAttributes( { consentGate: next } );
								},
								help: value === 'never'
									? __( 'This block’s embeds will load immediately for every visitor, without a consent click.', 'consent-gate' )
									: __( 'Overrides the site-wide setting for this block only.', 'consent-gate' )
							} ),
							value === 'never' ? null : posterControls
						)
					)
				);
			};
		}, 'withConsentGateInspector' )
	);

	// Editor-canvas badge (§7.5): the override is otherwise invisible, and
	// an editor who set "never" months ago deserves to see it at a glance.
	wp.hooks.addFilter(
		'editor.BlockListBlock',
		'consent-gate/badge',
		wp.compose.createHigherOrderComponent( function ( BlockListBlock ) {
			return function ( props ) {
				if ( ! isGatedBlock( props.name ) || ! props.attributes.consentGate ) {
					return el( BlockListBlock, props );
				}
				var wrapperProps = Object.assign( {}, props.wrapperProps, {
					'data-consent-gate': props.attributes.consentGate
				} );
				return el( BlockListBlock, Object.assign( {}, props, { wrapperProps: wrapperProps } ) );
			};
		}, 'withConsentGateBadge' )
	);

	// The withdrawal control as a block (§6.2): same server-side renderer as
	// the [consent_gate_withdraw] shortcode.
	wp.blocks.registerBlockType( 'consent-gate/withdraw', {
		title: __( 'Withdraw embed consents', 'consent-gate' ),
		icon: 'unlock',
		category: 'widgets',
		description: __( 'A button visitors use to clear their stored embed consents. Place it on your privacy policy page. It only has an effect when consent memory is enabled.', 'consent-gate' ),
		attributes: {
			label: { type: 'string', default: '' }
		},
		edit: function ( props ) {
			return el(
				'div',
				{ className: 'cg-withdraw-editor-preview' },
				el(
					'button',
					{ type: 'button', className: 'cg-withdraw', disabled: true },
					props.attributes.label || __( 'Withdraw embed consents', 'consent-gate' )
				),
				el(
					wp.blockEditor.InspectorControls,
					null,
					el(
						wp.components.PanelBody,
						{ title: __( 'Consent Gate', 'consent-gate' ) },
						el( wp.components.TextControl, {
							label: __( 'Button label', 'consent-gate' ),
							value: props.attributes.label,
							onChange: function ( next ) {
								props.setAttributes( { label: next } );
							}
						} )
					)
				)
			);
		},
		save: function () {
			return null; // Dynamic block: rendered server-side (invariant 2).
		}
	} );
}( window.wp ) );
