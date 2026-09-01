// Shared helpers for the field-validation specs (tests/Field/).
//
// The one rule every spec follows: prove the plugin under test is really
// there before asserting anything about it. requireField() fails a spec
// whose group plugins are not active on the site — an install that quietly
// failed must never produce a green run.
// @ts-check
const { expect, request } = require( '@playwright/test' );
const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );

/**
 * Run wp-cli inside the Docker stack, as the web user, exactly as
 * tests/wp/field-setup.sh does. For the settings of the plugin UNDER TEST
 * between tests (an optimiser's defer level, say); the plugin's own
 * settings go through the form like a site owner would.
 */
function wpcli( ...args ) {
	return execFileSync(
		'docker',
		[ 'compose', '-f', path.resolve( __dirname, '../wp/docker-compose.yml' ), 'run', '--rm', '--no-deps', '--user', '33:33', '-e', 'HOME=/tmp', 'cli', 'wp', ...args ],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] }
	).trim();
}

/** Run a shell command inside the WordPress container (reads of files a cache plugin wrote). */
function wpexec( command ) {
	return execFileSync(
		'docker',
		[ 'compose', '-f', path.resolve( __dirname, '../wp/docker-compose.yml' ), 'exec', '-T', 'wordpress', 'sh', '-c', command ],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] }
	);
}

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

function isOwnRequest( url, allow = [] ) {
	if ( url.startsWith( 'data:' ) || url.startsWith( 'blob:' ) ) {
		return true;
	}
	const host = new URL( url ).hostname;
	return OWN_HOSTS.includes( host ) || allow.includes( host );
}

/**
 * Record every request that leaves the site. `allow` is for hosts the plugin
 * UNDER TEST contacts on its own behalf (a CMP's CDN, say) — each entry is a
 * documented decision in the spec, never a widening of OWN_HOSTS.
 */
function trackThirdPartyRequests( page, allow = [] ) {
	const offenders = [];
	page.on( 'request', ( req ) => {
		if ( ! isOwnRequest( req.url(), allow ) ) {
			offenders.push( req.url() );
		}
	} );
	return offenders;
}

/** Abort anything third-party: the page must work without it. */
async function abortThirdParty( page, allow = [] ) {
	await page.route( '**', ( route ) => ( isOwnRequest( route.request().url(), allow ) ? route.continue() : route.abort() ) );
}

/**
 * Prove the group's plugins are active on the site under test, via the
 * probe mu-plugin tests/wp/field-seed.php writes. Records the versions as
 * annotations so the JSON report carries them into the CI summary.
 */
async function requireField( testInfo, active ) {
	const ctx = await request.newContext( { baseURL: testInfo.project.use.baseURL } );
	const res = await ctx.get( '/?cg_field=status' );
	expect( res.ok(), 'the field probe did not answer — did tests/wp/field-setup.sh run for this group?' ).toBe( true );
	const status = await res.json();
	expect( status.probe, 'the field probe mu-plugin is not installed (tests/wp/field-seed.php did not run)' ).toBe( 1 );
	for ( const slug of active ) {
		expect( status.active, `${ slug } is not active on the site under test` ).toHaveProperty( slug );
		testInfo.annotations.push( { type: 'plugin', description: `${ slug } ${ status.active[ slug ] }` } );
	}
	testInfo.annotations.push( { type: 'wordpress', description: `${ status.wp } / PHP ${ status.php }` } );
	await ctx.dispose();
	return status;
}

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/, { timeout: 120000 } );
}

const SETTINGS = '/wp-admin/options-general.php?page=calucon-embed-gate';

async function openStatusTab( page ) {
	await page.goto( SETTINGS );
	await page.click( '#cg-tabbtn-status' );
}

/** A row of the Compatibility table (detected plugins) by plugin name. */
function compatRow( page, name ) {
	return page.locator( '#cg-compatibility + table tr', { hasText: name } );
}

/** A row of the JavaScript-optimisation table by plugin name. */
function optimiserRow( page, name ) {
	return page.locator( 'h3:has-text("JavaScript optimisation") + table tr', { hasText: name } );
}

async function saveSettings( page ) {
	await page.click( 'form p.submit input[type="submit"], form input#submit' );
	await page.waitForURL( /options-general\.php/ );
}

