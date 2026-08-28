// WP Fastest Cache (wp-fastest-cache), the real plugin.
//
// No settings reader exists for it (its settings live in one JSON option,
// WpFastestCache), so the screen must say "could not be read" and point at
// the Exclude tab. Behaviour: cached page is the gated one; Load still loads
// with its minify + combine on.
//
// WPFC serves its cache through .htaccess rules it writes from its own
// admin form; tests/wp/field-setup.sh sets the option directly, so if the
// marker test reports "not served from cache" the honest fix is to drive
// that form (recorded as a setup liability), not to weaken the test.
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
	await requireField( testInfo, [ 'wp-fastest-cache' ] );
} );

function flush() {
	wpcli( 'fastest-cache', 'clear', 'all', 'and', 'minified' );
}

test( 'Compatibility: detected; optimiser settings honestly "could not be read"; Exclude tab named', async ( { page } ) => {
	await login( page );
	await openStatusTab( page );
	await expect( compatRow( page, 'WP Fastest Cache' ) ).toHaveCount( 1 );
	const row = optimiserRow( page, 'WP Fastest Cache' );
	await expect( row ).toHaveCount( 1 );
	await expect( row ).toContainText( 'could not be read' );
	await expect( row ).toContainText( 'Exclude tab' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/js/gate.js' );
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

test( 'the load-bearing click: with minify + combine JS on, Load still loads', async ( { page } ) => {
	flush();
	await expectClickLoads( page );
} );
