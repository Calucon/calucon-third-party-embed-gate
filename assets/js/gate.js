/**
 * Consent Gate front end.
 *
 * Dependency-free, ES5-compatible by design (PLAN.md §11): must run before
 * any framework and on old browsers. Does nothing until the visitor clicks —
 * the placeholder itself is server-rendered (invariant 2) and this script
 * stores nothing, ever (invariant 3).
 */
( function () {
	'use strict';

	// Mirror of the server-side safelist (PLAN.md §5.2). Never style, never
	// srcdoc, never on* — and autoplay never survives the rebuild (invariant 8).
	var SAFELIST = [ 'title', 'width', 'height', 'sandbox', 'loading', 'allow', 'allowfullscreen', 'referrerpolicy' ];

	function hasClass( el, name ) {
		return el && el.nodeType === 1 && ( ' ' + el.className + ' ' ).indexOf( ' ' + name + ' ' ) !== -1;
	}

	function closestByClass( el, name ) {
		while ( el && el !== document ) {
			if ( hasClass( el, name ) ) {
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	function stripAutoplay( allow ) {
		var parts = String( allow ).split( ';' );
		var kept = [];
		for ( var i = 0; i < parts.length; i++ ) {
			var feature = parts[ i ].replace( /^\s+|\s+$/g, '' );
			if ( feature && feature.toLowerCase().indexOf( 'autoplay' ) !== 0 ) {
				kept.push( feature );
			}
		}
		return kept.join( '; ' );
	}

	function buildFrame( payload ) {
		var src = typeof payload.src === 'string' ? payload.src : '';
		// Only http(s) or protocol-relative URLs may be loaded. Anything else
		// in the payload is treated as hostile and ignored.
		if ( ! /^(https?:)?\/\//i.test( src ) ) {
			return null;
		}

		var frame = document.createElement( 'iframe' );
		var attrs = payload.attrs || {};
		for ( var i = 0; i < SAFELIST.length; i++ ) {
			var name = SAFELIST[ i ];
			if ( ! Object.prototype.hasOwnProperty.call( attrs, name ) ) {
				continue;
			}
			var value = attrs[ name ];
			if ( name === 'allowfullscreen' ) {
				if ( value ) {
					frame.setAttribute( 'allowfullscreen', '' );
				}
				continue;
			}
			if ( name === 'allow' ) {
				value = stripAutoplay( value );
				if ( ! value ) {
					continue;
				}
			}
			frame.setAttribute( name, String( value ) );
		}
		frame.setAttribute( 'src', src );
		return frame;
	}

	function activate( container ) {
		if ( container.getAttribute( 'data-cg-activated' ) === '1' ) {
			return;
		}

		var payload;
		try {
			payload = JSON.parse( container.getAttribute( 'data-cg-payload' ) || '' );
		} catch ( e ) {
			return; // Malformed payload: the fallback link still works.
		}

		var frame = buildFrame( payload );
		if ( ! frame ) {
			return;
		}

		container.setAttribute( 'data-cg-activated', '1' );
		container.className += ' cg-embed--active';

		var panel = container.getElementsByTagName( 'div' )[ 0 ];
		if ( panel && hasClass( panel, 'cg-embed__panel' ) ) {
			container.removeChild( panel );
		}
		container.appendChild( frame );

		// Focus the container, not the inserted node: if a provider script
		// later replaces the node, focus would silently fall back to <body>
		// and throw the keyboard user to the top of the page (PLAN.md §8).
		container.setAttribute( 'tabindex', '-1' );
		container.focus();
	}

	document.addEventListener( 'click', function ( event ) {
		var button = closestByClass( event.target, 'cg-embed__button' );
		if ( ! button ) {
			return;
		}
		var container = closestByClass( button, 'cg-embed' );
		if ( container ) {
			event.preventDefault();
			activate( container );
		}
	}, false );
}() );
