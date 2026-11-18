// Kadence Blocks (kadence-blocks), the real block library.
//
// Its Row Layout block can take a YouTube or Vimeo video as its background.
// Kadence renders that as a real iframe —
//   <div class="kb-bg-video-iframe …"><iframe src="https://www.youtube.com/embed/…?autoplay=1&mute=1…">
// — so it was always gated; but the panel sat behind the row's columns,
// clipped and covered (measured 2026-11-18, Kadence Blocks 3.9.1.1). A
// placeholder nobody can press is not a placeholder: BackgroundVideoRule
// removes the background, as it does for the Cover block.
// @ts-check
const { test, expect } = require( '@playwright/test' );
const { requireField, trackThirdPartyRequests } = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'kadence-blocks' ] );
} );

for ( const provider of [ 'youtube', 'vimeo' ] ) {
	test( `a Row Layout with a ${ provider } background: nothing requested, no panel behind the row`, async ( { page } ) => {
		const offenders = trackThirdPartyRequests( page );
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
		await page.goto( `/kadence-background-${ provider }/` );
		await page.waitForLoadState( 'networkidle' );
		// Positive guard: Kadence rendered a row with a VIDEO background —
		// the container it puts the player in is still there, empty.
		await expect( page.locator( '.kb-row-layout-wrap > .kb-blocks-bg-video-container' ), 'Kadence did not render a row with a video background' ).toHaveCount( 1 );
		await page.waitForTimeout( 1500 );
		expect( offenders ).toEqual( [] );
		expect( errors ).toEqual( [] );
		await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cg-embed' ) ).toHaveCount( 0 );
		await expect( page.locator( '.kb-bg-video-iframe' ) ).toHaveCount( 0 );
		await expect( page.getByRole( 'heading', { name: 'Over a background video' } ) ).toBeVisible();
	} );
}
