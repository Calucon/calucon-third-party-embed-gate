// SiteGround Optimizer (sg-cachepress), the real plugin.
//
// The settings reader checks siteground_optimizer_combine_javascript. Its
// file-based page cache may or may not enable off SiteGround servers; that
// is a "verify on first run" and, if it does not, the group validates the
// optimiser half only (the cached-page claim is carried by the other cache
// groups).
// @ts-check
const { test, expect } = require( '@playwright/test' );
const {
	requireField,
	login,
	openStatusTab,
	compatRow,
	optimiserRow,
	wpcli,
	expectClickLoads,
} = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'sg-cachepress' ] );
} );

function setCombine( on ) {
	wpcli( 'option', 'update', 'siteground_optimizer_combine_javascript', on ? '1' : '0' );
	// Its optimiser keeps combined assets on disk; purge so a settings
	// change is not masked by a stale bundle.
	try {
		wpcli( 'sg', 'purge' );
	} catch ( e ) {
		// No SG cache to purge off SiteGround hosting — fine.
	}
}

test( 'combine on: named as "combine", with the SG Optimizer menu path', async ( { page } ) => {
	setCombine( true );
	await login( page );
	await openStatusTab( page );
	await expect( compatRow( page, 'SiteGround Optimizer' ) ).toHaveCount( 1 );
	const row = optimiserRow( page, 'SiteGround Optimizer' );
	await expect( row ).toHaveCount( 1 );
	await expect( row ).toContainText( 'combine JavaScript' );
	await expect( row ).toContainText( 'Frontend → JavaScript' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/js/gate.js' );
} );

test( 'combine off: settings read, nothing risky on', async ( { page } ) => {
	setCombine( false );
	await login( page );
	await openStatusTab( page );
	const row = optimiserRow( page, 'SiteGround Optimizer' );
	await expect( row ).toContainText( 'none of the ones that cause trouble are on' );
	await expect( row ).not.toContainText( 'could not be read' );
} );

test( 'the load-bearing click: with combine JS on, Load still loads', async ( { page } ) => {
	setCombine( true );
	await expectClickLoads( page );
} );
