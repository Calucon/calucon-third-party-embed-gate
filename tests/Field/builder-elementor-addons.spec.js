// Essential Addons for Elementor and Premium Addons for Elementor, the real
// add-ons, on the real Elementor.
//
// Measured 2026-11-18 (Essential Addons 6.8.9, Premium Addons 4.11.120):
//
// - Essential Addons' Sticky Video is <div data-plyr-provider="youtube"
//   data-plyr-embed-id="…"> and `new Plyr()`: 21 to 26 third-party requests
//   before any click, no placeholder.
// - Premium Addons' Video Box is <div class="premium-video-box-video-
//   container" data-src="…">; its script builds the iframe on page load
//   when the box has no image or the video is set to autoplay (16 to 35
//   requests), and with its automatic image it shows the provider's own
//   thumbnail (a request to i.ytimg.com).
//
// Both are panels now (BuilderEmbedRule). tests/wp/field-seed.php stores the
// pages as _elementor_data, like the builder-elementor group.
// @ts-check
const { test, expect } = require( '@playwright/test' );
const { requireField, trackThirdPartyRequests, abortThirdParty } = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'elementor', 'essential-addons-for-elementor-lite', 'premium-addons-for-elementor' ] );
} );

/** One page: rendered by the add-on, gated, silent, and loadable with ONE click on the button. */
async function expectGatedAndLoadable( page, slug, widget, src ) {
	const offenders = trackThirdPartyRequests( page );
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
	await page.goto( `/${ slug }/` );
	await page.waitForLoadState( 'networkidle' );
	// Positive guard: the add-on's widget is really on the page.
	await expect( page.locator( widget ), `the add-on did not render ${ widget }` ).toHaveCount( 1 );
	await expect( page.locator( `${ widget } .cg-embed` ) ).toHaveCount( 1 );
	await page.waitForTimeout( 2500 );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
	expect( errors, 'the add-on\'s script must stand down without throwing' ).toEqual( [] );

	// The button is what a mouse hits at its own centre — not an overlay.
	const button = page.locator( `${ widget } .cg-embed__button` );
	await button.scrollIntoViewIfNeeded();
	const hit = await button.evaluate( ( el ) => {
		const r = el.getBoundingClientRect();
		return document.elementFromPoint( r.left + r.width / 2, r.top + r.height / 2 ) === el;
	} );
	expect( hit, 'something lies on top of the Load button' ).toBe( true );

	await abortThirdParty( page );
	await button.click();
	const frame = page.locator( `${ widget } .cg-embed iframe` );
	await expect( frame ).toHaveAttribute( 'src', src );
	const box = await frame.evaluate( ( el ) => {
		const r = el.getBoundingClientRect();
		return { width: r.width, ratio: r.width / r.height };
	} );
	expect( box.width, 'the loaded player has no usable size' ).toBeGreaterThan( 250 );
	expect( box.ratio ).toBeGreaterThan( 1.6 );
	expect( box.ratio ).toBeLessThan( 1.9 );
	expect( errors ).toEqual( [] );
}

test( 'Essential Addons Sticky Video (YouTube): a panel instead of a Plyr player', async ( { page } ) => {
	await expectGatedAndLoadable( page, 'addons-sticky-youtube', '.elementor-widget-eael-sticky-video', /youtube-nocookie\.com\/embed\/y_pjE_p1HwE/ );
	// The element scripts address by id is still there, without what Plyr reads.
	await expect( page.locator( '[id^="eaelsv-player-"]' ) ).toHaveCount( 1 );
	await expect( page.locator( '[data-plyr-provider], [data-plyr-embed-id]' ) ).toHaveCount( 0 );
} );

test( 'Essential Addons Sticky Video (Vimeo) with an image overlay: the overlay stands aside, one click loads', async ( { page } ) => {
	await expectGatedAndLoadable( page, 'addons-sticky-vimeo-overlay', '.elementor-widget-eael-sticky-video', /player\.vimeo\.com\/video\/76979871/ );
} );

for ( const [ slug, what ] of [
	[ 'addons-videobox-youtube', 'no image' ],
	[ 'addons-videobox-autoplay', 'autoplay' ],
	[ 'addons-videobox-their-image', 'the provider\'s thumbnail' ],
] ) {
	test( `Premium Addons Video Box (${ what }): the whole box is the panel`, async ( { page } ) => {
		await expectGatedAndLoadable( page, slug, '.elementor-widget-premium-addon-video-box', /youtube-nocookie\.com\/embed\/y_pjE_p1HwE/ );
		await expect( page.locator( '.premium-video-box-container' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cg-embed iframe' ) ).not.toHaveAttribute( 'src', /autoplay/ );
		expect( await page.content() ).not.toContain( 'ytimg' );
	} );
}

test( 'Premium Addons Video Box with the owner\'s own image: the image becomes the poster', async ( { page } ) => {
	await page.goto( '/addons-videobox-own-image/' );
	const poster = page.locator( '.elementor-widget-premium-addon-video-box .cg-embed img.cg-embed__poster' );
	await expect( poster ).toHaveCount( 1 );
	expect( new URL( await poster.getAttribute( 'src' ) ).hostname ).toBe( new URL( page.url() ).hostname );
	await expectGatedAndLoadable( page, 'addons-videobox-own-image', '.elementor-widget-premium-addon-video-box', /youtube-nocookie\.com\/embed\/y_pjE_p1HwE/ );
} );

test( 'Premium Addons Video Box (Vimeo): gated without asking Vimeo for anything', async ( { page } ) => {
	await expectGatedAndLoadable( page, 'addons-videobox-vimeo', '.elementor-widget-premium-addon-video-box', /player\.vimeo\.com\/video\/76979871/ );
} );

// Elementor's own video widget, a provider with a real iframe, and an image
// overlay: the panel was there, but Elementor's image lay on top of it and
// took the first click.
test( 'Elementor video widget (Vimeo) with an image overlay: one click, not two', async ( { page } ) => {
	await expectGatedAndLoadable( page, 'addons-elementor-vimeo-overlay', '.elementor-widget-video', /player\.vimeo\.com\/video\/76979871/ );
} );
