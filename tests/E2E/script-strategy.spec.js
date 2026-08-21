// Script-strategy providers (PLAN.md §3.5, §9.6): the SDK script tag is
// removed and replaced with a panel; the companion element (the provider's
// own no-JS content) stays. Nothing third-party loads before the click.
// @ts-check
const { test, expect } = require( '@playwright/test' );

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

test( 'script embeds: zero third-party requests before interaction', async ( { page } ) => {
	const offenders = [];
	page.on( 'request', ( request ) => {
		const host = new URL( request.url() ).hostname;
		if ( ! OWN_HOSTS.includes( host ) ) {
			offenders.push( request.url() );
		}
	} );

	await page.goto( '/page/scripts' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders, 'INVARIANT 1 VIOLATED — script SDK loaded before any click' ).toEqual( [] );

	// Both SDKs gated; the companion elements survive as no-JS content.
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 2 );
	await expect( page.locator( 'blockquote.twitter-tweet' ) ).toHaveCount( 1 );
	await expect( page.locator( 'div.strava-embed-placeholder' ) ).toHaveCount( 1 );
	// The harvested fallback is the canonical status link, not the .js file.
	await expect( page.locator( '[data-cg-provider="twitter"] .cg-embed__fallback a' ) )
		.toHaveAttribute( 'href', 'https://twitter.com/calucon/status/1234567890123456789' );
	await expect( page.locator( '[data-cg-provider="strava"] .cg-embed__fallback a' ) )
		.toHaveAttribute( 'href', 'https://www.strava.com/activities/1234567890' );
} );

test( 'clicking a script embed injects the SDK once and clears its panels', async ( { page } ) => {
	const sdkRequests = [];
	await page.route( '**', ( route ) => {
		const url = route.request().url();
		const host = new URL( url ).hostname;
		if ( OWN_HOSTS.includes( host ) ) {
			return route.continue();
		}
		sdkRequests.push( url );
		// Post-consent the request is legitimate; serve an empty script so
		// onload fires without contacting the real provider from CI.
		return route.fulfill( { contentType: 'application/javascript', body: '' } );
	} );

	await page.goto( '/page/scripts' );

	await page.locator( '[data-cg-provider="twitter"] .cg-embed__button' ).click();

	// The SDK script element is in the document, pointing at the provider.
	await expect( page.locator( 'script[src="https://platform.twitter.com/widgets.js"]' ) )
		.toHaveCount( 1 );
	await expect.poll( () => sdkRequests ).toEqual( [ 'https://platform.twitter.com/widgets.js' ] );

	// The twitter panel is gone, the strava panel still gated.
	await expect( page.locator( '[data-cg-provider="twitter"] .cg-embed__panel' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-cg-provider="strava"] .cg-embed__panel' ) ).toHaveCount( 1 );

	// Focus stays on the clicked container (§8), not <body>.
	const focused = await page.evaluate( () => document.activeElement && document.activeElement.className );
	expect( String( focused ) ).toContain( 'cg-embed' );
} );

test( 'a blocked SDK does not strand the sibling embeds', async ( { page } ) => {
	// The SDK is blocked (ad/tracker blockers hit exactly these scripts).
	await page.route( '**/widgets.js', ( route ) => route.abort() );

	await page.goto( '/page/scripts-multi' );
	await expect( page.locator( '[data-cg-provider="twitter"]' ) ).toHaveCount( 2 );

	// Click the first: its SDK request fails.
	await page.locator( '[data-cg-provider="twitter"] .cg-embed__button' ).first().click();

	// The sibling embed's panel AND its fallback link must survive the failed
	// load — clearing siblings up front would have deleted both.
	await expect( page.locator( '[data-cg-provider="twitter"] .cg-embed__panel' ) ).toHaveCount( 1 );
	await expect( page.locator( '[data-cg-provider="twitter"] .cg-embed__fallback a' ) )
		.toHaveAttribute( 'href', 'https://twitter.com/calucon/status/2222222222222222222' );

	// The clicked one routes to its own error state (§8) with its link intact.
	await expect( page.locator( '[data-cg-provider="twitter"]' ).first().locator( '.cg-embed__error a' ) )
		.toHaveAttribute( 'href', 'https://twitter.com/calucon/status/1111111111111111111' );
} );

test( 'a retried activation after a failed SDK load does not corrupt state', async ( { page } ) => {
	let attempts = 0;
	await page.route( '**/widgets.js', ( route ) => {
		attempts++;
		if ( attempts === 1 ) {
			return route.abort(); // First click: blocked.
		}
		// Retry: serve an empty SDK so onload fires.
		return route.fulfill( { contentType: 'application/javascript', body: '' } );
	} );

	await page.goto( '/page/scripts-multi' );
	const first = page.locator( '[data-cg-provider="twitter"]' ).first();

	await first.locator( '.cg-embed__button' ).click();
	await expect( first.locator( '.cg-embed__error' ) ).toHaveCount( 1 );

	// The bridge (or a second gesture) may retry after a failure. The retry
	// must not lose the stashed panel, stack a second error, duplicate the
	// active class, or leave a dead <script> from the first attempt behind.
	await page.evaluate( () => {
		const el = document.querySelector( '[data-cg-provider="twitter"]' );
		el.removeAttribute( 'data-cg-activated' ); // as the failure path does
		window.caluconEmbedGateBridge.grant( el );
	} );
	await expect.poll( () => attempts ).toBe( 2 );
	await expect( first.locator( '.cg-embed__error' ) ).toHaveCount( 1 );
	const state = await first.evaluate( ( el ) => ( {
		stash: el._cgStash ? el._cgStash.length : -1,
		classes: el.className,
		deadScripts: document.querySelectorAll( 'script[src*="widgets.js"]' ).length,
	} ) );
	expect( state.stash ).toBeGreaterThan( 0 ); // panel nodes preserved for a future regate
	expect( state.classes.split( 'cg-embed--active' ).length - 1 ).toBe( 1 ); // no duplicate class
	expect( state.deadScripts ).toBe( 1 ); // failed element removed, only the retry's element remains
} );
