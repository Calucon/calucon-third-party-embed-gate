// WP Super Cache (wp-super-cache), the real plugin.
//
// A page cache that does nothing to JavaScript — which is exactly what the
// Status screen has to say: no exclusion list, nothing to exclude, and no
// "exclude the files below" preamble contradicting it one line above. The
// behavioural claim is the cached page being the gated one.
//
// tests/wp/field-setup.sh calls wp_cache_enable()/wp_super_cache_enable(),
// the same functions WP Super Cache's own wp-cli package calls.
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
	wpexec,
	fetchAnonymous,
	trackThirdPartyRequests,
} = require( './_helpers' );

test.beforeAll( async ( {}, testInfo ) => {
	await requireField( testInfo, [ 'wp-super-cache' ] );
} );


function flush() {
	wpcli( 'eval', 'if ( function_exists( "wp_cache_clear_cache" ) ) { wp_cache_clear_cache(); }' );
}

test( 'Compatibility: detected as a cache; the optimiser row is the lone honest sentence', async ( { page } ) => {
	await login( page );
	await openStatusTab( page );
	await expect( compatRow( page, 'WP Super Cache' ) ).toHaveCount( 1 );
	const row = optimiserRow( page, 'WP Super Cache' );
	await expect( row ).toHaveCount( 1 );
	await expect( row ).toContainText( 'nothing to exclude' );
	await expect( row ).not.toContainText( 'exclude the files below' );
	await expect( row ).not.toContainText( 'Where to exclude them:' );
} );

// WP Super Cache on a non-standard port: phase 2 writes the supercache
// file under the hostname WITHOUT the port (supercache/127.0.0.1/…), phase 1
// looks it up under HTTP_HOST WITH the port (127.0.0.1:8890) and never
// finds it — every request regenerates. A port-less Host header is a
// WordPress canonical 301. On 80/443 the two agree. So the harness cannot
// observe WPSC *serving* on port 8890; what it can prove, from the file
// WPSC writes, is the plugin's two claims: the page WPSC caches is the
// gated one, and a settings save flushes it. Measured 2026-08-28, WPSC 3.1.3.
const SUPERCACHE_FILE = 'wp-content/cache/supercache/127.0.0.1/gated-classic/index.html';

function cachedFile() {
	try {
		return wpexec( `cat ${ SUPERCACHE_FILE }` );
	} catch ( e ) {
		return null;
	}
}

test( 'the page WPSC caches is the gated one, and the live page makes no third-party request', async ( { page }, testInfo ) => {
	flush();
	expect( cachedFile(), 'flush left a supercache file behind' ).toBeNull();

	const live = await fetchAnonymous( testInfo.project.use.baseURL, GATED_PAGE );
	expect( live.status ).toBe( 200 );
	await new Promise( ( resolve ) => setTimeout( resolve, 500 ) ); // written at shutdown
	const file = cachedFile();
	expect( file, 'WP Super Cache did not write a supercache file for the page' ).not.toBeNull();
	// The file is THIS page's cache — same per-request marker — and gated.
	expect( file ).toContain( `data-cg-field-req="${ live.marker }"` );
	expect( file ).toContain( 'class="cg-embed' );
	expect( file ).not.toMatch( /<iframe[^>]+src=["']?https?:\/\/[^"'\s>]*(youtube|example-partner)/i );

	const offenders = trackThirdPartyRequests( page );
	await page.goto( GATED_PAGE );
	await page.waitForLoadState( 'networkidle' );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	expect( offenders ).toEqual( [] );
} );

test( 'saving the plugin settings flushes the page cache', async ( { page }, testInfo ) => {
	flush();
	await fetchAnonymous( testInfo.project.use.baseURL, GATED_PAGE );
	await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
	expect( cachedFile(), 'no supercache file to flush' ).not.toBeNull();

	await login( page );
	await page.goto( '/wp-admin/options-general.php?page=calucon-embed-gate' );
	await page.click( 'form p.submit input[type="submit"], form input#submit' );
	await page.waitForURL( /options-general\.php/ );

	expect( cachedFile(), 'the supercache file survived a settings save — the flush hook did not reach WP Super Cache' ).toBeNull();
} );

test( 'a cached, gated page still loads the embed on click', async ( { page } ) => {
	flush();
	await page.goto( GATED_PAGE ); // warm the cache
	await page.route( '**', ( route ) => ( [ '127.0.0.1', 'localhost' ].includes( new URL( route.request().url() ).hostname ) ? route.continue() : route.abort() ) );
	await page.goto( GATED_PAGE );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
} );
