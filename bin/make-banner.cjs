/**
 * Generate the WordPress.org listing banner from bin/banner.html.
 *
 *   node bin/make-banner.cjs
 *
 * Writes .wordpress-org/banner-772x250.png and banner-1544x500.png — the two
 * sizes the plugin directory asks for, the second being the same layout at
 * twice the pixel density. Needs no WordPress, unlike the screenshots.
 *
 * The source is HTML on purpose: it uses the plugin's own palette and a
 * miniature of the real placeholder, so the banner cannot drift away from
 * what the product actually looks like, and it is diffable in review.
 */
const { chromium } = require( '@playwright/test' );
const path = require( 'path' );

const SRC = path.join( __dirname, 'banner.html' );
const OUT = path.join( __dirname, '..', '.wordpress-org' );

( async () => {
	const browser = await chromium.launch();

	for ( const [ scale, file ] of [ [ 1, 'banner-772x250.png' ], [ 2, 'banner-1544x500.png' ] ] ) {
		const context = await browser.newContext( {
			viewport: { width: 772, height: 250 },
			deviceScaleFactor: scale,
		} );
		const page = await context.newPage();
		await page.goto( 'file://' + SRC );
		// Let the webfont-free layout settle before the shot.
		await page.waitForTimeout( 250 );
		await page.screenshot( { path: path.join( OUT, file ) } );
		await context.close();
	}

	await browser.close();
	console.log( 'Wrote banner-772x250.png and banner-1544x500.png to .wordpress-org/' );
} )();
