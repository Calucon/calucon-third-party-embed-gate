/**
 * Generate the WordPress.org listing screenshots from a running WordPress.
 *
 * Boot the same backend the WP integration suite uses, then run this:
 *
 *   bash tests/wp/serve-playground.sh &      # or npm run test:wp backend
 *   node bin/capture-screenshots.cjs
 *
 * Writes .wordpress-org/screenshot-{1..4}.png, matching the readme's
 * == Screenshots == captions in order. The fifth caption (block-editor
 * control) is captured better from a real editing session and is left to add
 * by hand. Not part of CI — this is a one-off asset generator.
 */
const { chromium } = require( '@playwright/test' );
const path = require( 'path' );

const BASE = process.env.WP_BASE_URL || 'http://127.0.0.1:8890';
const OUT = path.join( __dirname, '..', '.wordpress-org' );

async function login( page ) {
	await page.goto( BASE + '/wp-login.php', { waitUntil: 'networkidle' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
}

async function settings( page ) {
	await page.goto( BASE + '/wp-admin/options-general.php?page=consent-gate', { waitUntil: 'networkidle' } );
	await page.waitForSelector( '.cg-tabs' );
}

( async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage( { viewport: { width: 1360, height: 1000 }, deviceScaleFactor: 2 } );

	// 1 — front-end placeholder (the product in one image). Use the
	// no-poster page so no media-library image is expected (Playground has
	// none, which would render a broken-image icon).
	await page.goto( BASE + '/gated-classic/', { waitUntil: 'networkidle' } );
	const embed = page.locator( '.cg-embed' ).first();
	await embed.scrollIntoViewIfNeeded();
	await embed.screenshot( { path: path.join( OUT, 'screenshot-1.png' ) } );

	await login( page );

	// 2 — Appearance: pickers + live preview + contrast report.
	await settings( page );
	await page.click( '#cg-tabbtn-appearance' );
	await page.waitForTimeout( 700 );
	await page.locator( '#cg-tab-appearance' ).screenshot( { path: path.join( OUT, 'screenshot-2.png' ) } );

	// 3 — Compatibility overview (under Status & tools).
	await page.click( '#cg-tabbtn-status' );
	await page.waitForTimeout( 500 );
	await page.locator( '#cg-tab-status' ).screenshot( { path: path.join( OUT, 'screenshot-3.png' ) } );

	// 4 — Providers tab. The full table is very tall (20+ providers); clip to
	// the top so the shot shows the columns and the first several providers.
	await page.click( '#cg-tabbtn-providers' );
	await page.waitForTimeout( 400 );
	const box = await page.locator( '#cg-tab-providers' ).boundingBox();
	await page.screenshot( {
		path: path.join( OUT, 'screenshot-4.png' ),
		clip: { x: box.x, y: box.y, width: box.width, height: Math.min( box.height, 900 ) },
	} );

	await browser.close();
	console.log( 'Wrote screenshot-1..4.png to .wordpress-org/' );
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
