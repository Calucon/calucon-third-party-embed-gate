// WP Consent API (wp-consent-api), the real plugin, against the bridge adapter.
//
// The API plugin is only the API: a consent-management plugin registers the
// consent TYPE (optin/optout) through it, and until one does,
// wp_has_consent() returns true for everything — fail-open by design. The
// bridge must not trust that. Two site states share one stack:
//
//   default           tests/wp/field-seed.php's stub plays the CMP and
//                     registers 'optin' — the interoperation case
//   ?cg_field_cmp=0   the stub stands down: API plugin alone — the trap
//
// Consent is granted through wp_set_consent(), the API plugin's own JS,
// which writes the wp_consent_<category> cookie and fires
// wp_listen_for_consent_change.
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

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'wp-consent-api' ] );
} );

async function expectApiPresent( page ) {
	await expect.poll( () => page.evaluate( () => typeof window.wp_has_consent === 'function' && typeof window.wp_set_consent === 'function' ), {
		message: 'WP Consent API front-end script is not loaded',
	} ).toBe( true );
}

test( 'Compatibility names WP Consent API; bridge off says "tested", bridge on says "active"', async ( { page } ) => {
	await login( page );
	await setBridge( page, false );
	await openStatusTab( page );
	await expect( compatRow( page, 'WP Consent API' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'WP Consent API' ) ).toContainText( 'tested for interoperation' );

	await setBridge( page, true );
	await openStatusTab( page );
	await expect( compatRow( page, 'WP Consent API' ) ).toContainText( 'bridge active' );
} );

test( 'THE TRAP: API plugin alone, no consent type — wp_has_consent() says yes, the bridge must not', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );
	await page.goto( GATED_PAGE + '?cg_field_cmp=0' );
	await page.waitForLoadState( 'networkidle' );
	await expectApiPresent( page );

	// Positive guard: this really is the fail-open state.
	expect( await page.evaluate( () => window.wp_has_consent( 'marketing' ) ) ).toBe( true );
	expect( await page.evaluate( () => ! window.wp_consent_type && ! window.wp_fallback_consent_type ) ).toBe( true );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );

	// And a synthetic change event cannot ungate on the default either.
	await page.evaluate( () => {
		const detail = [];
		detail.marketing = 'allow';
		document.dispatchEvent( new CustomEvent( 'wp_listen_for_consent_change', { detail } ) );
	} );
	await page.waitForLoadState( 'networkidle' );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
} );

test( 'with a consent type registered and no consent: gated, nothing requested', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );
	await page.goto( GATED_PAGE );
	await page.waitForLoadState( 'networkidle' );
	await expectApiPresent( page );
	expect( await page.evaluate( () => window.wp_consent_type ) ).toBe( 'optin' );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
} );

test( 'wp_set_consent allow auto-loads; deny re-gates; a click still works after', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( GATED_PAGE );
	await expectApiPresent( page );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );

	await page.evaluate( () => window.wp_set_consent( 'marketing', 'allow' ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 0 );
	expect( await page.evaluate( () => document.activeElement === document.body ) ).toBe( true );

	await page.evaluate( () => window.wp_set_consent( 'marketing', 'deny' ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 3 );

	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );

test( 'a stored consent loads on arrival; a clicked embed survives a later deny', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( GATED_PAGE );
	await expectApiPresent( page );
	await page.evaluate( () => window.wp_set_consent( 'marketing', 'allow' ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );

	// The cookie the API wrote is read back on the next page view.
	await page.goto( GATED_PAGE );
	await expectApiPresent( page );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );

	// Deny, then click one by hand, then deny again: the click stays.
	await page.evaluate( () => window.wp_set_consent( 'marketing', 'deny' ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
	await page.evaluate( () => window.wp_set_consent( 'marketing', 'allow' ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );
	await page.evaluate( () => window.wp_set_consent( 'marketing', 'deny' ) );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );
