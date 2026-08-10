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
		session: window.sessionStorage.getItem( 'consent-gate' ),
		local: window.localStorage.getItem( 'consent-gate' ),
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
