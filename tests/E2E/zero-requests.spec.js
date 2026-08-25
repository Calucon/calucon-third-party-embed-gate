// The headline test (PLAN.md §10.3): the entire product is that nothing
// third-party loads before the click. This file is never skipped and never
// marked flaky — if it is red, the product claim is false.
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

test( 'zero third-party requests before interaction', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/page/gated' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders, 'INVARIANT 1 VIOLATED — third-party requests before any click' ).toEqual( [] );

	// Four cross-origin embeds gated, the same-origin iframe untouched.
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 4 );
	await expect( page.locator( 'iframe[src="/frame.html"]' ) ).toHaveCount( 1 );
	expect( await page.locator( 'iframe' ).count() ).toBe( 1 );
} );

test( 'the privacy-policy link is offered before the click and leaves with the panel', async ( { page } ) => {
	await page.goto( '/page/gated' );
	const container = page.locator( '.cg-embed' ).first();
	await expect( container ).toBeVisible();

	// Before any click: a plain link to the provider's policy — informing
	// the visitor is free, no request happens unless they follow it.
	const privacy = container.locator( '.cg-embed__privacy a' );
	await expect( privacy ).toHaveAttribute( 'href', /^https:\/\// );

	await page.route( '**', ( route ) => {
		const host = new URL( route.request().url() ).hostname;
		return [ '127.0.0.1', 'localhost' ].includes( host ) ? route.continue() : route.abort();
	} );
	await container.locator( '.cg-embed__button' ).click();

	// After activation the panel — privacy link included — is gone.
	await expect( container.locator( '.cg-embed__privacy' ) ).toHaveCount( 0 );
} );

test( 'nothing is stored before consent', async ( { page } ) => {
	// Invariant 3: the plugin itself must not write to terminal equipment.
	await page.goto( '/page/gated' );
	await page.waitForLoadState( 'networkidle' );
	// Guard: empty storage is also true of an error page — prove the gate
	// actually rendered before the negative assertions mean anything.
	await expect( page.locator( '.cg-embed' ).first() ).toBeVisible();

	const storage = await page.evaluate( () => ( {
		localStorage: window.localStorage.length,
		sessionStorage: window.sessionStorage.length,
		cookies: document.cookie,
	} ) );

	expect( storage ).toEqual( { localStorage: 0, sessionStorage: 0, cookies: '' } );
} );

test( 'click inserts the iframe, with safelisted attributes only, and moves focus to the container', async ( { page } ) => {
	// After the click the request to the provider is legitimate — but this
	// test only checks the built node, so abort those requests at the router.
	await page.route( '**', ( route ) => {
		const host = new URL( route.request().url() ).hostname;
		return OWN_HOSTS.includes( host ) ? route.continue() : route.abort();
	} );

	await page.goto( '/page/gated' );

	const first = page.locator( '.cg-embed' ).first();
	const button = first.locator( '.cg-embed__button' );

	// WCAG 2.5.8: hit area at least 24×24 CSS px.
	const box = await button.boundingBox();
	expect( box.width ).toBeGreaterThanOrEqual( 24 );
	expect( box.height ).toBeGreaterThanOrEqual( 24 );

	await button.click();

	const frame = first.locator( 'iframe' );
	await expect( frame ).toHaveCount( 1 );
	// Data minimisation: the post-consent load goes to the privacy-preserving
	// host (measured 0 cookies vs 5 on the default host).
	await expect( frame ).toHaveAttribute( 'src', 'https://www.youtube-nocookie.com/embed/y_pjE_p1HwE' );
	await expect( frame ).toHaveAttribute( 'title', 'Kolkja Cycling' );
	await expect( frame ).toHaveAttribute( 'allowfullscreen', '' );
	// Invariant 8: autoplay never survives the rebuild.
	await expect( frame ).toHaveAttribute( 'allow', 'accelerometer; encrypted-media' );
	// Invariant 7 / §5.2: style must not be carried over.
	await expect( frame ).not.toHaveAttribute( 'style', /./ );

	// §8: focus lands on the container, never falls back to <body>.
	const focused = await page.evaluate( () => document.activeElement && document.activeElement.className );
	expect( String( focused ) ).toContain( 'cg-embed' );

	// The panel is gone; the other embeds stay gated.
	await expect( first.locator( '.cg-embed__panel' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 3 );
} );

test( 'placeholder works with JavaScript disabled: real fallback link, still zero third-party requests', async ( { browser } ) => {
	const context = await browser.newContext( { javaScriptEnabled: false } );
	const page = await context.newPage();
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/page/gated' );

	// Invariant 2: a visitor without JavaScript gets a real, working link —
	// a human page (watch URL), not an embed endpoint.
	const link = page.locator( '.cg-embed__fallback a' ).first();
	await expect( link ).toBeVisible();
	await expect( link ).toHaveAttribute( 'href', 'https://www.youtube.com/watch?v=y_pjE_p1HwE' );
	await expect( link ).toHaveAttribute( 'rel', 'noopener nofollow' );

	expect( offenders ).toEqual( [] );
	await context.close();
} );

test( 'owner-defined providers: zero third-party requests, built-ins keep their hosts, a disabled custom row still gates', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/page/custom-provider' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders, 'INVARIANT 1 VIOLATED — third-party requests before any click' ).toEqual( [] );

	// The unknown widget is named by the custom row — and gated although its
	// row is "disabled" in the options (custom providers are always gated).
	const widget = page.locator( '.cg-embed[data-cg-host="widgets.example-partner.com"]' );
	await expect( widget ).toHaveAttribute( 'data-cg-provider', 'custom-example-partner' );
	await expect( widget.locator( 'button' ) ).toContainText( 'Load content from Example Partner' );
	// The row that tried to claim YouTube's hosts changed nothing.
	const video = page.locator( '.cg-embed[data-cg-provider="youtube"]' );
	await expect( video ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed[data-cg-provider="custom-tube-thief"]' ) ).toHaveCount( 0 );
	// The script-strategy custom row gates its SDK.
	await expect( page.locator( '.cg-embed[data-cg-provider="custom-widget-sdk"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
	await expect( page.locator( 'script[src*="widget-sdk"]' ) ).toHaveCount( 0 );

	// Activation still goes to the privacy-preserving host for the built-in.
	await page.route( '**/*', ( route ) => ( route.request().url().startsWith( 'http://127.0.0.1' ) ? route.continue() : route.fulfill( { contentType: 'text/html', body: '<p>frame</p>' } ) ) );
	await video.locator( 'button' ).click();
	await expect( video.locator( 'iframe' ) ).toHaveAttribute( 'src', /youtube-nocookie\.com/ );
} );

test( 'silent companions: nothing loads before the click; after it, the inline injector and stylesheets follow their panel', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );
	const attempted = [];
	// Third-party requests are answered with stubs so loads "succeed"; the
	// test only needs to see WHEN they are attempted.
	await page.route( '**/*', ( route ) => {
		const url = route.request().url();
		if ( url.startsWith( 'http://127.0.0.1' ) ) {
			return route.continue();
		}
		attempted.push( url );
		if ( url.endsWith( '.css' ) ) {
			return route.fulfill( { contentType: 'text/css', body: '' } );
		}
		if ( url.endsWith( '.js' ) || url.includes( 'embedder' ) ) {
			return route.fulfill( { contentType: 'application/javascript', body: 'window.cgEmbedderLoaded = true;' } );
		}
		return route.fulfill( { contentType: 'text/html', body: '<p>frame</p>' } );
	} );

	await page.goto( '/page/companions' );
	await page.waitForLoadState( 'networkidle' );
	expect( offenders, 'INVARIANT 1 VIOLATED — third-party requests before any click' ).toEqual( [] );
	expect( attempted ).toEqual( [] );

	// Two visible panels (Scribd, Wolfram); the injector, the two
	// stylesheets and the inline call are silent companions.
	await expect( page.locator( '.cg-embed[role="group"]' ) ).toHaveCount( 2 );
	await expect( page.locator( '.cg-embed--silent' ) ).toHaveCount( 4 );
	expect( await page.evaluate( () => window.cgWolframInlineRan ) ).toBeUndefined();
	await expect( page.locator( 'link[rel="stylesheet"][href*="wolframcloud"]' ) ).toHaveCount( 0 );

	// Scribd: the iframe loads, then its inline injector runs and fetches inject.js.
	await page.locator( '.cg-embed[data-cg-provider="scribd"] button' ).click();
	await expect.poll( () => attempted.filter( ( u ) => u.includes( 'inject.js' ) ).length ).toBe( 1 );
	expect( attempted.filter( ( u ) => u.includes( 'wolframcloud' ) ) ).toEqual( [] );

	// Wolfram: the embedder script loads, then both stylesheets and the inline call follow.
	await page.locator( '.cg-embed[data-cg-provider="wolfram-cloud"] button' ).click();
	await expect.poll( () => attempted.filter( ( u ) => u.includes( 'wolframcloud.com/dist/' ) ).length ).toBe( 2 );
	await expect.poll( () => page.evaluate( () => window.cgWolframInlineRan ) ).toBe( true );
	await expect( page.locator( 'link[rel="stylesheet"][href*="wolframcloud"]' ) ).toHaveCount( 2 );
	await expect( page.locator( '.cg-embed--silent[data-cg-activated="1"]' ) ).toHaveCount( 4 );
} );

test( 'a deferred document.write loader appends instead of wiping the page', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/page/inline-write' );
	await page.waitForLoadState( 'networkidle' );
	expect( offenders ).toEqual( [] );

	// Gated: the loader has not run, so nothing was written yet.
	await expect( page.locator( '#cg-written' ) ).toHaveCount( 0 );
	const panel = page.locator( '.cg-embed[data-cg-provider="scribd"]' );
	await expect( panel ).toHaveCount( 1 );

	await panel.locator( 'button' ).click();

	// The write landed AND the page survived — without the shim, document.write
	// after load replaces the whole document and the heading disappears.
	await expect( page.locator( '#cg-written' ) ).toHaveCount( 1 );
	await expect( page.locator( 'h1' ) ).toBeVisible();
	await expect( page.locator( 'main' ) ).toContainText( 'written by the loader' );
} );
