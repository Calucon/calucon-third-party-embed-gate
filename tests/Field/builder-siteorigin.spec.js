// SiteOrigin Page Builder + Widgets Bundle (siteorigin-panels,
// so-widgets-bundle), the real plugins — and WordPress's own [video]
// shortcode, which shares the mechanism.
//
// Measured 2026-11-18 (Widgets Bundle 1.76.0): the video widget with its
// "oEmbed" option off renders
//   <video class="sow-video-widget"><source src="https://www.youtube.com/watch?v=…">
// — a provider's PAGE as a media source. The browser asks the provider for
// that "file", and MediaElement.js, WordPress's own player, then loads the
// provider's player API and builds the player: 20 to 25 third-party requests
// before any click, no placeholder. No rule read <video>. BuilderEmbedRule
// now turns such an element into a panel; a <video> with a real file is
// media and is left alone.
// @ts-check
const { test, expect } = require( '@playwright/test' );
const { requireField, trackThirdPartyRequests, abortThirdParty } = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'siteorigin-panels', 'so-widgets-bundle' ] );
} );

for ( const [ slug, wrapper, src ] of [
	[ 'siteorigin-video-youtube', '.so-widget-sow-video', /youtube-nocookie\.com\/embed\/y_pjE_p1HwE/ ],
	[ 'siteorigin-video-vimeo', '.so-widget-sow-video', /player\.vimeo\.com\/video\/76979871/ ],
	[ 'core-video-shortcode', '.wp-video', /youtube-nocookie\.com\/embed\/y_pjE_p1HwE/ ],
] ) {
	test( `${ slug }: a provider page as a <video> source is gated, and Load loads a player that fits`, async ( { page } ) => {
		const offenders = trackThirdPartyRequests( page );
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
		await page.goto( `/${ slug }/` );
		await page.waitForLoadState( 'networkidle' );
		// Positive guard: the widget (or the shortcode) rendered its wrapper.
		await expect( page.locator( wrapper ), 'the video wrapper was not rendered' ).toHaveCount( 1 );
		await expect( page.locator( `${ wrapper } .cg-embed` ) ).toHaveCount( 1 );
		await page.waitForTimeout( 2000 );
		await expect( page.locator( 'video, iframe' ) ).toHaveCount( 0 );
		expect( offenders ).toEqual( [] );
		expect( errors ).toEqual( [] );

		await abortThirdParty( page );
		await page.setViewportSize( { width: 390, height: 800 } );
		await page.locator( '.cg-embed__button' ).click();
		const frame = page.locator( '.cg-embed iframe' );
		await expect( frame ).toHaveAttribute( 'src', src );
		// The <video> that sized the player is gone; the gate sizes it — to
		// the width it has, at the video's ratio, on a phone as well.
		const box = await frame.evaluate( ( el ) => {
			const r = el.getBoundingClientRect();
			return { width: r.width, ratio: r.width / r.height, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1 };
		} );
		expect( box.width ).toBeGreaterThan( 250 );
		expect( box.ratio ).toBeGreaterThan( 1.6 );
		expect( box.ratio ).toBeLessThan( 1.9 );
		expect( box.overflow, 'the page scrolls sideways' ).toBe( false );
	} );
}
