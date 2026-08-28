// W3 Total Cache (w3-total-cache), the real plugin.
//
// A full page cache (disk-enhanced) plus, for the click test, its minifier
// in auto mode. The plugin has no settings reader for W3TC — the screen must
// say so and still say where the exclusion list is — so the load-bearing
// assertions are behavioural: the cached page is the gated one, and Load
// still loads with minification on.
//
// tests/wp/field-setup.sh enables the page cache and runs W3TC's own
// fix_environment (advanced-cache.php, WP_CACHE, .htaccess rules).
// @ts-check
const { test, expect } = require( '@playwright/test' );
const {
	GATED_PAGE,
	requireField,
	login,
	openStatusTab,
	compatRow,
	optimiserRow,
	wpcli,
	expectCachedAndGated,
	expectClickLoads,
	trackThirdPartyRequests,
} = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'w3-total-cache' ] );
} );

function flush() {
	wpcli( 'w3-total-cache', 'flush', 'all' );
}

test( 'Compatibility: detected as a cache; optimiser settings honestly "could not be read", exclusion list located', async ( { page } ) => {
	await login( page );
	await openStatusTab( page );
	await expect( compatRow( page, 'W3 Total Cache' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'W3 Total Cache' ) ).toContainText( 'flushed automatically' );
	const row = optimiserRow( page, 'W3 Total Cache' );
	await expect( row ).toHaveCount( 1 );
	await expect( row ).toContainText( 'could not be read' );
	await expect( row ).toContainText( 'Never minify the following JS files' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/js/gate.js' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/js/cmp-bridge.js' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/css/gate.css' );
} );

test( 'the cached page is the gated one, and it makes no third-party request', async ( { page }, testInfo ) => {
	flush();
	await expectCachedAndGated( testInfo.project.use.baseURL );

	const offenders = trackThirdPartyRequests( page );
	await page.goto( GATED_PAGE );
	await page.waitForLoadState( 'networkidle' );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	expect( offenders ).toEqual( [] );
} );

test( 'saving the plugin settings flushes the page cache (the promise on the Compatibility row)', async ( { page }, testInfo ) => {
	flush();
	const before = await expectCachedAndGated( testInfo.project.use.baseURL );

	// Any settings save fires the flush hook W3TC is registered on.
	await login( page );
	await page.goto( '/wp-admin/options-general.php?page=calucon-embed-gate' );
	await page.click( 'form p.submit input[type="submit"], form input#submit' );
	await page.waitForURL( /options-general\.php/ );

	const after = await expectCachedAndGated( testInfo.project.use.baseURL );
	expect( after.first.marker, 'the page cache was not flushed on save' ).not.toBe( before.second.marker );
} );

test( 'the load-bearing click: with minify (auto) on, Load still loads', async ( { page } ) => {
	wpcli( 'w3-total-cache', 'option', 'set', 'minify.enabled', 'true', '--type=boolean' );
	wpcli( 'w3-total-cache', 'option', 'set', 'minify.auto', 'true', '--type=boolean' );
	flush();
	await expectClickLoads( page );
} );
