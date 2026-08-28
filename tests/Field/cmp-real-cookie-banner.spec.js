// Real Cookie Banner (real-cookie-banner, free tier), the real plugin.
//
// RCB refuses the WP Consent API by design; its public contract is
// window.consentApi. The adapter calls consentApi.unblock(url) per embed and
// grants when the promise resolves. RCB documents that promise as resolving
// "only after given consent" WHEN a content blocker matches the URL — and
// "immediately" when none does. The free tier ships no YouTube blocker; an
// owner has to create one. So the first, load-bearing question is what the
// bridge does on a site where RCB is active and no blocker matches: with
// the adapter as written before 1.0, every gated embed would auto-load with
// no consent at all. Phase 1 asserts it must not.
//
// Phase 2 creates a YouTube content blocker the way RCB stores one (an
// rcb-blocker post whose `services` meta names an rcb-cookie service) and
// checks the contract the adapter now rests on: unblockSync() returns the
// blocker for a governed URL and nothing for another, unblock() stays
// pending until consent, and nothing auto-loads. Consent through RCB's own
// banner is NOT driven here: RCB renders no banner (and refuses even the
// administrator its admin screen) until its setup wizard has run, and
// reproducing that wizard is a follow-up — docs/field-validation.md.
// @ts-check
const { test, expect } = require( '@playwright/test' );
const {
	GATED_PAGE,
	requireField,
	login,
	openStatusTab,
	compatRow,
	trackThirdPartyRequests,
	setBridge,
	wpcli,
} = require( './_helpers' );

// Hosts RCB contacts on its own behalf. Empty until a run proves a need.
const RCB_OWN_HOSTS = [];

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'real-cookie-banner' ] );
} );

async function expectRcbPresent( page ) {
	await expect.poll( () => page.evaluate( () => !! ( window.consentApi && typeof window.consentApi.unblock === 'function' ) ), {
		message: 'Real Cookie Banner front-end API (window.consentApi) is not present — banner not active?',
	} ).toBe( true );
}

test( 'Compatibility names Real Cookie Banner; bridge off says "tested", bridge on says "active"', async ( { page } ) => {
	await login( page );
	await setBridge( page, false );
	await openStatusTab( page );
	await expect( compatRow( page, 'Real Cookie Banner' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'Real Cookie Banner' ) ).toContainText( 'tested for interoperation' );

	await setBridge( page, true );
	await openStatusTab( page );
	await expect( compatRow( page, 'Real Cookie Banner' ) ).toContainText( 'bridge active' );
} );

test( 'PHASE 1 — bridge on, RCB active, NO content blocker for YouTube: everything stays gated', async ( { page } ) => {
	// This is the site an owner gets by installing RCB and switching the
	// bridge on: RCB's free tier has no YouTube blocker out of the box.
	// consentApi.unblock() resolves immediately for an unblocked URL; the
	// bridge must not read that as consent.
	const offenders = trackThirdPartyRequests( page, RCB_OWN_HOSTS );
	await page.goto( GATED_PAGE );
	await page.waitForLoadState( 'networkidle' );
	await expectRcbPresent( page );

	// Positive guard: RCB really has no blocker for this URL.
	const unblocksImmediately = await page.evaluate( async () => {
		const timeout = new Promise( ( resolve ) => setTimeout( () => resolve( 'pending' ), 1500 ) );
		const result = await Promise.race( [ window.consentApi.unblock( 'https://www.youtube.com/embed/y_pjE_p1HwE' ).then( () => 'resolved' ), timeout ] );
		return result;
	} );
	expect( unblocksImmediately, 'expected RCB to resolve unblock() immediately for an unblocked URL (no blocker configured)' ).toBe( 'resolved' );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 3 );
	expect( offenders ).toEqual( [] );

	// And the placeholder still works by hand.
	await page.route( '**', ( route ) => ( [ '127.0.0.1', 'localhost' ].includes( new URL( route.request().url() ).hostname ) ? route.continue() : route.abort() ) );
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );

test.describe( 'PHASE 2 — a YouTube content blocker exists', () => {
	const created = [];

	test.beforeAll( () => {
		// RCB's storage, as read by inc/settings/Blocker.php: rules one per
		// line, `services` a comma-separated list of rcb-cookie post ids.
		let group = wpcli( 'term', 'list', 'rcb-cookie-group', '--slug=marketing', '--field=term_id' ).trim();
		if ( ! group ) {
			group = wpcli( 'term', 'create', 'rcb-cookie-group', 'Marketing', '--slug=marketing', '--porcelain' ).trim();
		}
		const service = wpcli( 'post', 'create', '--post_type=rcb-cookie', '--post_status=publish', '--post_title=YouTube', '--porcelain' ).trim();
		created.push( service );
		wpcli( 'post', 'term', 'set', service, 'rcb-cookie-group', 'marketing' );
		wpcli( 'post', 'meta', 'update', service, 'uniqueName', 'youtube' );
		wpcli( 'post', 'meta', 'update', service, 'legalBasis', 'consent' );
		wpcli( 'post', 'meta', 'update', service, 'provider', 'Google Ireland Limited' );
		const blocker = wpcli( 'post', 'create', '--post_type=rcb-blocker', '--post_status=publish', '--post_title=YouTube', '--porcelain' ).trim();
		created.push( blocker );
		wpcli( 'post', 'meta', 'update', blocker, 'rules', '*youtube.com*\n*youtube-nocookie.com*' );
		wpcli( 'post', 'meta', 'update', blocker, 'criteria', 'services' );
		wpcli( 'post', 'meta', 'update', blocker, 'services', service );
		wpcli( 'post', 'meta', 'update', blocker, 'isVisual', '0' );
	} );

	test.afterAll( () => {
		for ( const id of created ) {
			wpcli( 'post', 'delete', id, '--force' );
		}
	} );

	test( 'unblockSync() names the blocker for a governed URL only; unblock() waits; nothing auto-loads', async ( { page } ) => {
		const offenders = trackThirdPartyRequests( page, RCB_OWN_HOSTS );
		await page.goto( GATED_PAGE );
		await page.waitForLoadState( 'networkidle' );
		await expectRcbPresent( page );

		const shape = await page.evaluate( async () => {
			const api = window.consentApi;
			const governed = api.unblockSync( 'https://www.youtube.com/embed/y_pjE_p1HwE' );
			const other = api.unblockSync( 'https://widgets.example-partner.com/embed/9' );
			const timeout = new Promise( ( resolve ) => setTimeout( () => resolve( 'pending' ), 1500 ) );
			const waits = await Promise.race( [ api.unblock( 'https://www.youtube.com/embed/y_pjE_p1HwE' ).then( () => 'resolved' ), timeout ] );
			return { governed: !! governed, governedName: governed && governed.name, other: other === undefined, waits };
		} );
		expect( shape.governed, 'unblockSync() did not return the YouTube blocker' ).toBe( true );
		expect( shape.governedName ).toBe( 'YouTube' );
		expect( shape.other, 'unblockSync() matched a URL no blocker covers' ).toBe( true );
		expect( shape.waits, 'unblock() must stay pending until consent when a blocker matches' ).toBe( 'pending' );

		await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
		await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
		expect( offenders ).toEqual( [] );
	} );
} );
