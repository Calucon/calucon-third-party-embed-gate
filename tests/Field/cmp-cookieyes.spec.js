// CookieYes (cookie-law-info 3.x "lite"), the real plugin, against the
// bridge adapter.
//
// What the first real run taught, against the vendor docs the simulation
// was built from: the WordPress plugin's own script exposes
// window.getCkyConsent() (the shape the adapter reads: categories → bool,
// isUserActionCompleted) and window.revisitCkyConsent(), and fires
// cookieyes_consent_update — but it has NO performBannerAction() and never
// fires cookieyes_banner_load; those belong to CookieYes's hosted script.
// The adapter's load-time getCkyConsent() check is therefore the path that
// matters on WordPress, and consent can only be given the way a visitor
// gives it: the banner buttons.
//
// Selector liability: `.cky-btn-accept` / `.cky-btn-reject` (also tagged
// data-cky-tag="accept-button" / "reject-button") are the banner's buttons.
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

// Hosts CookieYes contacts on its own behalf. Empty until a run proves a
// need; every entry is a decision with its reason, never a default.
const COOKIEYES_OWN_HOSTS = [];

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'cookie-law-info' ] );
} );

async function expectCookieYesPresent( page ) {
	await expect.poll( () => page.evaluate( () => typeof window.getCkyConsent === 'function' ), {
		message: 'CookieYes front-end script is not loaded',
	} ).toBe( true );
	// The category the server tells the adapter to watch must exist in
	// this install, or "no consent" would be trivially true.
	const categories = await page.evaluate( () => Object.keys( window.getCkyConsent().categories ) );
	expect( categories, 'CookieYes has no "advertisement" category — BridgeConfig sends that slug for this adapter' ).toContain( 'advertisement' );
}

async function acceptAll( page ) {
	const accept = page.locator( '.cky-btn-accept' ).first();
	await expect( accept, 'CookieYes banner (accept button) not visible' ).toBeVisible();
	await accept.click();
	await expect.poll( () => page.evaluate( () => window.getCkyConsent().categories.advertisement ) ).toBe( true );
}

async function rejectAll( page ) {
	// After a choice the notice is hidden; the revisit API reopens the
	// PREFERENCES dialog, whose reject button is a different element from
	// the notice's — match whichever of the two is actually visible.
	await page.evaluate( () => window.revisitCkyConsent() );
	const reject = page.locator( ':is([data-cky-tag="reject-button"], [data-cky-tag="detail-reject-button"]):visible' ).first();
	await expect( reject, 'CookieYes banner (reject button) not visible after revisit' ).toBeVisible();
	await reject.click();
	await expect.poll( () => page.evaluate( () => window.getCkyConsent().categories.advertisement ) ).toBe( false );
}

test( 'Compatibility names CookieYes; bridge off says "tested", bridge on says "active"', async ( { page } ) => {
	await login( page );
	await setBridge( page, false );
	await openStatusTab( page );
	await expect( compatRow( page, 'CookieYes' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'CookieYes' ) ).toContainText( 'tested for interoperation' );

	await setBridge( page, true );
	await openStatusTab( page );
	await expect( compatRow( page, 'CookieYes' ) ).toContainText( 'bridge active' );
} );

test( 'bridge on, no consent: everything stays gated and nothing third-party is requested', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page, COOKIEYES_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await page.waitForLoadState( 'networkidle' );
	await expectCookieYesPresent( page );
	expect( await page.evaluate( () => window.getCkyConsent().isUserActionCompleted ) ).toBe( false );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( 'iframe[src*="youtube"], iframe[src*="example-partner"]' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
} );

test( 'accept on the real banner auto-loads; reject re-gates; a click still works after', async ( { page } ) => {
	await abortThirdParty( page, COOKIEYES_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await expectCookieYesPresent( page );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );

	await acceptAll( page );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 0 );

	await rejectAll( page );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 3 );

	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );

test( 'a clicked embed survives a rejection; a bridged one does not', async ( { page } ) => {
	await abortThirdParty( page, COOKIEYES_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await expectCookieYesPresent( page );

	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );

	await acceptAll( page );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );

	await rejectAll( page );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 2 );
} );

test( 'a stored consent loads on arrival, through getCkyConsent() alone', async ( { page } ) => {
	await abortThirdParty( page, COOKIEYES_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await expectCookieYesPresent( page );
	await acceptAll( page );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );

	// The next page view has no banner event to listen for — only the
	// stored consent read at load. No click, no event, three embeds.
	await page.goto( GATED_PAGE );
	await expectCookieYesPresent( page );
	expect( await page.evaluate( () => window.getCkyConsent().isUserActionCompleted ) ).toBe( true );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 3 );
} );
