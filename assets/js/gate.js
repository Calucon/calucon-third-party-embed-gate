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

	// Script load state per URL, so a provider SDK is fetched exactly once no
	// matter how many embeds it serves (PLAN.md §9.6).
	var scriptStates = {};

	// Invoked after a provider script loads AND after each later activation:
	// SDKs like Strava's embed.js only render the placeholders present when
	// they run (PLAN.md §9.6). Sites can add hooks for custom providers via
	// window.consentGateReadyHooks before or after this script loads.
	var readyHooks = {
		strava: function () {
			if ( window.__STRAVA_EMBED_BOOTSTRAP__ ) {
				window.__STRAVA_EMBED_BOOTSTRAP__();
			}
		},
		twitter: function () {
			if ( window.twttr && window.twttr.widgets && window.twttr.widgets.load ) {
				window.twttr.widgets.load();
			}
		},
		instagram: function () {
			if ( window.instgrm && window.instgrm.Embeds ) {
				window.instgrm.Embeds.process();
			}
		},
		facebook: function () {
			if ( window.FB && window.FB.XFBML ) {
				window.FB.XFBML.parse();
			}
		}
	};

	function runReadyHook( providerId ) {
		var custom = window.consentGateReadyHooks || {};
		var hook = custom[ providerId ] || readyHooks[ providerId ];
		if ( hook ) {
			try {
				hook();
			} catch ( e ) {
				// A broken provider hook must not break the page.
			}
		}
	}

	function loadScriptOnce( src, done ) {
		var state = scriptStates[ src ];
		if ( state && state.loaded ) {
			done();
			return;
		}
		if ( state ) {
			state.callbacks.push( done );
			return;
		}
		state = scriptStates[ src ] = { loaded: false, callbacks: [ done ] };
		var el = document.createElement( 'script' );
		el.async = true;
		el.src = src;
		el.onload = function () {
			state.loaded = true;
			var callbacks = state.callbacks;
			state.callbacks = [];
			for ( var i = 0; i < callbacks.length; i++ ) {
				callbacks[ i ]();
			}
		};
		document.head.appendChild( el );
	}

	function removePanel( container ) {
		container.setAttribute( 'data-cg-activated', '1' );
		container.className += ' cg-embed--active';
		var panel = container.getElementsByTagName( 'div' )[ 0 ];
		if ( panel && hasClass( panel, 'cg-embed__panel' ) ) {
			container.removeChild( panel );
		}
	}

	function activateScript( container, payload ) {
		var src = typeof payload.src === 'string' ? payload.src : '';
		if ( ! /^(https?:)?\/\//i.test( src ) ) {
			return;
		}
		var providerId = container.getAttribute( 'data-cg-provider' );

		// One SDK renders every companion element on the page, so the other
		// panels for the same provider would go stale — clear them all. The
		// clicked container stays in the DOM as the focus anchor (§8).
		var all = document.querySelectorAll
			? document.querySelectorAll( '.cg-embed[data-cg-provider="' + providerId + '"]' )
			: [];
		for ( var i = 0; i < all.length; i++ ) {
			if ( all[ i ] !== container && all[ i ].parentNode ) {
				all[ i ].parentNode.removeChild( all[ i ] );
			}
		}
		removePanel( container );
		container.setAttribute( 'tabindex', '-1' );
		container.focus();

		loadScriptOnce( src, function () {
			runReadyHook( providerId );
		} );
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

		if ( payload.strategy === 'script' ) {
			activateScript( container, payload );
			return;
		}

		var frame = buildFrame( payload );
		if ( ! frame ) {
			return;
		}

		removePanel( container );
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
