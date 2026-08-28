// Autoptimize (autoptimize), the real plugin.
//
// Autoptimize does not cache pages; it aggregates and defers JavaScript,
// which is the setting the readme says "moves scripts away from the inline
// snippets that belong to them". The settings reader checks
// autoptimize_js + autoptimize_js_aggregate (both 'on' — Autoptimize stores
// 'on', not '1'). The load-bearing assertion is the click: gate.js inside
// Autoptimize's bundle, with its inline config left in place, must still
// load the embed — and still must when the inline config is folded into
// the bundle too (autoptimize_js_include_inline).
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
	await requireField( testInfo, [ 'autoptimize' ] );
} );

function purge() {
	// Autoptimize's own cache of aggregated files; a stale bundle would
	// mask a settings change.
	wpcli( 'autoptimize', 'clear' );
}

test( 'aggregate on: named as "combine", with the Autoptimize exclusion advice', async ( { page } ) => {
	wpcli( 'option', 'update', 'autoptimize_js_aggregate', 'on' );
	purge();
	await login( page );
	await openStatusTab( page );
	await expect( compatRow( page, 'Autoptimize' ) ).toHaveCount( 1 );
	const row = optimiserRow( page, 'Autoptimize' );
	await expect( row ).toHaveCount( 1 );
	await expect( row ).toContainText( 'combine JavaScript' );
	await expect( row ).toContainText( 'Exclude scripts from Autoptimize' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/js/gate.js' );
} );

test( 'aggregate off: settings read, nothing risky on', async ( { page } ) => {
	wpcli( 'option', 'update', 'autoptimize_js_aggregate', '' );
	purge();
	await login( page );
	await openStatusTab( page );
	const row = optimiserRow( page, 'Autoptimize' );
	await expect( row ).toContainText( 'none of the ones that cause trouble are on' );
	await expect( row ).not.toContainText( 'could not be read' );
} );

test( 'the load-bearing click: aggregated + deferred JavaScript still loads the embed', async ( { page } ) => {
	wpcli( 'option', 'update', 'autoptimize_js_aggregate', 'on' );
	wpcli( 'option', 'update', 'autoptimize_js_include_inline', '' );
	purge();
	await expectClickLoads( page );
} );

test( 'and with the inline config folded into the bundle too', async ( { page } ) => {
	wpcli( 'option', 'update', 'autoptimize_js_aggregate', 'on' );
	wpcli( 'option', 'update', 'autoptimize_js_include_inline', 'on' );
	purge();
	await expectClickLoads( page );
} );
