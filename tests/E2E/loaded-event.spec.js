// The "calucon-embed-gate:loaded" DOM event (docs/customizing.md, "When an
// embed has loaded") — public surface since 1.1: a bubbling CustomEvent on
// the placeholder container once its embed has loaded, with who asked for
// it. Sites use it to resize a layout, start a player API or count a view
// without polling for an iframe.
// @ts-check
const { test, expect } = require( '@playwright/test' );

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

/** Answer third-party requests locally: an empty page for frames, an empty script for SDKs. */
async function stubThirdParty( page, { failScripts = false } = {} ) {
	await page.route( '**', ( route ) => {
		const request = route.request();
		if ( OWN_HOSTS.includes( new URL( request.url() ).hostname ) ) {
			return route.continue();
		}
		if ( 'script' === request.resourceType() ) {
			return failScripts ? route.abort() : route.fulfill( { contentType: 'application/javascript', body: '' } );
		}
		return route.fulfill( { contentType: 'text/html', body: '<!doctype html><p>frame</p>' } );
	} );
}

/** Record every loaded event the document sees, from before the page's own scripts run. */
async function recordEvents( page ) {
	await page.addInitScript( () => {
		window.__cgLoaded = [];
		document.addEventListener( 'calucon-embed-gate:loaded', ( event ) => {
			window.__cgLoaded.push( {
				provider: event.detail.provider,
				host: event.detail.host,
				strategy: event.detail.strategy,
				trigger: event.detail.trigger,
				element: event.detail.element ? event.detail.element.nodeName : null,
				keys: Object.keys( event.detail ).sort(),
				targetIsContainer: event.target.classList.contains( 'cg-embed' ),
				targetIsActive: event.target.classList.contains( 'cg-embed--active' ),
				frameInTarget: !! event.detail.element && event.target.contains( event.detail.element ),
				bubbles: event.bubbles,
			} );
		} );
	} );
}

const events = ( page ) => page.evaluate( () => window.__cgLoaded );

test( 'a clicked iframe embed announces itself once, on its container, after the frame has loaded', async ( { page } ) => {
	await stubThirdParty( page );
	await recordEvents( page );
	await page.goto( '/page/gated' );
	await page.waitForLoadState( 'networkidle' );
	expect( await events( page ), 'nothing has loaded before a click' ).toEqual( [] );

	await page.locator( '[data-cg-provider="youtube"] .cg-embed__button' ).first().click();
	await expect.poll( () => events( page ) ).toHaveLength( 1 );
	expect( ( await events( page ) )[ 0 ] ).toEqual( {
		provider: 'youtube',
		host: 'www.youtube-nocookie.com',
		strategy: 'iframe',
		trigger: 'click',
		element: 'IFRAME',
		// The promised detail, and nothing a site could start depending on by accident.
		keys: [ 'element', 'host', 'provider', 'strategy', 'trigger' ],
		targetIsContainer: true,
		targetIsActive: true,
		frameInTarget: true,
		bubbles: true,
	} );

	// A second load inside the same frame is not a second embed.
	await page.evaluate( () => {
		const frame = document.querySelector( '.cg-embed--active iframe' );
		frame.src = frame.src + '?again=1';
	} );
	await page.waitForTimeout( 500 );
	expect( await events( page ) ).toHaveLength( 1 );
} );

test( 'a script embed announces itself after its SDK has loaded — and not when the SDK is blocked', async ( { page } ) => {
	await stubThirdParty( page );
	await recordEvents( page );
	await page.goto( '/page/scripts' );
	await page.locator( '[data-cg-provider="twitter"] .cg-embed__button' ).click();
	await expect.poll( () => events( page ) ).toHaveLength( 1 );
	expect( ( await events( page ) )[ 0 ] ).toMatchObject( {
		provider: 'twitter',
		strategy: 'script',
		trigger: 'click',
		element: null,
		targetIsContainer: true,
	} );

	// Blocked (an ad blocker hits exactly these SDKs): the embed did not
	// load, so nothing may say it did.
	const blocked = await page.context().newPage();
	await stubThirdParty( blocked, { failScripts: true } );
	await recordEvents( blocked );
	await blocked.goto( '/page/scripts' );
	await blocked.locator( '[data-cg-provider="twitter"] .cg-embed__button' ).click();
	await expect( blocked.locator( '.cg-embed__error' ) ).toHaveCount( 1 );
	expect( await events( blocked ) ).toEqual( [] );
	await blocked.close();
} );

