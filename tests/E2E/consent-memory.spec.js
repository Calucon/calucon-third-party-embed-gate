// Consent memory (PLAN.md §6.2, §10.3 item 7): nothing is written to
// storage before the click, the right key appears after it, remembered
// consent survives a reload, and withdrawal clears everything.
// @ts-check
const { test, expect } = require( '@playwright/test' );

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

async function abortThirdParty( page ) {
	await page.route( '**', ( route ) => {
		const host = new URL( route.request().url() ).hostname;
		return OWN_HOSTS.includes( host )
			? route.continue()
			: route.fulfill( { contentType: 'text/html', body: '<!doctype html><p>frame</p>' } );
	} );
}

async function storageState( page ) {
	return page.evaluate( () => ( {
		session: window.sessionStorage.getItem( 'calucon-embed-gate' ),
		local: window.localStorage.getItem( 'calucon-embed-gate' ),
	} ) );
}

test( 'with memory enabled, nothing is stored before the click', async ( { page } ) => {
	await page.goto( '/page/memory' );
	await page.waitForLoadState( 'networkidle' );

	expect( await storageState( page ) ).toEqual( { session: null, local: null } );
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 1 );
} );

test( 'the click stores exactly the scope key, and consent survives a reload', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( '/page/memory' );

	await page.locator( '.cg-embed__button' ).click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );

	const stored = await storageState( page );
	expect( stored.local ).toBeNull(); // Session lifetime → sessionStorage only.
	const parsed = JSON.parse( stored.session );
	// Provider scope: one key, no identifier — just what was consented to.
	expect( Object.keys( parsed.g ) ).toEqual( [ 'p:youtube' ] );

	// Reload: the embed is active without a new click, and no focus was
	// stolen by the restore (no user gesture happened).
	await page.reload();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 0 );
	expect( await page.evaluate( () => document.activeElement === document.body ) ).toBe( true );
} );

test( 'withdrawal clears storage, announces it, and embeds ask again', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( '/page/memory' );

	await page.locator( '.cg-embed__button' ).click();
	await page.locator( '[data-cg-withdraw]' ).click();

	expect( await storageState( page ) ).toEqual( { session: null, local: null } );
	// Announced via the live region (WCAG 4.1.3).
	await expect( page.locator( '#cg-withdraw-status' ) ).toContainText( 'removed' );

	await page.reload();
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 1 );
} );

test( 'persistent memory: localStorage, the identifier-free * key, cross-provider restore, and expiry', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( '/page/memory-persistent' );
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 2 );

	// One click on the YouTube panel under scope:'all'.
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );

	const stored = await storageState( page );
	expect( stored.session ).toBeNull(); // Persistent lifetime → localStorage only.
	expect( Object.keys( JSON.parse( stored.local ).g ) ).toEqual( [ '*' ] );

	// The '*' grant restores BOTH providers on the next load.
	await page.reload();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 2 );
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 0 );

	// Age the grant past durationDays (1): the lazy expiry must re-gate —
	// a stale consent is no consent (§6.2).
	await page.evaluate( () => {
		const raw = JSON.parse( window.localStorage.getItem( 'calucon-embed-gate' ) );
		for ( const key of Object.keys( raw.g ) ) {
			raw.g[ key ] = Date.now() - 2 * 86400000;
		}
		window.localStorage.setItem( 'calucon-embed-gate', JSON.stringify( raw ) );
	} );
	await page.reload();
	await expect( page.locator( '.cg-embed__panel' ) ).toHaveCount( 2 );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
} );

// Silent companions on the restore path. A companion belongs to a panel and
// is activated by it, after the script it needs; remembered consent that
// activated every container independently ran the inline call first, and the
// embed never rendered for a returning visitor.
async function stubThirdParty( page, attempted ) {
	await page.route( '**', ( route ) => {
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
}

test( 'remembered consent runs a companion after its panel, never before', async ( { page } ) => {
	const attempted = [];
	await stubThirdParty( page, attempted );

	await page.goto( '/page/companions-memory' );
	await page.locator( '.cg-embed[data-cg-provider="wolfram-cloud"] button' ).click();
	await expect.poll( () => page.evaluate( () => window.cgWolframInlineSawSdk ) ).toBe( true );

	// Returning visitor: the grant is remembered, so nothing is clicked.
	await page.reload();
	await expect.poll( () => page.evaluate( () => window.cgWolframInlineRan ) ).toBe( true );
	expect(
		await page.evaluate( () => window.cgWolframInlineSawSdk ),
		'the companion ran before the script it calls into'
	).toBe( true );
	await expect( page.locator( '.cg-embed--silent[data-cg-activated="1"]' ) ).toHaveCount( 2 );
} );

test( 'per-embed memory keeps two inline loaders apart', async ( { page } ) => {
	const attempted = [];
	await stubThirdParty( page, attempted );

	await page.goto( '/page/memory-inline' );
	await expect( page.locator( '.cg-embed[role="group"]' ) ).toHaveCount( 2 );

	await page.locator( '.cg-embed[data-cg-provider="scribd"] button' ).click();
	await expect.poll( () => attempted.filter( ( u ) => u.includes( 'inject.js' ) ).length ).toBe( 1 );

	await page.reload();
	await expect.poll( () => attempted.filter( ( u ) => u.includes( 'inject.js' ) ).length ).toBe( 2 );
	expect(
		attempted.filter( ( u ) => u.includes( 'crowdsignal' ) ),
		'INVARIANT 1 — consent for one embed loaded another provider'
	).toEqual( [] );
	await expect( page.locator( '.cg-embed[data-cg-provider="crowdsignal"] button' ) ).toBeVisible();
} );
