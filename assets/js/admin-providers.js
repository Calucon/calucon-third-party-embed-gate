/**
 * Providers tab: filtering (admin only, dependency-free ES5).
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

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
