// The §6.4 CMP bridge against simulated consent platforms: each /page/cmp-*
// stub implements the documented public API of its platform, and the real
// cmp-bridge.js runs against it. Two properties are load-bearing:
// fail-closed (no platform / no answer / fail-open trap → the gate stands)
// and revocation (what the bridge loaded is re-gated; what the visitor
// clicked stays).
// @ts-check
const { test, expect } = require( '@playwright/test' );

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

function abortThirdParty( page ) {
	return page.route( '**', ( route ) => {
		const host = new URL( route.request().url() ).hostname;
		return OWN_HOSTS.includes( host ) ? route.continue() : route.abort();
	} );
}

function trackThirdPartyRequests( page ) {
	const offenders = [];
	page.on( 'request', ( request ) => {
		const host = new URL( request.url() ).hostname;
		if ( ! OWN_HOSTS.includes( host ) ) {
			offenders.push( request.url() );
		}
	} );
	return offenders;
}

test( 'bridge configured but platform absent: everything stays gated', async ( { page } ) => {
	// A cached page can carry bridge config for a CMP that was deactivated
	// since — feature detection must fail closed, with zero requests.
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/page/cmp-none' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders ).toEqual( [] );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 2 );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
} );

test( 'the WP Consent API fail-open trap does not ungate', async ( { page } ) => {
	// wp_has_consent() answers true when NO consent platform ever set a
	// consent type — the API's documented fail-open default. Trusting it
	// would silently ungate every embed the moment a CMP is deactivated.
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/page/cmp-trap' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders ).toEqual( [] );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
} );

for ( const adapter of [ 'wp-consent-api', 'complianz', 'cookiebot', 'cookieyes', 'borlabs' ] ) {
	test( `${ adapter }: grant auto-loads, revoke re-gates, click still works after`, async ( { page } ) => {
		await abortThirdParty( page );
		await page.goto( `/page/cmp-${ adapter }` );

		// Before any consent: gated, and the panels are live.
		await expect( page.locator( '.cg-embed' ) ).toHaveCount( 2 );
		await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );

		// CMP grant: both embeds load without a second click, and without
		// stealing focus — there was no user gesture on this page.
		await page.evaluate( () => window.__cmpGrant() );
		await expect( page.locator( 'iframe' ) ).toHaveCount( 2 );
		await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 0 );
		expect( await page.evaluate( () => document.activeElement === document.body ) ).toBe( true );

		// CMP withdrawal: the bridge re-gates what it loaded — panels and
		// their buttons are restored, frames gone.
		await page.evaluate( () => window.__cmpRevoke() );
		await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 2 );
		await expect( page.locator( '.cg-embed[data-cg-activated]' ) ).toHaveCount( 0 );

		// The restored placeholder is not decoration: a click must still
		// activate it exactly like a never-bridged one.
		await page.locator( '.cg-embed__button' ).first().click();
		await expect( page.locator( 'iframe' ) ).toHaveCount( 1 );
	} );
}

test( 'real-cookie-banner: per-embed unblock promises resolve to activation', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( '/page/cmp-rcb' );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 2 );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );

	await page.evaluate( () => window.__cmpGrant() );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 2 );
} );

test( 'a clicked embed survives a CMP withdrawal; a bridged one does not', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( '/page/cmp-complianz' );

	// The visitor clicks the first embed themselves…
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( 'iframe' ) ).toHaveCount( 1 );

	// …then the CMP grants (bridging only the second embed)…
	await page.evaluate( () => window.__cmpGrant() );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 2 );

	// …and withdraws. The click was a separate, more specific consent:
	// only the bridge-loaded embed is re-gated.
	await page.evaluate( () => window.__cmpRevoke() );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 1 );
} );

test( 'TCF: only GVL-registered providers are granted; downgrade re-gates', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( '/page/cmp-tcf' );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 2 );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );

	// Purpose 1 + vendor 755 granted: YouTube loads. Vimeo has no Global
	// Vendor List entry — TCF cannot answer for it, so it keeps the click.
	await page.evaluate( () => window.__cmpGrant() );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed[data-cg-provider="vimeo"] .cg-embed__button' ) ).toHaveCount( 1 );

	await page.evaluate( () => window.__cmpRevoke() );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 2 );
} );
