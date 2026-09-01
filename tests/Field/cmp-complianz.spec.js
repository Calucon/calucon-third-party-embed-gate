// Complianz (complianz-gdpr), the real plugin, against the bridge adapter.
//
// The simulation in tests/E2E/cmp-bridge.spec.js implements Complianz's
// documented API by hand; this file calls the actual one. Two things are
// load-bearing and asserted first: the platform is really there (its
// globals exist), and with no consent nothing loads and nothing is
// requested. Only then is consent granted — through cmplz_set_consent(),
// the function Complianz's own banner buttons call.
//
// Selector liability: `.cmplz-accept` / `.cmplz-deny` are Complianz's banner
// buttons. If a Complianz release renames them, only the "real banner" test
// fails — the API tests stand on their own.
// @ts-check
const { test, expect } = require( '@playwright/test' );
const {
	GATED_PAGE,
	requireField,
	login,
	openStatusTab,
	compatRow,
	trackThirdPartyRequests,
	abortThirdParty,
	setBridge,
} = require( './_helpers' );

// Hosts Complianz contacts on its own behalf. Empty until a run proves a
// need; every entry is a decision, with its reason, never a default.
const COMPLIANZ_OWN_HOSTS = [];

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'complianz-gdpr' ] );
} );

async function expectComplianzPresent( page ) {
	// A CMP whose script failed to load would make every fail-closed
	// assertion below pass for the wrong reason.
	await expect.poll( () => page.evaluate( () => typeof window.cmplz_set_consent === 'function' && typeof window.cmplz_has_consent === 'function' ), {
		message: 'Complianz front-end script is not loaded (wizard not completed? region not set for localhost?)',
	} ).toBe( true );
}

test( 'Compatibility names Complianz; bridge off says "tested", bridge on says "active"', async ( { page } ) => {
	await login( page );
	await setBridge( page, false );
	await openStatusTab( page );
	await expect( compatRow( page, 'Complianz' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'Complianz' ) ).toContainText( 'tested for interoperation' );

	await setBridge( page, true );
	await openStatusTab( page );
	await expect( compatRow( page, 'Complianz' ) ).toContainText( 'bridge active' );
} );

test( 'bridge on, no consent: everything stays gated and nothing third-party is requested', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page, COMPLIANZ_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await page.waitForLoadState( 'networkidle' );
	await expectComplianzPresent( page );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( 'iframe[src*="youtube"], iframe[src*="example-partner"]' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
} );

test( 'consent through the real API auto-loads; revocation re-gates; a click still works after', async ( { page } ) => {
	await abortThirdParty( page, COMPLIANZ_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await expectComplianzPresent( page );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );

	// Grant marketing — what the banner's accept button does.
	await page.evaluate( () => window.cmplz_set_consent( 'marketing', 'allow' ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 0 );
	expect( await page.evaluate( () => document.activeElement === document.body ) ).toBe( true );

	// Deny again. Complianz reloads the page shortly after a downgrade; the
	// bridge's own regate covers the moment before it does. Either way the
	// page must end up gated.
	await page.evaluate( () => window.cmplz_set_consent( 'marketing', 'deny' ) );
	await page.waitForLoadState( 'networkidle' );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 3 );

	// The restored placeholder is not decoration.
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );

test( 'a clicked embed survives a withdrawal; a bridged one does not', async ( { page } ) => {
	await abortThirdParty( page, COMPLIANZ_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await expectComplianzPresent( page );

	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );

	await page.evaluate( () => window.cmplz_set_consent( 'marketing', 'allow' ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );

	// Revoke without Complianz's reload getting in the way: dispatch the
	// event the adapter listens for, as a downgrade would.
	await page.evaluate( () => document.dispatchEvent( new CustomEvent( 'cmplz_revoke' ) ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 2 );
} );

test( 'the real banner: accept loads, and a fresh visit with the stored consent loads on arrival', async ( { page } ) => {
	await abortThirdParty( page, COMPLIANZ_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await expectComplianzPresent( page );
	const accept = page.locator( '.cmplz-accept' ).first();
	await expect( accept, 'Complianz banner not shown — on localhost it needs a default banner for "other regions"' ).toBeVisible();
	await accept.click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );

	// Stored consent: a returning visitor gets the embeds without a click.
	await page.goto( GATED_PAGE );
	await expectComplianzPresent( page );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );
} );
