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
// The video widget is the case nothing server-side could see until
// ElementorVideoRule read the same JSON Elementor does — see below.
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
// "youtube","youtube_url":…}'> and NOTHING else; Elementor's own script
// then loads youtube.com/iframe_api and builds the player, and the page
// contacted youtube.com, i.ytimg.com and DoubleClick before any click, with
// the buffer on or off (14 requests, 0 panels). ElementorVideoRule closes
// that: the wrapper's contents become the panel and data-settings is
// rewritten so Elementor's handler stands down. These are the tests that
// were expected failures until it did.
for ( const buffer of [ false, true ] ) {
	test( `video widget, buffer ${ buffer ? 'ON' : 'OFF' }: gated, nothing reaches YouTube before a click, and Load loads`, async ( { page } ) => {
		await login( page );
		await setOutputBuffer( page, buffer );
		await page.context().clearCookies();
		const offenders = trackThirdPartyRequests( page );
		await page.goto( '/elementor-video/' );
		await page.waitForLoadState( 'networkidle' );
		await expectElementorRendered( page );
		// Positive guard: the widget is really there, and really rewritten.
		await expect( page.locator( '.elementor-widget-video[data-settings*="calucon-embed-gate"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.elementor-widget-video .elementor-wrapper .cg-embed' ) ).toHaveCount( 1 );
		await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
		expect( offenders.filter( ( u ) => /youtube|ytimg|googlevideo|doubleclick/.test( u ) ) ).toEqual( [] );
		// Elementor's handler must have stood down without throwing.
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );

		await abortThirdParty( page );
		await page.locator( '.cg-embed__button' ).first().click();
		await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cg-embed iframe' ).first() ).toHaveAttribute( 'src', /youtube-nocookie\.com/ );
		expect( errors ).toEqual( [] );
	} );
}

test.afterAll( async ( { browser } ) => {
	// Leave the site as the next group expects it: buffer off.
	const page = await browser.newPage();
	await login( page );
	await setOutputBuffer( page, false );
	await page.close();
} );
