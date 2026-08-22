/**
 * Settings-screen Appearance controls: WordPress colour pickers, a live
 * preview of the placeholder panel, and a plain-language readability
 * (contrast) report so owners who cannot write CSS still end up with a
 * WCAG-readable panel.
 *
 * Admin-only. Unlike gate.js this file may depend on jQuery and
 * wp-color-picker — both ship with WordPress; nothing here is remote and
 * nothing here runs on the front end.
 */
( function ( $ ) {
	'use strict';

	// Option key -> the §7.3 custom property it overrides.
	var VARS = {
		bg: '--cg-bg',
		fg: '--cg-fg',
		accent: '--cg-accent',
		accent_fg: '--cg-accent-fg'
	};

	// Mirrors Plugin::appearance_css() — change corner values in both places.
	var RADII = {
		square: '0',
		rounded: '12px',
		pill: '12px'
	};

	$( function () {
		var stage = document.getElementById( 'cg-preview-stage' );
		var sample = stage ? stage.querySelector( '.cg-embed' ) : null;
		var report = document.getElementById( 'cg-contrast-report' );
		var i18n = window.caluconEmbedGateAdminI18n || {};
		var button = sample ? sample.querySelector( '.cg-embed__button' ) : null;
		var note = sample ? sample.querySelector( '.cg-embed__note' ) : null;
		var link = sample ? sample.querySelector( '.cg-embed__fallback a' ) : null;
		var panel = sample ? sample.querySelector( '.cg-embed__panel' ) : null;
		var withdraw = document.getElementById( 'cg-preview-withdraw' );

		// One palette store feeds the preview: the base colours, overlaid by
		// the dark set when both the dark option and the dark-preview toggle
		// are on — mirroring the @media (prefers-color-scheme: dark) emission.
		var palette = { base: {}, dark: {} };

		function darkPreviewActive() {
			var previewToggle = document.getElementById( 'cg-preview-dark' );
			var darkEnabled = document.getElementById( 'cg-dark-enabled' );
			return !! ( previewToggle && previewToggle.checked && darkEnabled && darkEnabled.checked );
		}

		function applyPalette() {
			if ( ! sample ) {
				return;
			}
			var dark = darkPreviewActive();
			for ( var key in VARS ) {
				if ( ! Object.prototype.hasOwnProperty.call( VARS, key ) ) {
					continue;
				}
				var value = ( dark && palette.dark[ key ] ) || palette.base[ key ] || '';
				if ( value ) {
					sample.style.setProperty( VARS[ key ], value );
					if ( withdraw ) {
						withdraw.style.setProperty( VARS[ key ], value );
					}
				} else {
					sample.style.removeProperty( VARS[ key ] );
					if ( withdraw ) {
						withdraw.style.removeProperty( VARS[ key ] );
					}
				}
			}
			refresh();
		}

		function setColor( key, value ) {
			if ( 'border_color' === key ) {
				applyBorder();
				return;
			}
			if ( 0 === key.indexOf( 'dark_' ) ) {
				palette.dark[ key.slice( 5 ) ] = value;
			} else {
				palette.base[ key ] = value;
			}
			applyPalette();
		}

		function applyPreset( preset ) {
			if ( ! stage ) {
				return;
			}
			stage.className = stage.className.replace( /\s*cg-preview--(?:minimal|card)/g, '' );
			if ( 'minimal' === preset || 'card' === preset ) {
				stage.className += ' cg-preview--' + preset;
			}
			refresh();
		}

		function applyCorners( corners ) {
			if ( ! sample ) {
				return;
			}
			var radiusRow = document.getElementById( 'cg-radius-row' );
			var radiusInput = document.getElementById( 'cg-radius' );
			if ( radiusRow ) {
				radiusRow.hidden = 'custom' !== corners;
			}
			// Inline styles beat the preview's preset class rules, matching
			// the front end where the corner CSS is emitted after the preset.
			var radius = RADII[ corners ] || null;
			if ( 'custom' === corners && radiusInput ) {
				radius = ( parseInt( radiusInput.value, 10 ) || 0 ) + 'px';
			}
			if ( null !== radius ) {
				sample.style.setProperty( '--cg-radius', radius );
				sample.style.borderRadius = radius;
				if ( withdraw ) {
					withdraw.style.setProperty( '--cg-radius', radius );
				}
			} else {
				sample.style.removeProperty( '--cg-radius' );
				sample.style.borderRadius = '';
				if ( withdraw ) {
					withdraw.style.removeProperty( '--cg-radius' );
				}
			}
			if ( button ) {
				button.style.borderRadius = 'pill' === corners ? '999px' : '';
			}
			refresh();
		}

		// Mirrors AppearanceCss::build() — border/shadow/spacing values live
		// in both places; change them together.
		function applyBorder() {
			if ( ! sample ) {
				return;
			}
			var widthInput = document.getElementById( 'cg-border-width' );
			var colorInput = document.getElementById( 'cg-color-border-color' );
			var width = widthInput ? widthInput.value : '';
			var color = colorInput && colorInput.value ? colorInput.value : '';
			if ( '' === width ) {
				sample.style.border = '';
				sample.style.borderColor = color;
			} else if ( 0 === ( parseInt( width, 10 ) || 0 ) ) {
				sample.style.border = 'none';
			} else {
				sample.style.border = ( parseInt( width, 10 ) || 0 ) + 'px solid '
					+ ( color || 'var( --cg-fg )' );
			}
			refresh();
		}

		var SHADOWS = {
			none: 'none',
			soft: '0 1px 4px rgba(0,0,0,0.18)',
			strong: '0 6px 24px rgba(0,0,0,0.35)'
		};
		function applyShadow( shadow ) {
			if ( sample ) {
				sample.style.boxShadow = SHADOWS[ shadow ] || '';
				refresh();
			}
		}

		// Mirrors AppearanceCss::build() — sizes live in both places.
		var SIZES = {
			small: { fontSize: '0.875em', padding: '0.375em 0.75em' },
			large: { fontSize: '1.125em', padding: '0.625em 1.25em' }
		};
		function applyButtonSize( size ) {
			var config = SIZES[ size ] || { fontSize: '', padding: '' };
			var targets = [ button, withdraw ];
			for ( var i = 0; i < targets.length; i++ ) {
				if ( targets[ i ] ) {
					targets[ i ].style.fontSize = config.fontSize;
					targets[ i ].style.padding = config.padding;
				}
			}
			refresh();
		}

		function applyPlayIcon( on ) {
			if ( stage ) {
				stage.classList.toggle( 'cg-preview--icon', !! on );
			}
		}

		function applyNoteSize( size ) {
			if ( note ) {
				note.style.fontSize = 'small' === size ? '0.875em' : '';
				refresh();
			}
		}

		function applyAlign( align ) {
			if ( panel ) {
				panel.style.alignItems = 'center' === align ? 'center' : '';
				panel.style.textAlign = 'center' === align ? 'center' : '';
			}
		}

		function applyWithdrawStyle( style ) {
			if ( withdraw ) {
				withdraw.className = 'cg-withdraw' + ( 'outline' === style || 'link' === style ? ' cg-withdraw--' + style : '' );
				refresh();
			}
		}

		function applyButtonStyle( style ) {
			if ( ! button ) {
				return;
			}
			if ( 'outline' === style ) {
				button.style.background = 'transparent';
				button.style.color = 'var( --cg-fg )';
				button.style.borderColor = 'var( --cg-accent )';
			} else {
				button.style.background = '';
				button.style.color = '';
				button.style.borderColor = '';
			}
			refresh();
		}

		function applyButtonWidth( width ) {
			if ( button ) {
				button.style.width = 'full' === width ? '100%' : '';
			}
		}

		// Hover is a state, not a property: mirrored by stage classes whose
		// rules live in admin-appearance.css (same values as AppearanceCss).
		function applyHover( hover ) {
			if ( ! stage ) {
				return;
			}
			stage.classList.remove( 'cg-preview--hover-none', 'cg-preview--hover-strong' );
			if ( 'none' === hover || 'strong' === hover ) {
				stage.classList.add( 'cg-preview--hover-' + hover );
			}
		}

		// Poster preview: a bundled gradient stands in for the owner's image
		// (a data: URI — nothing is fetched), so the placement option can be
		// seen without uploading anything.
		var POSTER_SRC = 'data:image/svg+xml,' + encodeURIComponent(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 9"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6b8e9f"/><stop offset="1" stop-color="#2d3e4f"/></linearGradient></defs><rect width="16" height="9" fill="url(#g)"/></svg>'
		);
		function applyPosterPreview( on ) {
			if ( ! sample ) {
				return;
			}
			var existing = sample.querySelector( '.cg-embed__poster' );
			if ( on && ! existing ) {
				var img = document.createElement( 'img' );
				img.className = 'cg-embed__poster';
				img.setAttribute( 'alt', '' );
				img.setAttribute( 'aria-hidden', 'true' );
				img.src = POSTER_SRC;
				sample.insertBefore( img, sample.firstChild );
				sample.classList.add( 'cg-embed--poster' );
			} else if ( ! on && existing ) {
				existing.parentNode.removeChild( existing );
				sample.classList.remove( 'cg-embed--poster' );
			}
			applyPosterPanel( ( document.getElementById( 'cg-poster-panel' ) || { value: '' } ).value );
		}

		// Mirrors AppearanceCss::build() poster placements.
		function applyPosterPanel( placement ) {
			if ( ! panel ) {
				return;
			}
			var hasPoster = sample && sample.classList.contains( 'cg-embed--poster' );
			panel.style.alignSelf = '';
			panel.style.justifySelf = '';
			panel.style.margin = '';
			panel.style.maxWidth = '';
			panel.style.borderRadius = '';
			if ( ! hasPoster ) {
				refresh();
				return;
			}
			if ( 'center' === placement ) {
				panel.style.alignSelf = 'center';
				panel.style.justifySelf = 'center';
			} else if ( 'bar' === placement ) {
				panel.style.alignSelf = 'end';
				panel.style.justifySelf = 'stretch';
				panel.style.margin = '0';
				panel.style.maxWidth = 'none';
				panel.style.borderRadius = '0 0 var( --cg-radius ) var( --cg-radius )';
			}
			refresh();
		}

		var GAPS = { compact: '0.5rem', spacious: '1.25rem' };
		function applyDensity( density ) {
			if ( ! sample ) {
				return;
			}
			if ( GAPS[ density ] ) {
				sample.style.setProperty( '--cg-gap', GAPS[ density ] );
			} else {
				sample.style.removeProperty( '--cg-gap' );
			}
			refresh();
		}

		// --- Contrast (WCAG 2.x relative luminance), computed from what the
		// --- preview actually renders, so theme-inherited values count too.

		function parseColor( value ) {
			var m = /rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+%?))?\s*\)/.exec( value || '' );
			if ( ! m ) {
				return null;
			}
			var alpha = 1;
			if ( undefined !== m[ 4 ] ) {
				alpha = parseFloat( m[ 4 ] );
				if ( /%$/.test( m[ 4 ] ) ) {
					alpha /= 100;
				}
			}
			return { r: +m[ 1 ], g: +m[ 2 ], b: +m[ 3 ], a: alpha };
		}

		// Composite a (possibly translucent) colour over an opaque backdrop.
		function over( top, backdrop ) {
			return {
				r: top.r * top.a + backdrop.r * ( 1 - top.a ),
				g: top.g * top.a + backdrop.g * ( 1 - top.a ),
				b: top.b * top.a + backdrop.b * ( 1 - top.a ),
				a: 1
			};
		}

		// The backdrop an element's text really sits on: walk up through
		// transparent ancestors, compositing translucent layers.
		function effectiveBackground( el ) {
			var layers = [];
			while ( el && 1 === el.nodeType ) {
				var parsed = parseColor( getComputedStyle( el ).backgroundColor );
				if ( parsed && parsed.a > 0 ) {
					layers.push( parsed );
					if ( 1 === parsed.a ) {
						break;
					}
				}
				el = el.parentNode;
			}
			var result = { r: 255, g: 255, b: 255, a: 1 };
			for ( var i = layers.length - 1; i >= 0; i-- ) {
				result = over( layers[ i ], result );
			}
			return result;
		}

		function luminance( c ) {
			var f = function ( v ) {
				v /= 255;
				return v <= 0.04045 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
			};
			return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b );
		}

		function ratio( fg, bg ) {
			var l1 = luminance( fg );
			var l2 = luminance( bg );
			var hi = Math.max( l1, l2 );
			var lo = Math.min( l1, l2 );
			return ( hi + 0.05 ) / ( lo + 0.05 );
		}

		function pairs() {
			var out = [];
			if ( note ) {
				out.push( { label: i18n.panelText, el: note } );
			}
			if ( button ) {
				out.push( { label: i18n.buttonText, el: button } );
			}
			if ( link ) {
				out.push( { label: i18n.linkText, el: link } );
			}
			if ( withdraw ) {
				out.push( { label: i18n.withdrawText, el: withdraw } );
			}
			return out;
		}

		function refresh() {
			if ( ! report || ! sample ) {
				return;
			}
			var lines = [];
			var template = i18n.line || '%1$s: %2$s — %3$s';
			pairs().forEach( function ( pair ) {
				var fg = parseColor( getComputedStyle( pair.el ).color );
				var bg = effectiveBackground( pair.el );
				if ( ! fg || ! bg ) {
					return;
				}
				if ( fg.a < 1 ) {
					fg = over( fg, bg );
				}
				var r = ratio( fg, bg );
				var verdict = r >= 4.5 ? i18n.pass : i18n.fail;
				lines.push(
					template
						.replace( '%1$s', pair.label || '' )
						.replace( '%2$s', r.toFixed( 1 ) + ':1' )
						.replace( '%3$s', verdict || '' )
				);
			} );
			report.textContent = lines.join( '\n' );
		}

		// --- Wiring ---

		$( '.cg-color-field' ).each( function () {
			var key = this.getAttribute( 'data-cg-color' );
			$( this ).wpColorPicker( {
				change: function ( event, ui ) {
					setColor( key, ui.color.toString() );
				},
				clear: function () {
					setColor( key, '' );
				}
			} );
		} );

		if ( ! stage || ! sample ) {
			return;
		}

		// The preview is a picture of the panel, not a working embed: its
		// fallback link must never navigate the owner away from the settings.
		stage.addEventListener( 'click', function ( event ) {
			event.preventDefault();
		} );

		$( '#cg-preset' ).on( 'change', function () {
			applyPreset( this.value );
		} );
		$( '#cg-corners' ).on( 'change', function () {
			applyCorners( this.value );
		} );
		$( '#cg-radius' ).on( 'input change', function () {
			applyCorners( ( document.getElementById( 'cg-corners' ) || { value: '' } ).value );
		} );
		$( '#cg-border-width' ).on( 'input change', applyBorder );
		$( '#cg-shadow' ).on( 'change', function () {
			applyShadow( this.value );
		} );
		$( '#cg-density' ).on( 'change', function () {
			applyDensity( this.value );
		} );
		$( '#cg-button-size' ).on( 'change', function () {
			applyButtonSize( this.value );
		} );
		$( '#cg-play-icon' ).on( 'change', function () {
			applyPlayIcon( this.checked );
		} );
		$( '#cg-note-size' ).on( 'change', function () {
			applyNoteSize( this.value );
		} );
		$( '#cg-align' ).on( 'change', function () {
			applyAlign( this.value );
		} );
		$( '#cg-withdraw-style' ).on( 'change', function () {
			applyWithdrawStyle( this.value );
		} );
		$( '#cg-button-style' ).on( 'change', function () {
			applyButtonStyle( this.value );
		} );
		$( '#cg-button-width' ).on( 'change', function () {
			applyButtonWidth( this.value );
		} );
		$( '#cg-hover' ).on( 'change', function () {
			applyHover( this.value );
		} );
		$( '#cg-poster-panel' ).on( 'change', function () {
			applyPosterPanel( this.value );
		} );
		$( '#cg-preview-poster' ).on( 'change', function () {
			applyPosterPreview( this.checked );
		} );
		$( '#cg-appearance-reset' ).on( 'click', function () {
			// Back to "inherit everything": selects to their first option,
			// numbers to their defaults, checkboxes off, every colour cleared
			// through the picker's own Clear so its swatch resets too.
			$( '#cg-tab-appearance select' ).each( function () {
				this.selectedIndex = 0;
			} );
			$( '#cg-radius' ).val( '12' );
			$( '#cg-border-width' ).val( '' );
			$( '#cg-play-icon, #cg-dark-enabled' ).prop( 'checked', false );
			$( '#cg-tab-appearance .wp-picker-clear' ).each( function () {
				this.click();
			} );
			$( '.cg-dark-row' ).prop( 'hidden', true );
			palette = { base: {}, dark: {} };
			syncFromForm();
		} );
		$( '#cg-dark-enabled' ).on( 'change', function () {
			var rows = document.querySelectorAll( '.cg-dark-row' );
			for ( var i = 0; i < rows.length; i++ ) {
				rows[ i ].hidden = ! this.checked;
			}
			applyPalette();
		} );
		$( '#cg-preview-dark' ).on( 'change', function () {
			stage.classList.toggle( 'cg-preview-stage--dark', this.checked );
			applyPalette();
		} );

		// Recompute when admin-tabs.js reveals a panel: computed styles of a
		// display:none subtree can be unreliable across engines.
		document.addEventListener( 'cg-tab-shown', function () {
			refresh();
		} );

		// Mirror whatever the form currently holds — saved, half-edited or
		// just reset — so the preview and the controls can never disagree.
		function syncFromForm() {
		$( '.cg-color-field' ).each( function () {
			var key = this.getAttribute( 'data-cg-color' );
			if ( ! this.value || 'border_color' === key ) {
				return;
			}
			if ( 0 === key.indexOf( 'dark_' ) ) {
				palette.dark[ key.slice( 5 ) ] = this.value;
			} else if ( VARS[ key ] ) {
				palette.base[ key ] = this.value;
			}
		} );
		applyPalette();
		applyCorners( ( document.getElementById( 'cg-corners' ) || { value: '' } ).value );
		applyBorder();
		applyShadow( ( document.getElementById( 'cg-shadow' ) || { value: '' } ).value );
		applyDensity( ( document.getElementById( 'cg-density' ) || { value: '' } ).value );
		applyButtonSize( ( document.getElementById( 'cg-button-size' ) || { value: '' } ).value );
		applyPlayIcon( ( document.getElementById( 'cg-play-icon' ) || { checked: false } ).checked );
		applyNoteSize( ( document.getElementById( 'cg-note-size' ) || { value: '' } ).value );
		applyAlign( ( document.getElementById( 'cg-align' ) || { value: '' } ).value );
		applyWithdrawStyle( ( document.getElementById( 'cg-withdraw-style' ) || { value: '' } ).value );
		applyButtonStyle( ( document.getElementById( 'cg-button-style' ) || { value: '' } ).value );
		applyButtonWidth( ( document.getElementById( 'cg-button-width' ) || { value: '' } ).value );
		applyHover( ( document.getElementById( 'cg-hover' ) || { value: '' } ).value );
		applyPosterPanel( ( document.getElementById( 'cg-poster-panel' ) || { value: '' } ).value );
		applyPreset( ( document.getElementById( 'cg-preset' ) || { value: '' } ).value );
		}
		syncFromForm();
	} );
}( window.jQuery ) );
