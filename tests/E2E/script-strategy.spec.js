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
