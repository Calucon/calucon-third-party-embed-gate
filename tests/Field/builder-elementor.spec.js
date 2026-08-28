// Elementor (elementor), the real page builder.
//
// tests/wp/field-seed.php stores two pages the way Elementor does
// (_elementor_data + edit-mode meta): an HTML widget carrying the fixture
// corpus's YouTube iframe, and Elementor's native video widget. The readme
// FAQ says page builders render outside the content filters and need
// whole-page gating; Elementor actually renders through the_content (at
// priority 9, before this plugin's 20), so the HTML widget is expected to
// be gated with the buffer OFF as well — the test records both states so
// the FAQ can be narrowed honestly rather than left vague.
//
// The video widget is the case nothing server-side can see — and the first
// run confirmed it: Elementor's own script fetches YouTube's player API
// before any click. Its two tests are expected failures, see below.
// @ts-check
const { test, expect } = require( '@playwright/test' );
const {
	requireField,
	login,
	openStatusTab,
	compatRow,
	setOutputBuffer,
	trackThirdPartyRequests,
	abortThirdParty,
} = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'elementor' ] );
} );

async function expectElementorRendered( page ) {
	// The builder really rendered the page: its widget wrapper is there.
	// Without this, an empty page would pass every "no iframe" check.
	await expect( page.locator( '.elementor-widget' ).first(), 'Elementor did not render the page (edit-mode meta or _elementor_data not picked up)' ).toBeAttached();
}

test( 'Compatibility names Elementor, and the advice follows the buffer setting', async ( { page } ) => {
	await login( page );
	await setOutputBuffer( page, false );
	await openStatusTab( page );
	await expect( compatRow( page, 'Elementor' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'Elementor' ) ).toContainText( 'Gate the whole page output' );

	await setOutputBuffer( page, true );
	await openStatusTab( page );
	await expect( compatRow( page, 'Elementor' ) ).toContainText( 'embeds are covered' );
	await setOutputBuffer( page, false );
} );

test( 'HTML widget, buffer OFF: gated through the_content, nothing third-party requested', async ( { page } ) => {
	await login( page );
	await setOutputBuffer( page, false );
	await page.context().clearCookies(); // an anonymous visitor
	const offenders = trackThirdPartyRequests( page );
	await page.goto( '/elementor-html/' );
	await page.waitForLoadState( 'networkidle' );
	await expectElementorRendered( page );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 1 );
	await expect( page.locator( 'iframe[src*="youtube"]' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
} );

test( 'HTML widget, buffer ON: gated, nothing requested, and Load loads', async ( { page } ) => {
	await login( page );
	await setOutputBuffer( page, true );
	await page.context().clearCookies();
	const offenders = trackThirdPartyRequests( page );
	await page.goto( '/elementor-html/' );
	await page.waitForLoadState( 'networkidle' );
	await expectElementorRendered( page );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 1 );
	await expect( page.locator( 'iframe[src*="youtube"]' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );

	await abortThirdParty( page );
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed iframe' ).first() ).toHaveAttribute( 'src', /youtube-nocookie\.com/ );
} );

// The video widget — measured 2026-08-28, Elementor 4.2.3: the server
// renders <div class="elementor-widget-video" data-settings='{"video_type":
// "youtube","youtube_url":…}'> and NOTHING else — no iframe, no script tag
// naming YouTube. Elementor's own front-end JavaScript then loads
// youtube.com/iframe_api and builds the player, and the page contacts
// youtube.com, i.ytimg.com and DoubleClick before any click, with the
// buffer on or off. No HTML scanner can gate that; it is a gap in what
// "this builder's embeds are covered" promises, not a regression. Kept as an
// EXPECTED failure: the day a rule for Elementor's data-settings players
// lands, this test turns unexpectedly green and the marker comes off.
for ( const buffer of [ false, true ] ) {
	test( `video widget, buffer ${ buffer ? 'ON' : 'OFF' }: nothing reaches YouTube before a click`, async ( { page } ) => {
		test.fail( true, 'known gap: Elementor builds its video widget client-side from data-settings; nothing server-side to gate (docs/field-validation.md)' );
		await login( page );
		await setOutputBuffer( page, buffer );
		await page.context().clearCookies();
		const offenders = trackThirdPartyRequests( page );
		await page.goto( '/elementor-video/' );
		await page.waitForLoadState( 'networkidle' );
		await expectElementorRendered( page );
		// Positive guard on the shape of the gap, so a passing run means the
		// widget really was gated — not that Elementor stopped rendering it.
		await expect( page.locator( '.elementor-widget-video[data-settings*="youtube_url"]' ) ).toHaveCount( 1 );
		const youtube = offenders.filter( ( u ) => /youtube|ytimg|googlevideo|doubleclick/.test( u ) );
		test.info().annotations.push( { type: 'finding', description: `Elementor video widget, buffer ${ buffer ? 'on' : 'off' }: ${ youtube.length } request(s) to YouTube/DoubleClick before any click; panels: ${ await page.locator( '.cg-embed' ).count() }` } );
		expect( youtube ).toEqual( [] );
	} );
}

test.afterAll( async ( { browser } ) => {
	// Leave the site as the next group expects it: buffer off.
	const page = await browser.newPage();
	await login( page );
	await setOutputBuffer( page, false );
	await page.close();
} );
