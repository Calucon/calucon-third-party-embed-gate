// LiteSpeed Cache (litespeed-cache), the real plugin.
//
// This is the group that exposed a two-release-old defect by being planned:
// the settings reader asked for `litespeed.optm.js_defer`, a key no version
// of LiteSpeed ever wrote (they are `litespeed.conf.<id>`), and the seed
// emulator mirrored the same wrong key, so the emulated test was green while
// every real install rendered "could not be read". These tests drive the
// real option rows.
//
// LiteSpeed's PAGE cache needs a LiteSpeed web server and is a no-op under
// Apache, so only the optimiser half is validated here; the cached-page
// claim is covered by the other cache groups. `js_defer` is 0 = off,
// 1 = deferred, 2 = delayed until interaction.
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
	expectClickLoads,
	abortThirdParty,
	fetchAnonymous,
} = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'litespeed-cache' ] );
} );

function setOptimiser( { defer, comb } ) {
	wpcli( 'option', 'update', 'litespeed.conf.optm-js_defer', String( defer ) );
	wpcli( 'option', 'update', 'litespeed.conf.optm-js_comb', String( comb ) );
	// LiteSpeed's `wp litespeed-purge` curls the site URL, which from inside
	// the cli container is nobody; fire the plugin's own purge action
	// instead. Under Apache there is no page cache to purge and the
	// optimised assets are content-hashed, so this is belt and braces.
	try {
		wpcli( 'eval', 'do_action( "litespeed_purge_all" );' );
	} catch ( e ) {
		// Nothing to purge on this server: fine.
	}
}

test( 'settings READ, nothing risky on: the screen says so — not "could not be read"', async ( { page } ) => {
	setOptimiser( { defer: 0, comb: 0 } );
	await login( page );
	await openStatusTab( page );
	await expect( compatRow( page, 'LiteSpeed Cache' ) ).toHaveCount( 1 );
	const row = optimiserRow( page, 'LiteSpeed Cache' );
	await expect( row ).toHaveCount( 1 );
	await expect( row ).toContainText( 'none of the ones that cause trouble are on' );
	await expect( row ).not.toContainText( 'could not be read' );
	await expect( row ).toContainText( 'Where to exclude them:' );
} );

test( 'combine on: named as such, and the exclusion files are listed', async ( { page } ) => {
	setOptimiser( { defer: 1, comb: 1 } );
	await login( page );
	await openStatusTab( page );
	const row = optimiserRow( page, 'LiteSpeed Cache' );
	await expect( row ).toContainText( 'combine JavaScript' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/js/gate.js' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/js/cmp-bridge.js' );
	await expect( page.locator( 'pre.cg-exclusions' ) ).toContainText( 'assets/css/gate.css' );
} );

test( 'delay-until-interaction: named as the setting that costs a click', async ( { page } ) => {
	setOptimiser( { defer: 2, comb: 0 } );
	await login( page );
	await openStatusTab( page );
	const row = optimiserRow( page, 'LiteSpeed Cache' );
	await expect( row ).toContainText( 'delay JavaScript until the visitor interacts' );
} );

test( 'the load-bearing click: with deferred + combined JavaScript, Load still loads', async ( { page } ) => {
	setOptimiser( { defer: 1, comb: 1 } );
	await expectClickLoads( page, { clicks: 1 } );
} );

// "Delay JavaScript until interaction" — what a real LiteSpeed does with
// gate.js is turn it into <script type="litespeed/javascript" data-src>
// and release it on the first of mouseover / click / keydown / wheel /
// touchmove / touchstart / pointerup / pointerdown (its js_delay loader; the
// list is window.litespeed_ui_events, overridable). Two things the first
// run of this suite measured:
//
//  - Chromium fires a synthetic mouseover for the parked cursor ~200 ms
//    after navigation, with no interaction at all, in desktop AND mobile
//    emulation. A mouse user hovers before clicking anyway, so on the
//    desktop the delay is over before the click lands — one click loads.
//  - A real phone fires no mouseover; the tap that releases the script is
//    the same gesture as the click. That is the documented "first click
//    does nothing" symptom, and the touch test emulates the pointer-less
//    device by taking mouseover out of LiteSpeed's own event list.
test( 'delay-until-interaction, mouse: the hover releases the script, one click loads', async ( { page }, testInfo ) => {
	setOptimiser( { defer: 2, comb: 0 } );
	// Positive guard on the RAW HTML (the DOM has already been released by
	// the synthetic mouseover by the time anyone looks at it).
	const raw = await fetchAnonymous( testInfo.project.use.baseURL, GATED_PAGE );
	expect( raw.body, 'LiteSpeed did not rewrite gate.js as a delayed script — is optm-js_defer=2 in effect?' )
		.toMatch( /<script[^>]*id="calucon-embed-gate-js"[^>]*type="litespeed\/javascript"[^>]*data-src=/ );
	await expectClickLoads( page, { clicks: 1 } );
} );

test.describe( 'delay-until-interaction, touch', () => {
	test.use( { hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } } );

	test( 'the first tap is spent releasing the scripts; a second tap loads', async ( { page } ) => {
		setOptimiser( { defer: 2, comb: 0 } );
		// A phone has no cursor to hover with: remove the event Chromium
		// synthesises for one, so the delay is observable as a visitor
		// on a touch device experiences it.
		await page.addInitScript( () => {
			window.litespeed_ui_events = [ 'click', 'keydown', 'wheel', 'touchmove', 'touchstart', 'pointerup', 'pointerdown' ];
		} );
		await abortThirdParty( page );
		await page.goto( GATED_PAGE );
		await page.waitForLoadState( 'networkidle' );
		await expect( page.locator( 'script#calucon-embed-gate-js[type="litespeed/javascript"][data-src]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );

		const button = page.locator( '.cg-embed__button' ).first();
		await button.tap();
		// Either the tap was swallowed (the documented symptom) or the script
		// won the race; what may NOT happen is a broken page. Record which.
		await page.waitForTimeout( 500 );
		const afterFirst = await page.locator( '.cg-embed iframe' ).count();
		test.info().annotations.push( { type: 'finding', description: `LiteSpeed delay, touch: iframes after first tap = ${ afterFirst }` } );
		if ( afterFirst === 0 ) {
			await button.tap();
		}
		await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cg-embed iframe' ).first() ).toHaveAttribute( 'src', /youtube-nocookie\.com/ );
	} );
} );
