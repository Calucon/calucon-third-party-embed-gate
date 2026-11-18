/**
 * Providers tab: filtering and the poster-image picker (admin only, ES5,
 * no dependency but the media library WordPress loads on this screen).
 *
 * The list is grouped and collapsible in PHP, so it is usable without
 * JavaScript. This adds the shortcut for a long list: type a few letters
 * and every matching provider is shown, whichever group it is in.
 */
( function () {
	'use strict';

	function init() {
		var wrap = document.getElementById( 'cg-provider-filter-wrap' );
		var field = document.getElementById( 'cg-provider-filter' );
		var onlyChanged = document.getElementById( 'cg-provider-only-changed' );
		var list = document.getElementById( 'cg-providers' );
		var count = document.getElementById( 'cg-provider-filter-count' );
		if ( ! wrap || ! field || ! list ) {
			return;
		}
		wrap.hidden = false;

		var groups = list.querySelectorAll( '.cg-provider-group' );
		// Remember how the groups were rendered, so clearing the filter puts
		// the list back the way the owner found it.
		var wasOpen = [];
		for ( var g = 0; g < groups.length; g++ ) {
			wasOpen.push( groups[ g ].open );
		}

		function apply() {
			var term = field.value.replace( /^\s+|\s+$/g, '' ).toLowerCase();
			var changedOnly = !! ( onlyChanged && onlyChanged.checked );
			var filtering = '' !== term || changedOnly;
			var shown = 0;

			for ( var i = 0; i < groups.length; i++ ) {
				var rows = groups[ i ].querySelectorAll( '.cg-provider' );
				var visible = 0;
				for ( var j = 0; j < rows.length; j++ ) {
					var row = rows[ j ];
					var hay = row.getAttribute( 'data-cg-search' ) || '';
					var match = ( '' === term || hay.indexOf( term ) !== -1 )
						&& ( ! changedOnly || '1' === row.getAttribute( 'data-cg-changed' ) );
					row.hidden = ! match;
					if ( match ) {
						visible++;
					}
				}
				shown += visible;
				groups[ i ].hidden = filtering && 0 === visible;
				// While filtering, open what matched; otherwise restore.
				groups[ i ].open = filtering ? visible > 0 : wasOpen[ i ];
			}

			if ( count ) {
				count.textContent = filtering
					? ( window.caluconEmbedGateProvidersI18n && window.caluconEmbedGateProvidersI18n.matches
						? window.caluconEmbedGateProvidersI18n.matches.replace( '%d', String( shown ) )
						: shown + ' shown' )
					: '';
			}
		}

		field.addEventListener( 'input', apply );
		field.addEventListener( 'search', apply );
		if ( onlyChanged ) {
			onlyChanged.addEventListener( 'change', apply );
		}
		// A filter box that survives a stray Enter: submitting the whole
		// settings form from here would be a surprise.
		field.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				apply();
			}
		} );
	}

	// The per-provider poster image. The field is a plain attachment-ID
	// input that works without this script; with it (and the media library
	// WordPress loads on this screen) the ID is chosen by picking an image.
	function initPosters() {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var i18n = window.caluconEmbedGateProvidersI18n || {};
		var fields = document.querySelectorAll( '[data-cg-poster]' );

		function wire( field ) {
			var input = field.querySelector( '[data-cg-poster-id]' );
			var preview = field.querySelector( '[data-cg-poster-preview]' );
			var choose = field.querySelector( '[data-cg-poster-choose]' );
			var remove = field.querySelector( '[data-cg-poster-remove]' );
			if ( ! input || ! preview || ! choose || ! remove ) {
				return;
			}
			// The number stays in the form (it is what gets saved) but out of
			// sight: the picture says which image it is.
			input.hidden = true;
			choose.hidden = false;
			remove.hidden = '' === input.value || '0' === input.value;

			function changed() {
				// The settings form watches for input/change to know it is dirty.
				var event;
				try {
					event = new Event( 'change', { bubbles: true } );
				} catch ( e ) {
					event = document.createEvent( 'Event' );
					event.initEvent( 'change', true, false );
				}
				input.dispatchEvent( event );
			}

			choose.addEventListener( 'click', function () {
				var frame = window.wp.media( {
					title: i18n.posterTitle || 'Choose a poster image',
					button: { text: i18n.posterButton || 'Use this image' },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					input.value = String( image.id );
					preview.src = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
					preview.hidden = false;
					remove.hidden = false;
					changed();
				} );
				frame.open();
			} );

			remove.addEventListener( 'click', function () {
				input.value = '';
				preview.hidden = true;
				preview.removeAttribute( 'src' );
				remove.hidden = true;
				changed();
				choose.focus();
			} );
		}

		for ( var i = 0; i < fields.length; i++ ) {
			wire( fields[ i ] );
		}
	}

	function start() {
		init();
		initPosters();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
