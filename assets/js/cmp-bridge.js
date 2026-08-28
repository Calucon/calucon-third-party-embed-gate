/**
 * Calucon Third-Party Embed Gate — CMP bridge (PLAN.md §6.4).
 *
 * Dependency-free, ES5-compatible like gate.js. Only enqueued when the site
 * enabled the bridge AND a consent platform from the tested list is
 * installed; the adapter to use arrives in caluconEmbedGateConfig.cmp, decided
 * server-side and double-checked here by feature detection (belt and
 * braces — a deactivated CMP whose config survived in a cached page must
 * not ungate anything).
 *
 * One direction only: the CMP's affirmative grant for the embeds' category
 * auto-loads gated embeds, and its withdrawal re-gates what the bridge
 * loaded. The bridge never writes to the CMP, never stores anything itself
 * (invariant 3), and never touches Google Consent Mode — that channel is
 * written by CMPs for Google tags, has no public read API, and no consent
 * mode signal governs iframes. Bridging the CMP itself is the reliable
 * read of the same visitor choice.
 *
 * Everything fails closed: no adapter match, no CMP object, no answer —
 * the two-click gate stands exactly as without this file.
 */
( function () {
	'use strict';

	var config = ( window.caluconEmbedGateConfig || {} ).cmp || null;
	var bridge = window.caluconEmbedGateBridge;
	if ( ! config || ! bridge ) {
		return;
	}

	var category = typeof config.category === 'string' && config.category ? config.category : 'marketing';

	// The initial state check runs immediately AND on window load: this
	// script and the CMP's are independent plugins, so neither load order
	// is guaranteed. Change events are bound unconditionally — binding to
	// an event a missing CMP will never fire is free.
	function onSettled( check ) {
		check();
		window.addEventListener( 'load', check, false );
	}

	var adapters = {

		// WP Consent API (wordpress.org/plugins/wp-consent-api): the
		// WordPress-native contract, spoken by Complianz, Cookiebot's WP
		// plugin, CookieYes, iubenda and others. TRAP guarded here: with no
		// CMP hooked in, its wp_has_consent() returns true for everything
		// (fail-open by design). The bridge therefore requires a consent
		// type to be affirmatively set by a CMP before trusting any verdict.
		'wp-consent-api': function () {
			function typeKnown() {
				return !! ( window.wp_consent_type || window.wp_fallback_consent_type );
			}
			function granted() {
				return typeof window.wp_has_consent === 'function'
					&& typeKnown()
					&& !! window.wp_has_consent( category );
			}
			function check() {
				if ( granted() ) {
					bridge.grantAll();
				}
			}
			// Geo-detecting CMPs resolve the consent type late; the API
			// mirrors that in waitfor_consent_hook + this event.
			document.addEventListener( 'wp_consent_type_defined', check, false );
			document.addEventListener( 'wp_listen_for_consent_change', function ( e ) {
				var changed = e.detail || {};
				if ( ! Object.prototype.hasOwnProperty.call( changed, category ) ) {
					return;
				}
				if ( changed[ category ] === 'allow' ) {
					// Same fail-open guard as the load-time check: only grant
					// once a CMP has affirmatively set a consent type, so a
					// synthetic change event cannot ungate on the default.
					if ( typeKnown() ) {
						bridge.grantAll();
					}
				} else {
					// Re-gating is always safe; never guarded.
					bridge.regate();
				}
			}, false );
			// Run the initial check unconditionally — granted() already
			// requires a known consent type, so an early call is a safe no-op.
			// Gating it behind waitfor_consent_hook missed returning visitors
			// whose CMP had defined the type and fired its event before this
			// script ran (load order between the two plugins is not fixed).
			onSettled( check );
		},

		// Complianz: cmplz_has_consent() + the documented category events.
		// On revoke Complianz usually reloads the page itself; the regate
		// here covers the paths where it does not.
		complianz: function () {
			function check() {
				if ( typeof window.cmplz_has_consent === 'function' && window.cmplz_has_consent( category ) ) {
					bridge.grantAll();
				}
			}
			document.addEventListener( 'cmplz_enable_category', function ( e ) {
				var detail = e.detail || {};
				if ( detail.category === category
					|| ( detail.categories && detail.categories.indexOf( category ) !== -1 ) ) {
					bridge.grantAll();
				}
			}, false );
			document.addEventListener( 'cmplz_status_change', function ( e ) {
				var detail = e.detail || {};
				if ( detail.category === category && detail.value && detail.value !== 'allow' ) {
					bridge.regate();
				}
			}, false );
			document.addEventListener( 'cmplz_revoke', function () {
				bridge.regate();
			}, false );
			onSettled( check );
		},

		// Borlabs Cookie v3: service groups are site-defined; the group that
		// covers embedded content is a setting (default 'external-media').
		// The v2 API is different and untested — fail closed there.
		borlabs: function () {
			var group = typeof config.borlabsGroup === 'string' && config.borlabsGroup ? config.borlabsGroup : 'external-media';
			function granted() {
				var b = window.BorlabsCookie;
				return !! ( b && b.Consents
					&& typeof b.Consents.hasConsentForServiceGroup === 'function'
					&& b.Consents.hasConsentForServiceGroup( group ) );
			}
			function grantIfConsented() {
				if ( granted() ) {
					bridge.grantAll();
				}
			}
			function sync() {
				if ( granted() ) {
					bridge.grantAll();
				} else {
					bridge.regate();
				}
			}
			window.addEventListener( 'borlabs-cookie-after-init', grantIfConsented, false );
			window.addEventListener( 'borlabs-cookie-consent-saved', sync, false );
			onSettled( grantIfConsented );
		},

		// Cookiebot: read-only consent properties; CookiebotOnConsentReady
		// fires both for a stored consent on load and after each submission.
		// Never infer from OnAccept/OnDecline alone — a visitor accepting
		// only statistics fires OnAccept too. Always read the property.
		cookiebot: function () {
			function granted() {
				var c = window.Cookiebot;
				return !! ( c && c.consent && c.consent[ category ] );
			}
			function sync() {
				if ( granted() ) {
					bridge.grantAll();
				} else {
					bridge.regate();
				}
			}
			window.addEventListener( 'CookiebotOnConsentReady', sync, false );
			onSettled( function () {
				var c = window.Cookiebot;
				if ( c && c.hasResponse && granted() ) {
					bridge.grantAll();
				}
			} );
		},

		// CookieYes: getCkyConsent() snapshot + the documented banner
		// events. Category names are its fixed internal slugs — the server
		// sends 'advertisement' for this adapter.
		cookieyes: function () {
			function grantedIn( state ) {
				return !! ( state && state.categories && state.categories[ category ] );
			}
			function check() {
				if ( typeof window.getCkyConsent === 'function' ) {
					try {
						if ( grantedIn( window.getCkyConsent() ) ) {
							bridge.grantAll();
						}
					} catch ( e ) {
						// Not ready before its banner loaded: stay gated.
					}
				}
			}
			document.addEventListener( 'cookieyes_banner_load', function ( e ) {
				if ( grantedIn( e.detail ) ) {
					bridge.grantAll();
				}
			}, false );
			document.addEventListener( 'cookieyes_consent_update', function ( e ) {
				var detail = e.detail || {};
				if ( ( detail.accepted || [] ).indexOf( category ) !== -1 ) {
					bridge.grantAll();
				} else if ( ( detail.rejected || [] ).indexOf( category ) !== -1 ) {
					bridge.regate();
				}
			}, false );
			onSettled( check );
		},

		// Real Cookie Banner: refuses the WP Consent API by design; its
		// public contract is window.consentApi.unblock(url) — a Promise per
		// URL that resolves once RCB's own configuration allows it (which
		// includes "after the visitor consented to the service gating that
		// URL"). Per-container, so mixed pages resolve independently.
		// Grant-only: RCB exposes no public revocation event; its own
		// opt-out machinery handles revoked services.
		//
		// THE TRAP, found by the field suite against RCB 5.3 (2026-08-28):
		// unblock(url) resolves IMMEDIATELY when no content blocker matches
		// the URL — "not blocked" and "consented" look the same to a caller
		// that only awaits it. RCB's free tier ships no YouTube blocker, so
		// on a plain install every gated embed would auto-load with no
		// consent at all. So the bridge first asks whether RCB governs the
		// URL — unblockSync(url) returns the matched blocker, or undefined
		// when none matches; consentSync({url}) returns cookie null when no
		// service matches — and stays gated unless it does. Fail closed: a
		// site can only end up gating something RCB would have allowed
		// (visible), never loading something nobody consented to.
		'real-cookie-banner': function () {
			// RCB inlines a STUB consentApi before its banner script loads:
			// unblockSync() answers undefined and consentSync() "no cookie",
			// while unblock()/consent() are queued until the real API
			// replaces the stub and announces itself with a "consentApi"
			// event on window (its own snippet, measured against RCB 5.3).
			// Asking the stub is asking nobody: it looks exactly like "no
			// blocker". So the bridge decides only once the real API is
			// there — recognised by wrapFn(), which the stub never has — and
			// listens for the announcement in case that is after page load.
			var asked = false;
			function real( api ) {
				return !! ( api && typeof api.unblock === 'function'
					&& typeof api.unblockSync === 'function'
					&& typeof api.wrapFn === 'function' );
			}
			function governs( api, src ) {
				try {
					if ( api.unblockSync( src ) ) {
						return true;
					}
				} catch ( e ) {
					// Fall through to the second signal.
				}
				try {
					if ( typeof api.consentSync === 'function' ) {
						var state = api.consentSync( { url: src } );
						return !! ( state && state.cookie );
					}
				} catch ( e ) {
					// No answer is "no".
				}
				return false;
			}
			function check() {
				var api = window.consentApi;
				if ( asked || ! real( api ) ) {
					return;
				}
				asked = true;
				bridge.each( function ( container, payload ) {
					var src = typeof payload.src === 'string' ? payload.src : '';
					if ( ! /^(https?:)?\/\//i.test( src ) ) {
						return;
					}
					if ( ! governs( api, src ) ) {
						return;
					}
					try {
						api.unblock( src ).then( function () {
							bridge.grant( container );
						} );
					} catch ( e ) {
						// RCB not answering for this URL: stay gated.
					}
				} );
			}
			window.addEventListener( 'consentApi', check, false );
			onSettled( check );
		}
	};

	var adapter = adapters[ config.adapter ];
	if ( adapter ) {
		adapter();
	}

	// IAB TCF v2.2, experimental and separately flagged: a generic bridge
	// for TCF-running (ad-monetised) sites. v2.2 removed getTCData —
	// addEventListener is the only read path. A provider can only ever be
	// granted if it has a Global Vendor List id (most embed providers have
	// none; those stay click-only), and only with BOTH Purpose 1 consent
	// (storage on the device — what loading the embed triggers) and vendor
	// consent. gdprApplies === false is NOT a grant: this gate is not
	// GDPR-scoped, it is no-third-party-before-the-click.
	if ( config.tcf && typeof window.__tcfapi === 'function' ) {
		var vendors = config.tcf.vendors || {};
		window.__tcfapi( 'addEventListener', 2, function ( tcData, success ) {
			if ( ! success || ! tcData ) {
				return;
			}
			// cmpuishown means the banner is up and nothing is decided;
			// only settled states carry a verdict.
			if ( tcData.eventStatus !== 'tcloaded' && tcData.eventStatus !== 'useractioncomplete' ) {
				return;
			}
			if ( tcData.gdprApplies === false ) {
				return;
			}
			var purposes     = ( tcData.purpose && tcData.purpose.consents ) || {};
			var vendorGrants = ( tcData.vendor && tcData.vendor.consents ) || {};
			bridge.each( function ( container ) {
				var providerId = container.getAttribute( 'data-cg-provider' ) || '';
				var vendorId   = vendors[ providerId ];
				if ( vendorId && purposes[ 1 ] && vendorGrants[ vendorId ] ) {
					bridge.grant( container );
				}
			} );
			// A downgrade after useractioncomplete re-gates what this
			// bridge (not the visitor's click) loaded for vendors that
			// lost consent.
			bridge.regate( function ( container ) {
				var providerId = container.getAttribute( 'data-cg-provider' ) || '';
				var vendorId   = vendors[ providerId ];
				if ( ! vendorId ) {
					return false;
				}
				return ! ( purposes[ 1 ] && vendorGrants[ vendorId ] );
			} );
		} );
	}
}() );