/** Toggle one checkbox on a settings tab through the real form (one form, one save). */
async function setCheckbox( page, tab, selector, on ) {
	await page.goto( SETTINGS );
	await page.click( `#cg-tabbtn-${ tab }` );
	if ( on ) {
		await page.check( selector );
	} else {
		await page.uncheck( selector );
	}
	await saveSettings( page );
}

const OUTPUT_BUFFER = 'input[name="calucon_embed_gate_options[detection][output_buffer]"][type="checkbox"]';
const CMP_BRIDGE = '#cg-cmp-bridge';

async function setOutputBuffer( page, on ) {
	await setCheckbox( page, 'detection', OUTPUT_BUFFER, on );
}

async function setBridge( page, on ) {
	await setCheckbox( page, 'consent', CMP_BRIDGE, on );
}

/** The seeded page with three gated embeds (two YouTube, one unknown host). */
const GATED_PAGE = '/gated-classic/';

/** Anonymous GET, for cache tests: the body and the per-request marker. */
async function fetchAnonymous( baseURL, path, headers = {} ) {
	const ctx = await request.newContext( { baseURL } );
	const res = await ctx.get( path, { headers } );
	const body = await res.text();
	await ctx.dispose();
	const marker = ( body.match( /data-cg-field-req="([^"]+)"/ ) || [] )[ 1 ] || null;
	return { status: res.status(), headers: res.headers(), body, marker };
}

/**
 * The cache groups' shared claim: "the page that gets cached is the gated
 * one". Two anonymous requests; both must be gated, and the second must be
 * the first one served again (same per-request marker from the probe
 * mu-plugin — plugin-agnostic, survives HTML-comment stripping). A marker
 * mismatch means the cache is not serving, which is a setup problem, and
 * the message says so rather than blaming the plugin.
 */
async function expectCachedAndGated( baseURL, path = GATED_PAGE, headers = {} ) {
	const first = await fetchAnonymous( baseURL, path, headers );
	expect( first.status ).toBe( 200 );
	expect( first.marker, 'the field probe marker is missing from the page' ).not.toBeNull();
	expect( first.body ).toContain( 'class="cg-embed' );
	expect( first.body ).not.toMatch( /<iframe[^>]+src=["']?https?:\/\/[^"'\s>]*(youtube|example-partner)/i );

	// Some caches write the file at PHP shutdown, after the response has
	// been sent in full; a second request a few milliseconds later can race
	// that write. Half a second is generous and costs nothing.
	await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
	const second = await fetchAnonymous( baseURL, path, headers );
	expect( second.status ).toBe( 200 );
	expect( second.marker, 'second request was NOT served from the page cache (markers differ) — the cache plugin is not caching; fix the group setup, this says nothing about gating' ).toBe( first.marker );
	expect( second.body ).toContain( 'class="cg-embed' );
	expect( second.body ).not.toMatch( /<iframe[^>]+src=["']?https?:\/\/[^"'\s>]*(youtube|example-partner)/i );
	return { first, second };
}

/**
 * The load-bearing click: with the optimiser's combine/defer/delay on, the
 * placeholder's button must still load the embed. `clicks` is how many
 * clicks the setting is expected to cost (2 for "delay JS until
 * interaction", the readme's documented symptom).
 */
async function expectClickLoads( page, { clicks = 1, allow = [] } = {} ) {
	await abortThirdParty( page, allow );
	await page.goto( GATED_PAGE );
	await page.waitForLoadState( 'networkidle' );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	const button = page.locator( '.cg-embed__button' ).first();
	for ( let i = 1; i < clicks; i++ ) {
		await button.click();
		// The documented symptom: this click is spent switching the delayed
		// scripts on, so nothing loads yet.
		await page.waitForTimeout( 500 );
		await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 0 );
	}
	await page.locator( '.cg-embed__button' ).first().click();
	await expect( page.locator( '.cg-embed iframe' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed iframe' ).first() ).toHaveAttribute( 'src', /youtube-nocookie\.com/ );
}

module.exports = {
	wpcli,
	wpexec,
	expectCachedAndGated,
	expectClickLoads,
	OWN_HOSTS,
	GATED_PAGE,
	SETTINGS,
	trackThirdPartyRequests,
	abortThirdParty,
	requireField,
	login,
	openStatusTab,
	compatRow,
	optimiserRow,
	saveSettings,
	setOutputBuffer,
	setBridge,
	fetchAnonymous,
};
