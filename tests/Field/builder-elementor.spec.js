// Elementor (elementor), the real page builder.
//
// tests/wp/field-seed.php stores pages the way Elementor does
// (_elementor_data + edit-mode meta): an HTML widget carrying the fixture
// corpus's YouTube iframe, Elementor's native video widget, and — further
// down — the atomic elements and the background videos. The readme
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

// The atomic YouTube element (Editor V4) — measured 2026-11-17, Elementor
// 4.4.1, on a fresh install where atomic elements are on by default: the
// server renders an empty <div data-e-type="e-youtube" data-settings=…> and
// its handler script loads youtube.com/iframe_api — 19 third-party requests
// and 0 panels, with the buffer on or off. The classic widget's tests above
// stayed green throughout: a different element, a different script. Privacy
// mode is no way out (the player API still comes from youtube.com), and lazy
// load only waits for the element to scroll into view.
const ATOMIC = '[data-e-type="e-youtube"]';
for ( const buffer of [ false, true ] ) {
	for ( const slug of [ 'elementor-atomic-youtube', 'elementor-atomic-youtube-privacy' ] ) {
		test( `atomic YouTube element (${ slug }), buffer ${ buffer ? 'ON' : 'OFF' }: gated, nothing reaches YouTube before a click, and Load loads`, async ( { page } ) => {
			await login( page );
			await setOutputBuffer( page, buffer );
			await page.context().clearCookies();
			const offenders = trackThirdPartyRequests( page );
			const errors = [];
			page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
			await page.goto( `/${ slug }/` );
			await page.waitForLoadState( 'networkidle' );
			// Positive guard: Elementor really rendered the ATOMIC element.
			// If a later Elementor renames it, this fails loudly instead of
			// passing on a page with nothing to gate.
			await expect( page.locator( ATOMIC ), 'Elementor did not render an atomic e-youtube element (atomic elements off, or the element was renamed)' ).toHaveCount( 1 );
			await expect( page.locator( `${ ATOMIC } > .cg-embed` ) ).toHaveCount( 1 );
			// The handler polls for the player API every 350 ms; give a
			// handler that did NOT stand down the time to show itself.
			await page.waitForTimeout( 2000 );
			await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
			expect( offenders ).toEqual( [] );
			expect( errors, 'Elementor\'s handler must stand down without throwing' ).toEqual( [] );

			// The panel is not caged in Elementor's 16/9 box: its link is
			// inside the panel's own box, on a phone as well.
			await page.setViewportSize( { width: 360, height: 800 } );
			const clipped = await page.evaluate( ( sel ) => {
				const host = document.querySelector( sel );
				const link = host.querySelector( '.cg-embed__fallback a' ).getBoundingClientRect();
				const box = host.getBoundingClientRect();
				return link.bottom > box.bottom + 1 || getComputedStyle( host ).overflow !== 'visible';
			}, ATOMIC );
			expect( clipped, 'the fallback link is clipped by Elementor\'s aspect-ratio box' ).toBe( false );

			await abortThirdParty( page );
			await page.locator( '.cg-embed__button' ).first().click();
			await expect( page.locator( `${ ATOMIC } .cg-embed iframe` ) ).toHaveCount( 1 );
			await expect( page.locator( `${ ATOMIC } .cg-embed iframe` ).first() ).toHaveAttribute( 'src', /youtube-nocookie\.com\/embed\/y_pjE_p1HwE/ );
			// The loaded player fills Elementor's box, as Elementor's own would.
			const fills = await page.evaluate( ( sel ) => {
				const host = document.querySelector( sel ).getBoundingClientRect();
				const frame = document.querySelector( sel + ' iframe' ).getBoundingClientRect();
				return Math.abs( host.width - frame.width ) <= 1 && Math.abs( host.height - frame.height ) <= 1 && frame.height > 50;
			}, ATOMIC );
			expect( fills, 'the loaded player does not fill the element' ).toBe( true );
			expect( errors ).toEqual( [] );
		} );
	}
}

// The atomic Google Maps element renders a real iframe (maps.google.com/maps
// ?q=…&output=embed), which the iframe rule gates like any other. Kept as a
// test because "renders a real iframe" is Elementor's decision, not ours.
test( 'atomic Google Maps element: gated, nothing requested', async ( { page } ) => {
	await login( page );
	await setOutputBuffer( page, false );
	await page.context().clearCookies();
	const offenders = trackThirdPartyRequests( page );
	await page.goto( '/elementor-atomic-maps/' );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 1000 );
	await expect( page.locator( '.cg-embed[data-cg-provider="google-maps"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
} );

// Background videos — measured 2026-11-18, Elementor 4.4.1: a section or a
// container with a YouTube or Vimeo background is a `background_video_link`
// setting and an empty container div; Elementor's background-video.js loads
// the provider's player API and builds the player — 30 to 39 third-party
// requests before any click, buffer on or off. There is nowhere to put a
// usable panel behind a section's content, so BackgroundVideoRule empties
// the setting and the video does not load at all.
for ( const buffer of [ false, true ] ) {
	for ( const [ slug, provider ] of [ [ 'elementor-background-section', 'youtube' ], [ 'elementor-background-container', 'vimeo' ] ] ) {
		test( `background video (${ slug }), buffer ${ buffer ? 'ON' : 'OFF' }: nothing reaches ${ provider }, and the section keeps its content`, async ( { page } ) => {
			await login( page );
			await setOutputBuffer( page, buffer );
			await page.context().clearCookies();
			const offenders = trackThirdPartyRequests( page );
			const errors = [];
			page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
			await page.goto( `/${ slug }/` );
			await page.waitForLoadState( 'networkidle' );
			// Positive guard: Elementor rendered a VIDEO background — its
			// container is there, and the setting says "video".
			await expect( page.locator( '.elementor-background-video-container' ), 'Elementor did not render a background-video container' ).toHaveCount( 1 );
			await expect( page.locator( '[data-settings*="background_background"][data-settings*="video"]' ) ).toHaveCount( 1 );
			await page.waitForTimeout( 2000 );
			await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
			expect( offenders ).toEqual( [] );
			expect( errors, 'Elementor\'s handler must stand down without throwing' ).toEqual( [] );
			await expect( page.getByRole( 'heading', { name: 'Over a background video' } ) ).toBeVisible();
			// No panel: one behind a section's content could not be used.
			await expect( page.locator( '.cg-embed' ) ).toHaveCount( 0 );
		} );
	}
}

test.afterAll( async ( { browser } ) => {
	// Leave the site as the next group expects it: buffer off.
	const page = await browser.newPage();
	await login( page );
	await setOutputBuffer( page, false );
	await page.close();
} );
