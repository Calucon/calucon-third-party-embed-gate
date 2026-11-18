/**
 * Calucon Third-Party Embed Gate block editor integration (PLAN.md §7.5).
 *
 * Dependency-free of any build step: plain JS against the wp.* globals.
 * Two jobs: the per-block "Gate this embed" override on blocks that carry
 * embeds, and the withdrawal-control block.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.blocks || ! wp.i18n
		|| ! wp.compose || ! wp.blockEditor || ! wp.components ) {
		return;
	}

	var __ = wp.i18n.__;
	var el = wp.element.createElement;

	// Blocks that can carry third-party embeds: core/embed, and core/html
	// because pasted iframe markup lives there. Not core/video or
	// core/audio, which were in this list until 1.0.2: they render a <video>
	// or <audio> element, which no detection rule reads, so the panel there
	// offered "Always gate" and a poster for something that is never gated.
	var GATED_BLOCKS = [ 'core/embed', 'core/html' ];

	function isGatedBlock( name ) {
		return GATED_BLOCKS.indexOf( name ) !== -1;
	}

	wp.hooks.addFilter(
		'blocks.registerBlockType',
		'calucon-embed-gate/attribute',
		function ( settings, name ) {
			if ( ! isGatedBlock( name ) ) {
				return settings;
			}
			settings.attributes = Object.assign( {}, settings.attributes, {
				caluconEmbedGate: { type: 'string', default: '' },
				// Owner-supplied poster (§5.4): the ID is what the server
				// renders from; the URL exists only for the inspector preview.
				caluconEmbedGatePoster: { type: 'number', default: 0 },
				caluconEmbedGatePosterUrl: { type: 'string', default: '' },
				caluconEmbedGateAction: { type: 'string', default: '' },
				caluconEmbedGateNote: { type: 'string', default: '' },
				// An alternative without the provider (1.1): an address on
				// this site and its link text. The server shows the link only
				// when the address really is on this site.
				caluconEmbedGateAltUrl: { type: 'string', default: '' },
				caluconEmbedGateAltText: { type: 'string', default: '' }
			} );
			return settings;
		}
	);

	wp.hooks.addFilter(
		'editor.BlockEdit',
		'calucon-embed-gate/inspector',
		wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( ! isGatedBlock( props.name ) ) {
					return el( BlockEdit, props );
				}
				var value = props.attributes.caluconEmbedGate || '';
				var posterUrl = props.attributes.caluconEmbedGatePosterUrl || '';

				// Poster picker (§5.4, owner-supplied variant): images come
				// from the media library so the placeholder stays site-origin.
				var posterControls = el(
					wp.blockEditor.MediaUploadCheck,
					null,
					el( wp.blockEditor.MediaUpload, {
						allowedTypes: [ 'image' ],
						value: props.attributes.caluconEmbedGatePoster || 0,
						onSelect: function ( media ) {
							var large = media && media.sizes && media.sizes.large ? media.sizes.large.url : '';
							props.setAttributes( {
								caluconEmbedGatePoster: media && media.id ? media.id : 0,
								caluconEmbedGatePosterUrl: large || ( media && media.url ? media.url : '' )
							} );
						},
						render: function ( obj ) {
							return el(
								'div',
								{ className: 'cg-poster-control', style: { marginBottom: '16px' } },
								posterUrl ? el( 'img', {
									className: 'cg-poster-control__preview',
									src: posterUrl,
									alt: ''
								} ) : null,
								el( wp.components.Button, {
									variant: 'secondary',
									onClick: obj.open
								}, posterUrl
									? __( 'Replace poster image', 'calucon-third-party-embed-gate' )
									: __( 'Set poster image', 'calucon-third-party-embed-gate' ) ),
								posterUrl ? el( wp.components.Button, {
									variant: 'link',
									isDestructive: true,
									onClick: function () {
										props.setAttributes( { caluconEmbedGatePoster: 0, caluconEmbedGatePosterUrl: '' } );
									}
								}, __( 'Remove poster image', 'calucon-third-party-embed-gate' ) ) : null,
								el(
									'p',
									{ className: 'cg-poster-control__help' },
									__( 'Shown behind the consent panel until the visitor loads the embed. The image is served from your own media library, never fetched from the provider.', 'calucon-third-party-embed-gate' )
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
							{ title: __( 'Calucon Third-Party Embed Gate', 'calucon-third-party-embed-gate' ), initialOpen: false },
							el( wp.components.SelectControl, {
								label: __( 'Gate this embed', 'calucon-third-party-embed-gate' ),
								value: value,
								options: [
									{ value: '', label: __( 'Site default', 'calucon-third-party-embed-gate' ) },
									{ value: 'always', label: __( 'Always gate', 'calucon-third-party-embed-gate' ) },
									{ value: 'never', label: __( 'Never gate', 'calucon-third-party-embed-gate' ) }
								],
								onChange: function ( next ) {
									props.setAttributes( { caluconEmbedGate: next } );
								},
								help: value === 'never'
									? __( 'This block’s embeds will load immediately for every visitor, without a consent click.', 'calucon-third-party-embed-gate' )
									: __( 'Overrides the site-wide setting for this block only.', 'calucon-third-party-embed-gate' )
							} ),
							value === 'never' ? null : posterControls,
							value === 'never' ? null : el( wp.components.TextControl, {
								label: __( 'Button text for this embed', 'calucon-third-party-embed-gate' ),
								value: props.attributes.caluconEmbedGateAction || '',
								placeholder: __( 'Site default', 'calucon-third-party-embed-gate' ),
								onChange: function ( next ) {
									props.setAttributes( { caluconEmbedGateAction: next } );
								},
								help: __( 'Plain text, for example “Load the trailer”. Empty keeps the provider’s default.', 'calucon-third-party-embed-gate' )
							} ),
							value === 'never' ? null : el( wp.components.TextareaControl, {
								label: __( 'Notice text for this embed', 'calucon-third-party-embed-gate' ),
								value: props.attributes.caluconEmbedGateNote || '',
								placeholder: __( 'Site default', 'calucon-third-party-embed-gate' ),
								rows: 3,
								onChange: function ( next ) {
									props.setAttributes( { caluconEmbedGateNote: next } );
								},
								help: __( 'Replaces the notice above the button for this embed only. Keep it honest: the notice tells visitors what loading the content does.', 'calucon-third-party-embed-gate' )
							} ),
							value === 'never' ? null : el( wp.components.TextControl, {
								label: __( 'Alternative on this site (address)', 'calucon-third-party-embed-gate' ),
								type: 'url',
								value: props.attributes.caluconEmbedGateAltUrl || '',
								placeholder: '/transcripts/…',
								onChange: function ( next ) {
									props.setAttributes( { caluconEmbedGateAltUrl: next } );
								},
								help: __( 'A page, file or section of your own site that offers this content without the provider: a transcript, a download, a copy you host yourself. Shown as a link in the placeholder. An address on another site is ignored.', 'calucon-third-party-embed-gate' )
							} ),
							value === 'never' || ! props.attributes.caluconEmbedGateAltUrl ? null : el( wp.components.TextControl, {
								label: __( 'Link text for the alternative', 'calucon-third-party-embed-gate' ),
								value: props.attributes.caluconEmbedGateAltText || '',
								placeholder: __( 'View an alternative on this site', 'calucon-third-party-embed-gate' ),
								onChange: function ( next ) {
									props.setAttributes( { caluconEmbedGateAltText: next } );
								},
								help: __( 'Plain text, for example “Read the transcript”. Empty keeps the default.', 'calucon-third-party-embed-gate' )
							} )
						)
					)
				);
			};
		}, 'withCaluconEmbedGateInspector' )
	);

	// Editor-canvas badge (§7.5): the override is otherwise invisible, and
	// an editor who set "never" months ago deserves to see it at a glance.
	wp.hooks.addFilter(
		'editor.BlockListBlock',
		'calucon-embed-gate/badge',
		wp.compose.createHigherOrderComponent( function ( BlockListBlock ) {
			return function ( props ) {
				if ( ! isGatedBlock( props.name ) || ! props.attributes.caluconEmbedGate ) {
					return el( BlockListBlock, props );
				}
				var wrapperProps = Object.assign( {}, props.wrapperProps, {
					'data-calucon-embed-gate': props.attributes.caluconEmbedGate
				} );
				return el( BlockListBlock, Object.assign( {}, props, { wrapperProps: wrapperProps } ) );
			};
		}, 'withCaluconEmbedGateBadge' )
	);

	// The per-page switch (1.1): "Do not gate embeds on this page", in the
	// post's own settings panel. Stored as post meta (Admin\PageSwitch); the
	// server exposes it only to someone who may edit the post, and only for
	// post types whose meta travels over REST — where it does not, the panel
	// stays away and the classic meta box takes its place.
	//
	// Registered on DOM ready: the panel component belongs to the post
	// editor (wp.editor from 6.6, wp.editPost before), which this script
	// does not depend on — the same file also loads in the site and widget
	// editors, where there is no post and no such panel.
	function registerPageSwitch() {
		var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel )
			|| ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
		if ( ! Panel || ! wp.plugins || ! wp.data || ! wp.data.useSelect ) {
			return;
		}
		var KEY = '_calucon_embed_gate';
		wp.plugins.registerPlugin( 'calucon-embed-gate-page', {
			render: function () {
				var meta = wp.data.useSelect( function ( select ) {
					var editor = select( 'core/editor' );
					return editor && editor.getEditedPostAttribute ? editor.getEditedPostAttribute( 'meta' ) : undefined;
				}, [] );
				var dispatch = wp.data.useDispatch( 'core/editor' );
				if ( ! meta || ! Object.prototype.hasOwnProperty.call( meta, KEY ) || ! dispatch || ! dispatch.editPost ) {
					return null;
				}
				var off = 'never' === meta[ KEY ];
				return el(
					Panel,
					{
						name: 'calucon-embed-gate',
						title: __( 'Calucon Third-Party Embed Gate', 'calucon-third-party-embed-gate' ),
						className: 'cg-page-switch'
					},
					el( wp.components.ToggleControl, {
						label: __( 'Do not gate embeds on this page', 'calucon-third-party-embed-gate' ),
						checked: off,
						onChange: function ( next ) {
							var change = {};
							change[ KEY ] = next ? 'never' : '';
							dispatch.editPost( { meta: change } );
						},
						help: off
							? __( 'Every embed on this page loads as soon as the page opens — for every visitor, without a click. It applies to this page’s own address only: in lists, feeds and search results its embeds stay gated.', 'calucon-third-party-embed-gate' )
							: __( 'Embeds on this page ask the visitor first, like everywhere else on the site.', 'calucon-third-party-embed-gate' )
					} )
				);
			}
		} );
	}
	if ( wp.domReady ) {
		wp.domReady( registerPageSwitch );
	} else {
		registerPageSwitch();
	}

	// The withdrawal control as a block (§6.2): same server-side renderer as
	// the [calucon_embed_gate_withdraw] shortcode.
	var withdrawBlock = {
		title: __( 'Withdraw embed consents', 'calucon-third-party-embed-gate' ),
		icon: 'unlock',
		category: 'widgets',
		description: __( 'A button visitors use to clear their stored embed consents. Place it on your privacy policy page. It only has an effect when consent memory is enabled.', 'calucon-third-party-embed-gate' ),
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
					props.attributes.label || __( 'Withdraw embed consents', 'calucon-third-party-embed-gate' )
				),
				el(
					wp.blockEditor.InspectorControls,
					null,
					el(
						wp.components.PanelBody,
						{ title: __( 'Calucon Third-Party Embed Gate', 'calucon-third-party-embed-gate' ) },
						el( wp.components.TextControl, {
							label: __( 'Button label', 'calucon-third-party-embed-gate' ),
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
	};

	wp.blocks.registerBlockType( 'calucon-embed-gate/withdraw', withdrawBlock );
}( window.wp ) );
