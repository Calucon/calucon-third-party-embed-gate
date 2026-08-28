// Real Cookie Banner (real-cookie-banner, free tier), the real plugin.
//
// RCB refuses the WP Consent API by design; its public contract is
// window.consentApi. The adapter calls consentApi.unblock(url) per embed and
// grants when the promise resolves — and, since the field suite's first run,
// only after unblockSync(url) has confirmed a content blocker governs the
// URL: unblock() resolves IMMEDIATELY when none does, and the free tier
// ships no YouTube blocker. Phase 1 is that site — banner on, nothing
// configured — and everything must stay gated.
//
// Phase 2 is RCB the way an owner ends up with it: RCB's own default
// content (its settings page creates the service groups on first load — no
// wp-cli path exists, so the spec visits the page), a YouTube service and a
// content blocker stored the way RCB stores them, and consent given on the
// REAL banner. Three things the first run taught, all load-bearing:
//
//   - RCB stands down for a HeadlessChrome user agent: no banner, and a
//     no-op consentApi (unblockSync → undefined) in its place. Every test
//     here runs under a normal Chrome UA, or it would be testing the stub.
//   - The banner lives in a shadow root with generated class names; its
//     buttons are driven by accessible name ("Accept all", "Continue
//     without consent") — the one selector liability in this file.
//   - Default-content creation aborts on a pre-existing term with the same
//     name ("Marketing"), so the Marketing group is created AFTER RCB's own
//     groups exist.
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
	wpcli,
} = require( './_helpers' );

// A browser RCB shows its banner to. Headless Chrome's own UA gets the
// crawler treatment: no banner, a stub API.
test.use( { userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36' } );

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

// RCB's banner buttons are <a> elements WITHOUT href inside a shadow root —
// no link role, so a role query finds nothing; the visible text is the
// stable handle, and CSS locators pierce open shadow roots.
function bannerLink( page, name ) {
	return page.locator( 'a', { hasText: new RegExp( '^\\s*' + name + '\\s*$' ) } ).first();
}

// The banner is a modal: while it is up, nothing behind it can be clicked.
// A visitor answers it first; so does the test, declining.
async function dismissBannerIfShown( page ) {
	const decline = bannerLink( page, 'Continue without consent' );
	if ( await decline.isVisible().catch( () => false ) ) {
		await decline.click();
		await page.waitForLoadState( 'networkidle' );
	}
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
		return Promise.race( [ window.consentApi.unblock( 'https://www.youtube.com/embed/y_pjE_p1HwE' ).then( () => 'resolved' ), timeout ] );
	} );
	expect( unblocksImmediately, 'expected RCB to resolve unblock() immediately for an unblocked URL (no blocker configured)' ).toBe( 'resolved' );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 3 );
	expect( offenders ).toEqual( [] );

	// And the placeholder still works by hand — once the visitor has
	// answered RCB's banner, which sits over the page until they do.
	await abortThirdParty( page, RCB_OWN_HOSTS );
	await dismissBannerIfShown( page );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );

test.describe( 'PHASE 2 — RCB set up, a YouTube service behind a content blocker', () => {
	const created = [];

	test.beforeAll( async ( { browser } ) => {
		// RCB creates its service groups and its essential service on the
		// first load of its own settings page, by an administrator holding
		// manage_real_cookie_banner (granted to administrators on
		// activation). There is no wp-cli path; visit the page once.
		const page = await browser.newPage();
		await login( page );
		await page.goto( '/wp-admin/admin.php?page=real-cookie-banner-component' );
		await page.waitForTimeout( 5000 );
		await page.close();
		expect( wpcli( 'term', 'list', 'rcb-cookie-group', '--slug=essential', '--field=term_id' ).trim(), 'RCB did not create its default service groups' ).not.toBe( '' );

		// Then ours, stored as inc/settings/Blocker.php and Cookie.php read
		// them: a group, a service in it, a blocker whose `services` meta is
		// a comma-separated list of service ids and whose rules are one per
		// line.
		let group = wpcli( 'term', 'list', 'rcb-cookie-group', '--slug=marketing', '--field=term_id' ).trim();
		if ( ! group ) {
			group = wpcli( 'term', 'create', 'rcb-cookie-group', 'Marketing', '--slug=marketing', '--porcelain' ).trim();
		}
		// CookieGroup::getOrdered() selects groups by a numeric `order` term
		// meta — a group without it is invisible to the banner, and nothing
		// in it can ever be consented to (the first banner run: "Accept all"
		// consented to nothing, unblock() stayed pending).
		wpcli( 'term', 'meta', 'update', group, 'order', '4' );
		wpcli( 'term', 'meta', 'update', group, 'isEssential', '0' );
		wpcli( 'term', 'meta', 'update', group, 'isDefault', '0' );
		const service = wpcli( 'post', 'create', '--post_type=rcb-cookie', '--post_status=publish', '--post_title=YouTube', '--post_content=Embeds YouTube videos.', '--porcelain' ).trim();
		created.push( service );
		wpcli( 'post', 'term', 'set', service, 'rcb-cookie-group', 'marketing' );
		for ( const [ key, value ] of [
			[ 'uniqueName', 'youtube' ],
			[ 'legalBasis', 'consent' ],
			[ 'provider', 'Google Ireland Limited' ],
			[ 'providerPrivacyPolicyUrl', 'https://policies.google.com/privacy' ],
			[ 'isEmbeddingOnlyExternalResources', '1' ],
		] ) {
			wpcli( 'post', 'meta', 'update', service, key, value );
		}
		for ( const [ key, json ] of [
			[ 'technicalDefinitions', '[]' ],
			[ 'codeDynamics', '[]' ],
			[ 'dataProcessingInCountries', '["US"]' ],
			[ 'dataProcessingInCountriesSpecialTreatments', '[]' ],
			[ 'googleConsentModeConsentTypes', '[]' ],
		] ) {
			wpcli( 'post', 'meta', 'update', service, key, json, '--format=json' );
		}
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

	test( 'the API contract: unblockSync() names the blocker for a governed URL only; unblock() waits', async ( { page } ) => {
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

	test( 'the real banner: "Continue without consent" leaves everything gated; nothing requested', async ( { page } ) => {
		const offenders = trackThirdPartyRequests( page, RCB_OWN_HOSTS );
		await page.goto( GATED_PAGE );
		await expectRcbPresent( page );
		const decline = bannerLink( page, 'Continue without consent' );
		await expect( decline, 'RCB banner not shown (UA? banner inactive? no service?)' ).toBeVisible();
		await decline.click();
		await page.waitForLoadState( 'networkidle' );

		await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
		await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
		expect( offenders ).toEqual( [] );
	} );

	test( 'the real banner: "Accept all" auto-loads the governed embeds, and the stored consent loads them on the next visit', async ( { page } ) => {
		await abortThirdParty( page, RCB_OWN_HOSTS );
		await page.goto( GATED_PAGE );
		await expectRcbPresent( page );
		await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );

		const accept = bannerLink( page, 'Accept all' );
		await expect( accept, 'RCB banner not shown (UA? banner inactive? no service?)' ).toBeVisible();
		await accept.click();
		// The blocker covers the two YouTube embeds; the unknown-host widget
		// is nobody's service and stays gated — per-URL, as RCB answers.
		await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 2 );
		await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 1 );
		expect( await page.evaluate( () => document.activeElement === document.body ) ).toBe( true );

		// Stored consent: no banner, no click — unblock() resolves at once
		// for a consented URL, and only then.
		await page.goto( GATED_PAGE );
		await expectRcbPresent( page );
		await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 2 );
		await expect( page.locator( '.cg-embed__button' ) ).toHaveCount( 1 );
	} );
} );
