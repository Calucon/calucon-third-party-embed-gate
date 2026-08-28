// Caching and minification plugins do not leave scripts where WordPress put
// them: they add defer or async, roll everything into one bundle that runs
// long after DOMContentLoaded, and can emit an inline block after the file it
// belongs to. None of that may stop a visitor loading an embed they asked for.
//
// The gate is built to survive it — the click listener is delegated on
// `document` and there is a readyState guard — but until this file existed
// nothing proved it, and an innocent-looking refactor could have taken it away.
// @ts-check
const { test, expect } = require( '@playwright/test' );

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

async function stubThirdParty( page ) {
	await page.route( '**', ( route ) => {
		const host = new URL( route.request().url() ).hostname;
		return OWN_HOSTS.includes( host )
			? route.continue()
			: route.fulfill( { contentType: 'text/html', body: '<!doctype html><p>frame</p>' } );
	} );
}

for ( const mode of [ 'defer', 'async', 'late' ] ) {
	test( `gate.js delivered "${ mode }" still activates the embed`, async ( { page } ) => {
		await stubThirdParty( page );
		await page.goto( `/page/loading-${ mode }` );
		// 'late' injects gate.js from a window.load handler, so the script is
		// not even in the document until the load event has been and gone.
		await page.waitForLoadState( 'load' );
		await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 1 );

		await page.locator( '.cg-embed__button' ).click();

		await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 0 );
		// Focus must land on the container, never be lost to <body> (§8, 2.4.3).
		await expect( page.locator( '.cg-embed' ) ).toBeFocused();
	} );

	test( `nothing third-party is requested before the click with "${ mode }"`, async ( { page } ) => {
		const foreign = [];
		await page.route( '**', ( route ) => {
			const host = new URL( route.request().url() ).hostname;
			if ( ! OWN_HOSTS.includes( host ) ) {
				foreign.push( route.request().url() );
				return route.fulfill( { contentType: 'text/html', body: '<p>frame</p>' } );
			}
			return route.continue();
		} );

		await page.goto( `/page/loading-${ mode }` );
		await page.waitForLoadState( 'networkidle' );

		expect( foreign ).toEqual( [] );
	} );
}

/**
 * A script combiner can emit the inline config after gate.js instead of before
 * it. This works — and the reason is the `document.readyState === 'loading'`
 * guard at the foot of gate.js, which defers the memory restore to
 * DOMContentLoaded, by which time any inline block later in the body has run.
 *
 * That guard is therefore load-bearing, not a tidy-up. Replacing it with a bare
 * `restoreFromMemory()` makes this test fail (verified by mutation), which is
 * the whole reason it is here.
 */
test( 'consent memory still works when the config lands after gate.js', async ( { page } ) => {
	await stubThirdParty( page );

	// Grant on the ordinary page, where the config precedes gate.js…
	await page.goto( '/page/memory' );
	await page.locator( '.cg-embed__button' ).click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );

	// …then load the page a combiner has reordered. Same origin, same storage,
	// same provider scope: the stored consent must still be found and applied.
	await page.goto( '/page/loading-config-after' );
	await page.waitForLoadState( 'load' );

	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 0 );
} );

/**
 * "Delay JavaScript execution until interaction" (WP Rocket, LiteSpeed,
 * Perfmatters) is the one setting the gate cannot paper over: gate.js does not
 * exist when the visitor first clicks, so that click lifts the delay instead of
 * loading the embed, and they have to click again.
 *
 * This test exists to keep that behaviour honest and documented rather than
 * discovered in the field. What must stay true either way: nothing
 * third-party is contacted by the extra click, and the embed does load once
 * the script is there. The fix is the exclusion list on the Compatibility
 * screen, not code here — an inline click-capture stub would mean shipping
 * inline JS on every page to work around another plugin's setting.
 */
test( 'with JS delayed until interaction the first click is lost, the second works', async ( { page } ) => {
	const foreign = [];
	await page.route( '**', ( route ) => {
		const host = new URL( route.request().url() ).hostname;
		if ( ! OWN_HOSTS.includes( host ) ) {
			foreign.push( route.request().url() );
			return route.fulfill( { contentType: 'text/html', body: '<p>frame</p>' } );
		}
		return route.continue();
	} );

	await page.goto( '/page/loading-delayed' );
	await page.waitForLoadState( 'networkidle' );
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 1 );

	// First click: consumed by the delay shim, which only now loads gate.js.
	await page.locator( '.cg-embed__button' ).click();
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 1 );
	expect( foreign ).toEqual( [] );

	// Second click: the listener is attached, and the embed loads normally.
	await page.locator( '.cg-embed__button' ).click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );
