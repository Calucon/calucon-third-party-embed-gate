// The forged-panel trap (CLAUDE.md): WordPress's kses hands every author
// `class` and `data-*` on every tag, plus <div> and <button>, so the whole
// 0.x placeholder — payload in a data attribute — was writable verbatim by a
// Contributor, and gate.js executed it. The payload now lives in the one
// element kses never lets through, <script type="application/json">, and
// gate.js reads it from nowhere else. These pages carry forged panels next
// to a real one; every activation path must leave the forgeries inert and
// execute nothing. If this file is red, a Contributor has stored XSS.
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

async function expectForgeriesInert( page ) {
	expect( await page.evaluate( () => window.__pwned ) ).toBeUndefined();
	for ( const id of [ 'inline', 'srcdoc', 'script', 'iframe' ] ) {
		const forged = page.locator( '#forged-' + id );
		await expect( forged.locator( 'iframe, script[src], embed, object, img' ), id + ' must build nothing' ).toHaveCount( 0 );
		await expect( forged.locator( '.cg-embed__fallback a' ), id + ' keeps its link' ).toBeVisible();
	}
	expect( await page.locator( 'script[src*="evil.example"]' ).count(), 'no script element for the forged src' ).toBe( 0 );
}

test( 'a click on a forged panel executes nothing; the real one still works', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );
	await page.route( '**', ( route ) => ( OWN_HOSTS.includes( new URL( route.request().url() ).hostname ) ? route.continue() : route.abort() ) );
	await page.goto( '/page/forged' );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 5 );

	for ( const id of [ 'inline', 'srcdoc', 'script', 'iframe' ] ) {
		await page.locator( '#forged-' + id + ' .cg-embed__button' ).click();
		// The panel answers (§8) instead of doing something silently.
		await expect( page.locator( '#forged-' + id + ' .cg-embed__error' ) ).toBeVisible();
	}
	await expectForgeriesInert( page );
	expect( offenders.filter( ( url ) => ! /youtube-nocookie\.com/.test( url ) ) ).toEqual( [] );

	// The real panel — with the server-rendered payload element — activates.
	const real = page.locator( '.cg-embed' ).first();
	await expect( real.locator( 'script.cg-embed__payload' ) ).toHaveCount( 1 );
	await real.locator( '.cg-embed__button' ).click();
	await expect( real.locator( 'iframe' ) ).toHaveAttribute( 'src', /youtube-nocookie\.com/ );
} );

test( 'a consent-memory restore with scope "all" activates the real panel and never a forged one', async ( { page } ) => {
	await page.route( '**', ( route ) => ( OWN_HOSTS.includes( new URL( route.request().url() ).hostname ) ? route.continue() : route.abort() ) );
	await page.addInitScript( () => {
		window.localStorage.setItem( 'calucon-embed-gate', JSON.stringify( { v: 1, g: { '*': Date.now() } } ) );
	} );
	await page.goto( '/page/forged-memory' );

	const real = page.locator( '.cg-embed' ).first();
	await expect( real.locator( 'iframe' ) ).toHaveCount( 1 );
	await expectForgeriesInert( page );
	for ( const id of [ 'inline', 'srcdoc', 'script', 'iframe' ] ) {
		await expect( page.locator( '#forged-' + id ) ).not.toHaveAttribute( 'data-cg-activated', '1' );
	}
} );

test( 'a CMP-bridge grantAll activates the real panel and never a forged one', async ( { page } ) => {
	await page.route( '**', ( route ) => ( OWN_HOSTS.includes( new URL( route.request().url() ).hostname ) ? route.continue() : route.abort() ) );
	await page.goto( '/page/forged' );
	await page.evaluate( () => window.caluconEmbedGateBridge.grantAll() );

	const real = page.locator( '.cg-embed' ).first();
	await expect( real.locator( 'iframe' ) ).toHaveCount( 1 );
	await expectForgeriesInert( page );
} );

test( 'a payload element that is not JSON is not a payload either', async ( { page } ) => {
	// A forgery cannot produce the element at all; this guards the reader
	// itself against a broken one (a filter returning junk): no execution,
	// an announced error, the link kept.
	await page.goto( '/page/forged' );
	const real = page.locator( '.cg-embed' ).first();
	await real.evaluate( ( node ) => {
		node.querySelector( 'script.cg-embed__payload' ).textContent = '{"strategy":"script","inline":"window.__pwned=1"';
	} );
	await real.locator( '.cg-embed__button' ).click();
	await expect( real.locator( '.cg-embed__error' ) ).toBeVisible();
	await expect( real.locator( 'iframe' ) ).toHaveCount( 0 );
	expect( await page.evaluate( () => window.__pwned ) ).toBeUndefined();
} );