test( 'a silent companion is part of its panel\'s embed and announces nothing of its own', async ( { page } ) => {
	await stubThirdParty( page );
	await recordEvents( page );
	await page.goto( '/page/companions' );
	await page.locator( '.cg-embed__button' ).first().click();
	await expect.poll( () => events( page ) ).toHaveLength( 1 );
	await page.waitForTimeout( 500 );
	expect( await events( page ) ).toHaveLength( 1 );
} );

test( 'the trigger says who asked: a remembered consent is not a click', async ( { page } ) => {
	await stubThirdParty( page );
	await recordEvents( page );
	await page.goto( '/page/memory' );
	await page.locator( '.cg-embed__button' ).click();
	await expect.poll( async () => ( await events( page ) ).map( ( e ) => e.trigger ) ).toEqual( [ 'click' ] );

	await page.reload();
	await expect.poll( async () => ( await events( page ) ).map( ( e ) => e.trigger ) ).toEqual( [ 'memory' ] );
} );

test( 'the trigger says who asked: the consent platform', async ( { page } ) => {
	await stubThirdParty( page );
	await recordEvents( page );
	await page.goto( '/page/cmp-complianz' );
	await page.evaluate( () => window.__cmpGrant() );
	await expect.poll( async () => ( await events( page ) ).map( ( e ) => e.trigger ) ).toContain( 'bridge' );
	expect( ( await events( page ) ).every( ( e ) => 'bridge' === e.trigger ) ).toBe( true );
} );

// The 1.1 panel: the owner's alternative link, and the players a builder
// or a facade component would have built in the browser.
test( 'the alternative link is a real link to this page, and builder-built players load on the click like any other', async ( { page } ) => {
	const seen = [];
	await page.route( '**', ( route ) => {
		const url = route.request().url();
		if ( OWN_HOSTS.includes( new URL( url ).hostname ) ) {
			return route.continue();
		}
		seen.push( url );
		return route.fulfill( { contentType: 'text/html', body: '<!doctype html><p>frame</p>' } );
	} );
	await recordEvents( page );
	await page.goto( '/page/alternative' );
	await page.waitForLoadState( 'networkidle' );

	// Three panels (a block embed, Enfold's templated video, a facade
	// element), nothing requested, nothing left for a builder's script.
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( 'lite-youtube, .av-video-tmpl, .av-click-to-play-overlay' ) ).toHaveCount( 0 );
	expect( seen ).toEqual( [] );

	// The alternative: a link, in every panel, that goes nowhere but here.
	const alternative = page.locator( '.cg-embed__alternative a' );
	await expect( alternative ).toHaveCount( 3 );
	await expect( alternative.first() ).toHaveText( 'Read the transcript on this page' );
	await alternative.first().click();
	await expect( page ).toHaveURL( /\/page\/alternative#transcript$/ );
	expect( seen, 'following the alternative must not load the embed' ).toEqual( [] );
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 3 );

	// Enfold's video: the click loads Vimeo into Enfold's own element.
	await page.locator( '.avia-video .cg-embed__button' ).click();
	await expect( page.locator( '.avia-video > .cg-embed--active > iframe' ) ).toHaveAttribute( 'src', /player\.vimeo\.com\/video\/76979871\?dnt=1$/ );
	// The facade: YouTube, from the cookieless host.
	await page.locator( '.cg-embed[data-cg-provider="youtube"]:not(.cg-embed--active) .cg-embed__button' ).last().click();
	await expect.poll( async () => ( await events( page ) ).map( ( e ) => e.provider ).sort() ).toEqual( [ 'vimeo', 'youtube' ] );
	expect( seen.every( ( url ) => /^https:\/\/(player\.vimeo\.com|www\.youtube-nocookie\.com)\//.test( url ) ), seen.join( ' ' ) ).toBe( true );
} );
