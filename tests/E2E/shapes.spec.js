// The detection-hardening shapes end-to-end: attribute-swapped lazy loading,
// legacy object/embed, srcdoc, hidden pixels — and the generic-provider
// collision fix. All against the real pipeline + real gate.js in a browser.
// @ts-check
const { test, expect } = require( '@playwright/test' );

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

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

test( 'hardened shapes: all gated, zero third-party requests, pixel removed', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/page/shapes' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders, 'INVARIANT 1 VIOLATED on hardened shapes' ).toEqual( [] );

	// Lazy iframe, object/embed pair (one panel), srcdoc — three panels.
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	// The GTM noscript pixel is gone entirely: no panel, no iframe.
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
	expect( await page.content() ).not.toContain( 'googletagmanager' );
} );

test( 'srcdoc placeholder restores the original inline document on click', async ( { page } ) => {
	await page.goto( '/page/shapes' );

	// Stable selector: the payload element is the container's own child and
	// survives the panel's removal on activation; only the srcdoc embed's
	// payload carries a srcdoc.
	const index = await page.evaluate( () => {
		return Array.prototype.findIndex.call( document.querySelectorAll( '.cg-embed' ), ( node ) => {
			const payload = node.querySelector( 'script.cg-embed__payload' );
			return !! payload && payload.textContent.indexOf( '"srcdoc"' ) !== -1;
		} );
	} );
	expect( index ).toBeGreaterThanOrEqual( 0 );
	const container = page.locator( '.cg-embed' ).nth( index );
	await container.locator( 'button' ).click();

	const frame = container.locator( 'iframe' );
	await expect( frame ).toHaveCount( 1 );
	await expect( frame ).toHaveAttribute( 'srcdoc', /img\.youtube\.com/ );
} );

test( 'generic collision: activating one unknown widget leaves the other intact', async ( { page } ) => {
	await page.goto( '/page/collision' );

	// Two unknown scripts + one unknown iframe, all gated, all generic —
	// but each carries its own host.
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );

	const first = page.locator( '.cg-embed[data-cg-host="cdn.example-a.com"]' );
	const second = page.locator( '.cg-embed[data-cg-host="cdn.example-b.com"]' );
	await first.locator( 'button' ).click();

	// The second widget's placeholder, button and fallback link survive.
	await expect( second ).toHaveCount( 1 );
	await expect( second.locator( 'button' ) ).toHaveCount( 1 );
	await expect( second.locator( '.cg-embed__fallback a' ) ).toHaveCount( 1 );

	// The clicked widget's SDK cannot load in the sandbox (no such host):
	// the error state must be announced with a route to the fallback,
	// never a silent dead end (PLAN.md §8).
	await expect( first.locator( '[role="alert"]' ) ).toHaveCount( 1 );
	await expect( first.locator( '[role="alert"] a' ) ).toHaveAttribute( 'href', /example-a/ );
} );
