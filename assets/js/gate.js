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
	// on* — and autoplay never survives the rebuild (invariant 8). 'type'
	// only ever arrives in <embed>/<object> payloads, whose server safelist
	// is narrower still.
	var SAFELIST = [ 'title', 'width', 'height', 'sandbox', 'loading', 'allow', 'allowfullscreen', 'referrerpolicy', 'type' ];

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
		var srcdoc = typeof payload.srcdoc === 'string' ? payload.srcdoc : '';
		var tag = payload.tag === 'embed' || payload.tag === 'object' ? payload.tag : 'iframe';

		// Only http(s) or protocol-relative URLs may be loaded. Anything else
		// in the payload is treated as hostile and ignored. A srcdoc payload
		// carries the embed's original inline document instead of a URL —
		// restoring it verbatim is the same privilege the page already had.
		if ( ! srcdoc && ! /^(https?:)?\/\//i.test( src ) ) {
			return null;
		}

		var frame = document.createElement( tag );
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
		if ( srcdoc ) {
			frame.setAttribute( 'srcdoc', srcdoc );
		} else {
			// An <object> spells its target 'data'; iframe and embed use 'src'.
			frame.setAttribute( tag === 'object' ? 'data' : 'src', src );
		}
		return frame;
	}

	// Consent memory (PLAN.md §6.2): OFF unless the site enabled it. Nothing
	// is ever written before the first click (invariant 3) — page-load code
	// only READS storage. Client-side only (§6.3): server-side state would
	// make every page uncacheable.
	var STORAGE_KEY = 'consent-gate';

	function memoryConfig() {
		var config = window.consentGateConfig || {};
		var memory = config.memory === 'session' || config.memory === 'persistent' ? config.memory : 'off';
		return {
			memory: memory,
			scope: config.scope === 'embed' || config.scope === 'all' ? config.scope : 'provider',
			durationDays: typeof config.durationDays === 'number' && config.durationDays > 0 ? config.durationDays : 180,
			i18n: config.i18n || {}
		};
	}

	function memoryStore( config ) {
		try {
			return config.memory === 'session' ? window.sessionStorage : window.localStorage;
		} catch ( e ) {
			return null; // Storage blocked: memory silently degrades to off.
		}
	}

	function readGrants( config ) {
		var store = memoryStore( config );
		if ( ! store ) {
			return {};
		}
		var grants;
		try {
			grants = JSON.parse( store.getItem( STORAGE_KEY ) || '{}' ).g || {};
		} catch ( e ) {
			return {};
		}
		// Lazily expire persistent grants past their lifetime.
		var cutoff = config.memory === 'persistent' ? Date.now() - config.durationDays * 86400000 : 0;
		var live = {};
		for ( var key in grants ) {
			if ( Object.prototype.hasOwnProperty.call( grants, key ) && grants[ key ] >= cutoff ) {
				live[ key ] = grants[ key ];
			}
		}
		return live;
	}

	function writeGrants( config, grants ) {
		var store = memoryStore( config );
		if ( ! store ) {
			return;
		}
		try {
			store.setItem( STORAGE_KEY, JSON.stringify( { v: 1, g: grants } ) );
		} catch ( e ) {
			// Full or blocked storage: the click still works, just unremembered.
		}
	}

	// Grant keys carry no identifier — only what was consented to (§6.2):
	// the embed URL, the provider id, or everything.
	function grantKeys( config, container, payload ) {
		if ( config.scope === 'all' ) {
			return [ '*' ];
		}
		if ( config.scope === 'embed' ) {
			return [ 'e:' + String( payload.src || '' ) ];
		}
		return [ 'p:' + String( container.getAttribute( 'data-cg-provider' ) || '' ) ];
	}

	function rememberConsent( container, payload ) {
		var config = memoryConfig();
		if ( config.memory === 'off' ) {
			return;
		}
		var grants = readGrants( config );
		var keys = grantKeys( config, container, payload );
		for ( var i = 0; i < keys.length; i++ ) {
			grants[ keys[ i ] ] = Date.now();
		}
		writeGrants( config, grants );
	}

	function hasStoredConsent( container, payload ) {
		var config = memoryConfig();
		if ( config.memory === 'off' ) {
			return false;
		}
		var grants = readGrants( config );
		if ( Object.prototype.hasOwnProperty.call( grants, '*' ) ) {
			return true;
		}
		var keys = grantKeys( config, container, payload );
		for ( var i = 0; i < keys.length; i++ ) {
			if ( Object.prototype.hasOwnProperty.call( grants, keys[ i ] ) ) {
				return true;
			}
		}
		return false;
	}

	function withdrawConsent() {
		// Art. 7(3): withdrawal must be as easy as giving consent. Clear the
		// plugin's key from both storages; embeds are gated again from the
		// next page load.
		try {
			window.sessionStorage.removeItem( STORAGE_KEY );
		} catch ( e ) { /* Storage blocked: nothing was stored. */ }
		try {
			window.localStorage.removeItem( STORAGE_KEY );
		} catch ( e ) { /* Storage blocked: nothing was stored. */ }
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

	function activateScript( container, payload, focus ) {
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
		if ( focus ) {
			container.setAttribute( 'tabindex', '-1' );
			container.focus();
		}

		loadScriptOnce( src, function () {
			runReadyHook( providerId );
		} );
	}

	function activate( container, options ) {
		options = options || {};
		if ( container.getAttribute( 'data-cg-activated' ) === '1' ) {
			return;
		}

		var payload;
		try {
			payload = JSON.parse( container.getAttribute( 'data-cg-payload' ) || '' );
		} catch ( e ) {
			return; // Malformed payload: the fallback link still works.
		}

		// Storage is written AFTER the click, never before (invariant 3);
		// a memory-restored activation only reads and re-stores nothing.
		if ( options.remember ) {
			rememberConsent( container, payload );
		}

		if ( payload.strategy === 'script' ) {
			activateScript( container, payload, !! options.focus );
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
		if ( options.focus ) {
			container.setAttribute( 'tabindex', '-1' );
			container.focus();
		}
	}

	// With memory enabled (§6.2), re-activate what the visitor already
	// consented to. Read-only: no write happens on page load, and no focus
	// moves — there was no user gesture.
	function restoreFromMemory() {
		if ( memoryConfig().memory === 'off' || ! document.querySelectorAll ) {
			return;
		}
		var containers = document.querySelectorAll( '.cg-embed[data-cg-payload]' );
		for ( var i = 0; i < containers.length; i++ ) {
			var container = containers[ i ];
			var payload;
			try {
				payload = JSON.parse( container.getAttribute( 'data-cg-payload' ) || '' );
			} catch ( e ) {
				continue;
			}
			if ( hasStoredConsent( container, payload ) ) {
				activate( container, { focus: false, remember: false } );
			}
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var withdraw = closestByAttribute( event.target, 'data-cg-withdraw' );
		if ( withdraw ) {
			event.preventDefault();
			withdrawConsent();
			announceWithdrawal( withdraw );
			return;
		}

		var button = closestByClass( event.target, 'cg-embed__button' );
		if ( ! button ) {
			return;
		}
		var container = closestByClass( button, 'cg-embed' );
		if ( container ) {
			event.preventDefault();
			activate( container, { focus: true, remember: true } );
		}
	}, false );

	function closestByAttribute( el, name ) {
		while ( el && el !== document ) {
			if ( el.nodeType === 1 && el.hasAttribute && el.hasAttribute( name ) ) {
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	function announceWithdrawal( trigger ) {
		var status = document.getElementById( trigger.getAttribute( 'aria-controls' ) || '' );
		if ( status ) {
			status.textContent = memoryConfig().i18n.withdrawn
				|| 'Stored embed consents have been removed. Embeds will ask again.';
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', restoreFromMemory, false );
	} else {
		restoreFromMemory();
	}
}() );
