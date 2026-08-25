/**
 * One-click exceptions from the Status scan (admin only, dependency-free ES5).
 *
 * The scan already knows which hosts are gated; before this, acting on that
 * meant reading a host name off the screen and typing it into a textarea on
 * another tab. These buttons stage the same edit into that textarea and let
 * the settings form's own Save apply it.
 *
 * Nothing is written here. That is deliberate: the settings screen holds the
 * WHOLE option tree in one form, so a save-on-click endpoint would be silently
 * reverted by the owner's next Save. Staging the host into the field it
 * belongs to makes that impossible — and keeps every write going through the
 * one sanitiser.
 *
 * The buttons are hidden in the markup and revealed here, like the provider
 * filter and "Add another provider": without JavaScript the screen is exactly
 * what it was, a read-only table plus the host lists.
 */
( function () {
	'use strict';

	var i18n = window.caluconEmbedGateScanI18n || {};

	function normalise( host ) {
		return String( host || '' ).replace( /^\s+|\s+$/g, '' ).toLowerCase();
	}

	function lines( field ) {
		var out = [];
		var raw = field.value.split( /\r?\n/ );
		for ( var i = 0; i < raw.length; i++ ) {
			var line = raw[ i ].replace( /^\s+|\s+$/g, '' );
			if ( '' !== line ) {
				out.push( line );
			}
		}
		return out;
	}

	// A real browser event, so admin-appearance.js counts this as an edit and
	// arms the unsaved bar. jQuery's .trigger() carries no originalEvent and
	// is deliberately ignored there.
	function fire( field ) {
		var event;
		try {
			event = new Event( 'input', { bubbles: true } );
		} catch ( e ) {
			event = document.createEvent( 'Event' );
			event.initEvent( 'input', true, false );
		}
		field.dispatchEvent( event );
	}

	function addHost( field, host ) {
		var current = lines( field );
		for ( var i = 0; i < current.length; i++ ) {
			// An exact match only: a '*.example.com' row is the owner's own
			// broader rule and must not be edited from under them.
			if ( current[ i ].toLowerCase() === host ) {
				return false;
			}
		}
		current.push( host );
		field.value = current.join( '\n' );
		fire( field );
		return true;
	}

	function removeHost( field, host ) {
		var current = lines( field );
		var kept = [];
		var removed = false;
		for ( var i = 0; i < current.length; i++ ) {
			if ( current[ i ].toLowerCase() === host ) {
				removed = true;
			} else {
				kept.push( current[ i ] );
			}
		}
		if ( ! removed ) {
			return false;
		}
		field.value = kept.join( '\n' );
		fire( field );
		return true;
	}

	function selectTab( name ) {
		var tab = document.getElementById( 'cg-tabbtn-' + name );
		if ( tab ) {
			tab.click();
		}
	}

	function init() {
		var status = document.getElementById( 'cg-tab-status' );
		var neverGate = document.getElementById( 'cg-never-gate' );
		var alwaysGate = document.getElementById( 'cg-always-gate' );
		var note = document.getElementById( 'cg-staged-note' );
		if ( ! status || ! neverGate || ! note ) {
			return;
		}

		var buttons = document.querySelectorAll( '.cg-scan-action' );
		for ( var i = 0; i < buttons.length; i++ ) {
			buttons[ i ].hidden = false;
		}

		var hostSlot = note.querySelector( '.cg-staged__host' );
		var letThrough = note.querySelector( '.cg-staged__body' );
		var gateAgain = note.querySelector( '.cg-staged__gate' );
		var undo = null;

		function show( host, gating ) {
			if ( hostSlot ) {
				hostSlot.textContent = host;
			}
			// One notice, two directions: only the sentence that matches what
			// is about to happen is shown.
			if ( letThrough ) {
				letThrough.hidden = gating;
			}
			if ( gateAgain ) {
				gateAgain.hidden = ! gating;
			}
			note.hidden = false;
			selectTab( 'detection' );
			note.scrollIntoView();
			note.focus();
		}

		function stage( field, host, mode, gating ) {
			var done = 'add' === mode ? addHost( field, host ) : removeHost( field, host );
			if ( ! done ) {
				return;
			}
			undo = { field: field, host: host, mode: mode };
			show( host, gating );
		}

		document.addEventListener( 'click', function ( event ) {
			var el = event.target;
			if ( ! el || ! el.getAttribute ) {
				return;
			}
			var except = el.getAttribute( 'data-cg-except' );
			var ungate = el.getAttribute( 'data-cg-ungate' );
			var always = el.getAttribute( 'data-cg-always' );
			var name = el.getAttribute( 'data-cg-name-host' );

			if ( except ) {
				event.preventDefault();
				stage( neverGate, normalise( except ), 'add', false );
			} else if ( ungate ) {
				event.preventDefault();
				stage( neverGate, normalise( ungate ), 'remove', true );
			} else if ( always && alwaysGate ) {
				event.preventDefault();
				stage( alwaysGate, normalise( always ), 'add', true );
			} else if ( name ) {
				event.preventDefault();
				nameHost( normalise( name ), 'script' === el.getAttribute( 'data-cg-name-kind' ) );
			} else if ( 'cg-staged-cancel' === el.id ) {
				event.preventDefault();
				if ( undo ) {
					if ( 'add' === undo.mode ) {
						removeHost( undo.field, undo.host );
					} else {
						addHost( undo.field, undo.host );
					}
					undo = null;
				}
				note.hidden = true;
			}
		} );

		// Naming a host keeps the gate on, so it needs no warning: fill the
		// blank custom-provider row and let the owner type a label.
		function nameHost( host, isScript ) {
			var blank = document.querySelector( '#cg-custom-providers tr[data-cg-blank]' );
			if ( ! blank ) {
				window.alert( i18n.noBlank || 'Add a provider row first, then try again.' );
				return;
			}
			// A host found as a third-party SCRIPT belongs in Script hosts:
			// put into Embed hosts it would match nothing, and the scan would
			// keep listing the same bare host as if the owner had done
			// nothing. Select by field name, never by column order.
			var hosts = blank.querySelector( isScript ? 'textarea[name$="[script_hosts]"]' : 'textarea[name$="[hosts]"]' );
			var label = blank.querySelector( 'input[type="text"]' );
			if ( hosts ) {
				hosts.value = host;
				fire( hosts );
			}
			selectTab( 'providers' );
			if ( label ) {
				label.scrollIntoView();
				label.focus();
			}
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
