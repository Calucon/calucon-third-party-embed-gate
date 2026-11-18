// Beaver Builder Lite (beaver-builder-lite-version), the real page builder.
//
// tests/wp/field-seed.php builds four pages through FLBuilderModel's own
// API. Measured 2026-11-18 on 2.11.2.2, before BackgroundVideoRule knew
// Beaver: a row with a YouTube or Vimeo background made 28 to 31 third-party
// requests before any click — the row is
// <div class="fl-bg-video" data-youtube="…" data-video-id="…">, built into
// a player by fl-builder-layout.js, plus the provider's player API as an
// ordinary enqueued script tag in the footer, outside every content filter.
// The "embedded code" background was gated, but its panel sat behind the
// row's content.
// @ts-check
const { test, expect } = require( '@playwright/test' );
const { requireField, trackThirdPartyRequests, abortThirdParty } = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'beaver-builder-lite-version' ] );
} );

for ( const [ slug, what ] of [
	[ 'beaver-background-youtube', 'a YouTube row background' ],
	[ 'beaver-background-vimeo', 'a Vimeo row background' ],
	[ 'beaver-background-embed', 'an embedded-code row background' ],
] ) {
	test( `${ what }: nothing is requested, no panel behind the row, the row keeps its content`, async ( { page } ) => {
		const offenders = trackThirdPartyRequests( page );
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
		await page.goto( `/${ slug }/` );
		await page.waitForLoadState( 'networkidle' );
		// Positive guard: Beaver Builder rendered a row with a video or
		// embed background (the class is on the row itself, and stays).
		await expect( page.locator( '.fl-row.fl-row-bg-video, .fl-row.fl-row-bg-embed' ), 'Beaver Builder did not render a background row' ).toHaveCount( 1 );
		// The layout script polls for the player API; give a script that did
		// NOT stand down the time to show itself.
		await page.waitForTimeout( 2500 );
		expect( offenders ).toEqual( [] );
		expect( errors, 'Beaver Builder\'s layout script must not throw' ).toEqual( [] );
		await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cg-embed' ) ).toHaveCount( 0 );
		// Neither half of the background is left: the element its script
		// builds from, and the player API it enqueues.
		await expect( page.locator( '.fl-bg-video, .fl-bg-embed-code' ) ).toHaveCount( 0 );
		await expect( page.locator( 'script[src*="youtube.com"], script[src*="vimeo.com"]' ) ).toHaveCount( 0 );
		await expect( page.getByRole( 'heading', { name: 'Over a background video' } ) ).toBeVisible();
	} );
}

test( 'the video module (an embed code) is gated like any embed, and Load loads', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );
	await page.goto( '/beaver-video-module/' );
	await page.waitForLoadState( 'networkidle' );
	await expect( page.locator( '.fl-module-video .cg-embed' ) ).toHaveCount( 1 );
	await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );

	await abortThirdParty( page );
	await page.locator( '.cg-embed__button' ).click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveAttribute( 'src', /youtube-nocookie\.com/ );
} );
