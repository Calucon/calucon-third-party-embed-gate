// Detection-only group: five real plugins whose *detection* is all the plugin
// promises for them — Cookiebot (bridge exists but its banner needs an
// account), Cloudflare (Rocket Loader advice), Polylang and TranslatePress
// (the two multilingual modes) and Beaver Builder Lite (a builder row).
//
// Until this ran, every one of these rows was produced by a constant the
// seed defined by hand (tests/wp/seed.php ?cg_cmp= / ?cg_builder=). A real
// plugin that renamed its constant would drop off the Compatibility screen
// with no test noticing; this is the test that notices.
// @ts-check
const { test, expect } = require( '@playwright/test' );
const {
	GATED_PAGE,
	requireField,
	login,
	openStatusTab,
	compatRow,
	trackThirdPartyRequests,
} = require( './_helpers' );

const PLUGINS = [ 'cookiebot', 'cloudflare', 'polylang', 'translatepress-multilingual', 'beaver-builder-lite-version' ];

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, PLUGINS );
} );

test( 'every plugin in the group is named on the Compatibility screen, with the right advice', async ( { page } ) => {
	await login( page );
	await openStatusTab( page );

	// Cookiebot is on the tested list; with the bridge off the row must say
	// so and point at the bridge, not claim it is active.
	await expect( compatRow( page, 'Cookiebot' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'Cookiebot' ) ).toContainText( 'tested for interoperation' );
	await expect( compatRow( page, 'Cookiebot' ) ).not.toContainText( 'bridge active' );

	// Cloudflare: a cache row, and the Rocket Loader advice in the
	// optimisation table (no exclusion list — it is per-script).
	await expect( compatRow( page, 'Cloudflare' ) ).toHaveCount( 1 );
	const cloudflare = page.locator( 'h3:has-text("JavaScript optimisation") + table tr', { hasText: 'Cloudflare' } );
	await expect( cloudflare ).toContainText( 'Rocket Loader takes no exclusion list' );
	await expect( cloudflare ).not.toContainText( 'exclude the files below' );

	// The two multilingual modes: Polylang holds registered strings and is
	// told where to translate them; TranslatePress translates the finished
	// page and needs nothing.
	await expect( compatRow( page, 'Polylang' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'Polylang' ) ).toContainText( 'registered for translation' );
	await expect( compatRow( page, 'TranslatePress' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'TranslatePress' ) ).toContainText( 'translates the finished page' );

	// Beaver Builder: a builder row, with the buffer off pointing at the
	// setting rather than claiming coverage.
	await expect( compatRow( page, 'Beaver Builder' ) ).toHaveCount( 1 );
	await expect( compatRow( page, 'Beaver Builder' ) ).toContainText( 'Gate the whole page output' );
} );

test( 'with all five active, a gated page still makes no third-party request', async ( { page } ) => {
	// Cookiebot without a Domain Group ID must not call home from the front
	// end, and none of the other four should either. Anything that shows up
	// here is a decision to make explicitly (a per-spec allowlist with a
	// comment), never a silent widening.
	const offenders = trackThirdPartyRequests( page );
	await page.goto( GATED_PAGE );
	await page.waitForLoadState( 'networkidle' );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( 'iframe[src*="youtube"]' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
} );
