// A prerendered page runs its scripts before the visitor has gone there,
// and may never be visited at all. WordPress's speculative loading ships as
// "prefetch", but a site — and since WordPress 7.1 a host, through
// WP_SPECULATIVE_LOADING_DEFAULT_MODE — can switch it to "prerender". The
// two unattended ways an embed loads, consent memory and the
// consent-platform bridge, must therefore wait for the visitor to arrive:
// a consent covers the pages they open, not the ones a browser guesses at.
//
// Real prerendering needs a second page and a browser heuristic to trigger
// it, so the state is simulated here exactly as the platform exposes it:
// document.prerendering is true, and prerenderingchange fires when the page
// is shown. gate.js reads nothing else.
// @ts-check
const { test, expect } = require( '@playwright/test' );

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

/** Record third-party requests, and answer them so the page keeps working. */
async function trackThirdParty( page ) {
	const seen = [];
	await page.route( '**', ( route ) => {
		const url = route.request().url();
		if ( OWN_HOSTS.includes( new URL( url ).hostname ) ) {
			return route.continue();
		}
		seen.push( url );
		return route.fulfill( { contentType: 'text/html', body: '<!doctype html><p>frame</p>' } );
	} );
	return seen;
}

/** From the next navigation on, the page believes it is being prerendered. */
async function prerenderNextNavigation( page ) {
	await page.addInitScript( () => {
		let prerendering = true;
		Object.defineProperty( Document.prototype, 'prerendering', {
			configurable: true,
			get: () => prerendering,
		} );
		window.__cgArrive = () => {
			prerendering = false;
			document.dispatchEvent( new Event( 'prerenderingchange' ) );
		};
	} );
}

for ( const [ path, what ] of [ [ '/page/memory', 'an iframe embed' ], [ '/page/companions-memory', 'a script embed and its companions' ] ] ) {
	test( `consent memory: ${ what } waits on a prerendered page and loads when the visitor arrives`, async ( { page } ) => {
		const seen = await trackThirdParty( page );
		await page.goto( path );
		await page.locator( '.cg-embed__button' ).first().click();
		await expect( page.locator( '.cg-embed--active' ).first() ).toBeAttached();
		// Polled: the request leaves a moment after the container is marked.
		await expect.poll( () => seen.length, { message: 'the click itself must load the embed' } ).toBeGreaterThan( 0 );
		const panels = await page.locator( '.cg-embed__panel' ).count();

		// Leave the page before counting again: a companion the click set
		// loading (a stylesheet after its script) may still be on its way,
		// and would otherwise be booked against the prerendered visit.
		await page.waitForLoadState( 'networkidle' );
		await page.goto( 'about:blank' );

		// The same page again, prerendered: the consent is remembered (the
		// session's storage outlives the detour), and nothing may be
		// contacted for it yet.
		seen.length = 0;
		await prerenderNextNavigation( page );
		await page.goto( path );
		await page.waitForLoadState( 'networkidle' );
		await page.waitForTimeout( 500 );
		expect( await page.evaluate( () => document.prerendering ) ).toBe( true );
		await expect( page.locator( '.cg-embed--active' ) ).toHaveCount( 0 );
		expect( await page.locator( '.cg-embed__panel' ).count(), 'every panel is still a panel while prerendering' ).toBeGreaterThan( panels );
		expect( seen, 'a remembered embed was contacted on a page the visitor has not opened' ).toEqual( [] );

		// The visitor arrives: now it loads, without a click.
		await page.evaluate( () => window.__cgArrive() );
		await expect( page.locator( '.cg-embed--active' ).first() ).toBeAttached();
		await expect.poll( () => seen.length ).toBeGreaterThan( 0 );
	} );
}

test( 'consent-platform bridge: a grant waits on a prerendered page, and a withdrawal before arrival cancels it', async ( { page } ) => {
	const seen = await trackThirdParty( page );
	await prerenderNextNavigation( page );
	await page.goto( '/page/cmp-complianz' );
	await page.waitForLoadState( 'networkidle' );

	await page.evaluate( () => window.__cmpGrant() );
	await page.waitForTimeout( 300 );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	expect( seen ).toEqual( [] );

	// The platform takes it back while the page is still not on screen.
	await page.evaluate( () => window.__cmpRevoke() );
	await page.evaluate( () => window.__cgArrive() );
	await page.waitForTimeout( 300 );
	await expect( page.locator( '.cg-embed iframe' ), 'a grant withdrawn before arrival must not load on arrival' ).toHaveCount( 0 );
	expect( seen ).toEqual( [] );
	await expect( page.locator( '.cg-embed__panel' ).first() ).toBeVisible();
} );

test( 'consent-platform bridge: a grant that stands loads when the visitor arrives', async ( { page } ) => {
	const seen = await trackThirdParty( page );
	await prerenderNextNavigation( page );
	await page.goto( '/page/cmp-complianz' );
	await page.waitForLoadState( 'networkidle' );

	await page.evaluate( () => window.__cmpGrant() );
	await page.waitForTimeout( 300 );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );

	await page.evaluate( () => window.__cgArrive() );
	await expect( page.locator( '.cg-embed iframe' ).first() ).toBeAttached();
	await expect.poll( () => seen.length ).toBeGreaterThan( 0 );
	// A later withdrawal still re-gates it: the deferral did not lose the
	// "bridged" mark.
	await page.evaluate( () => window.__cmpRevoke() );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
} );

test( 'a page that is not prerendered behaves exactly as before', async ( { page } ) => {
	await trackThirdParty( page );
	await page.goto( '/page/memory' );
	expect( await page.evaluate( () => document.prerendering ) ).not.toBe( true );
	await page.locator( '.cg-embed__button' ).click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
	await page.reload();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );
