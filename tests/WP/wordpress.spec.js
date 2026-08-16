// WordPress integration tests (PLAN.md §10.2, run against a real install).
//
// The site under test is seeded by tests/wp/seed.php — the same content on
// both backends (Docker stack or WordPress Playground). These tests assert
// the plugin's behaviour where it actually runs: real hooks, real theme,
// real enqueue pipeline, real feeds and REST.
// @ts-check
const { test, expect } = require( '@playwright/test' );

const OWN_HOSTS = [ '127.0.0.1', 'localhost' ];

function isOwnRequest( url ) {
	// blob:/data: URLs carry no network host — they can only exist because
	// the page's own scripts created them (WordPress core does).
	if ( url.startsWith( 'blob:' ) || url.startsWith( 'data:' ) ) {
		return true;
	}
	return OWN_HOSTS.includes( new URL( url ).hostname );
}

function trackThirdPartyRequests( page ) {
	const offenders = [];
	page.on( 'request', ( request ) => {
		if ( ! isOwnRequest( request.url() ) ) {
			offenders.push( request.url() );
		}
	} );
	return offenders;
}

async function abortThirdParty( page ) {
	await page.route( '**', ( route ) => {
		return isOwnRequest( route.request().url() )
			? route.continue()
			: route.fulfill( { contentType: 'text/html', body: '<!doctype html><p>frame</p>' } );
	} );
}

test( 'classic content: gated on the_content, zero third-party requests', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/gated-classic/' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders, 'INVARIANT 1 VIOLATED on a real WordPress page' ).toEqual( [] );

	// Three foreign iframes gated — including the minified one — the
	// same-origin iframe untouched.
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 3 );
	await expect( page.locator( 'iframe[src="/wp-json/"]' ) ).toHaveCount( 1 );
	expect( await page.locator( 'iframe' ).count() ).toBe( 1 );

	// Nothing stored by the plugin before consent (invariant 3). WordPress
	// core itself writes wpEmojiSettingsSupports to sessionStorage — that is
	// core's doing on any theme, with or without this plugin — so the
	// assertion is scoped to what the plugin controls.
	const storage = await page.evaluate( () => ( {
		local: window.localStorage.length,
		sessionKeys: Object.keys( window.sessionStorage ).filter( ( k ) => k !== 'wpEmojiSettingsSupports' ),
		caluconEmbedGate: window.sessionStorage.getItem( 'calucon-embed-gate' ) || window.localStorage.getItem( 'calucon-embed-gate' ),
		cookie: document.cookie,
	} ) );
	expect( storage ).toEqual( { local: 0, sessionKeys: [], caluconEmbedGate: null, cookie: '' } );
} );

test( 'block content: gated via render_block', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/gated-blocks/' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders ).toEqual( [] );
	await expect( page.locator( '[data-cg-provider="youtube"]' ) ).toHaveCount( 1 );
	await expect( page.locator( '[data-cg-provider="vimeo"]' ) ).toHaveCount( 1 );
	expect( await page.locator( 'iframe' ).count() ).toBe( 0 );
} );

test( 'click activates through the plugin-enqueued gate.js, loading the privacy-preserving host', async ( { page } ) => {
	await abortThirdParty( page );
	await page.goto( '/gated-blocks/' );

	const youtube = page.locator( '[data-cg-provider="youtube"]' );
	await youtube.locator( '.cg-embed__button' ).click();

	const frame = youtube.locator( 'iframe' );
	await expect( frame ).toHaveCount( 1 );
	await expect( frame ).toHaveAttribute( 'src', 'https://www.youtube-nocookie.com/embed/y_pjE_p1HwE' );

	// Focus stays inside the container (§8), and the Vimeo panel is intact.
	const focused = await page.evaluate( () => document.activeElement && document.activeElement.className );
	expect( String( focused ) ).toContain( 'cg-embed' );
	await expect( page.locator( '[data-cg-provider="vimeo"] .cg-embed__panel' ) ).toHaveCount( 1 );
} );

test( 'script embeds: SDKs gated, companions kept, fallbacks harvested', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/script-embeds/' );
	await page.waitForLoadState( 'networkidle' );

	expect( offenders, 'script SDK loaded before any click' ).toEqual( [] );
	await expect( page.locator( '.cg-embed[data-cg-provider="twitter"]' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cg-embed[data-cg-provider="strava"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'blockquote.twitter-tweet' ) ).toHaveCount( 1 );
	await expect( page.locator( 'div.strava-embed-placeholder' ) ).toHaveCount( 1 );
	await expect( page.locator( '[data-cg-provider="strava"] .cg-embed__fallback a' ) )
		.toHaveAttribute( 'href', 'https://www.strava.com/activities/1234567890' );
} );

test( 'poster block attribute renders a site-origin poster, gone after activation', async ( { page } ) => {
	const offenders = trackThirdPartyRequests( page );

	await page.goto( '/poster-embed/' );
	await page.waitForLoadState( 'networkidle' );

	// The poster request goes to the site's own host — never the provider.
	expect( offenders, 'a poster must never contact a third party' ).toEqual( [] );

	const container = page.locator( '.cg-embed--poster' );
	await expect( container ).toHaveCount( 1 );
	const poster = container.locator( 'img.cg-embed__poster' );
	await expect( poster ).toHaveAttribute( 'alt', '' );
	await expect( poster ).toHaveAttribute( 'aria-hidden', 'true' );
	const src = await poster.getAttribute( 'src' );
	expect( OWN_HOSTS ).toContain( new URL( String( src ), page.url() ).hostname );

	await abortThirdParty( page );
	await container.locator( '.cg-embed__button' ).click();
	await expect( container.locator( 'iframe' ) ).toHaveCount( 1 );
	await expect( container.locator( 'img.cg-embed__poster' ) ).toHaveCount( 0 );
} );

test( 'assets ship only on pages where something was gated', async ( { page } ) => {
	await page.goto( '/gated-classic/' );
	await expect( page.locator( 'script[src*="calucon-third-party-embed-gate/assets/js/gate.js"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'link[href*="calucon-third-party-embed-gate/assets/css/gate.css"]' ) ).toHaveCount( 1 );

	await page.goto( '/no-embeds/' );
	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 0 );
	await expect( page.locator( 'script[src*="calucon-third-party-embed-gate/assets/js/gate.js"]' ) ).toHaveCount( 0 );
	await expect( page.locator( 'link[href*="calucon-third-party-embed-gate/assets/css/gate.css"]' ) ).toHaveCount( 0 );
} );

test( 'escaped markup in a tutorial post survives untouched', async ( { page } ) => {
	await page.goto( '/escaped-markup/' );

	await expect( page.locator( '.cg-embed' ) ).toHaveCount( 0 );
	// The code sample still shows the literal markup to the reader.
	await expect( page.locator( 'code' ) ).toContainText( '<iframe src="https://www.youtube.com/embed/y_pjE_p1HwE"' );
} );

test( 'feeds strip embeds instead of gating them', async ( { request } ) => {
	const response = await request.get( '/feed/' );
	expect( response.ok() ).toBe( true );
	const xml = await response.text();

	// No live embed, no placeholder — neither belongs in RSS (§9.3). The
	// escaped tutorial sample (&#60;iframe …) legitimately remains.
	expect( xml ).not.toMatch( /<iframe[^>]*youtube\.com\/embed/ );
	expect( xml ).not.toMatch( /<script[^>]*platform\.twitter\.com/ );
	expect( xml ).not.toContain( 'cg-embed' );
	expect( xml ).toContain( 'Intro paragraph.' );
} );

test( 'anonymous REST content is gated — headless/load-more consumers get placeholders', async ( { request } ) => {
	const response = await request.get( '/wp-json/wp/v2/posts?slug=gated-classic' );
	expect( response.ok() ).toBe( true );
	const [ post ] = await response.json();

	// §9.2: themes fetch archive pages over /wp-json for "load more" and
	// render them into the live page. An anonymous requester is a visitor,
	// and visitors get gated markup — the blanket REST bail was a bypass.
	expect( post.content.rendered ).toContain( 'cg-embed' );
	expect( post.content.rendered ).not.toMatch( /<iframe[^>]*youtube\.com\/embed/ );
} );

test( 'editor REST content is NOT gated — invariant 4', async ( { page, request } ) => {
	// A cookie fetch without a nonce is anonymous to the REST API, so this
	// goes through the block editor itself: its wp.apiFetch carries the
	// authenticated nonce, exactly like real editor traffic.
	const listResponse = await request.get( '/wp-json/wp/v2/posts?slug=gated-classic' );
	const [ { id } ] = await listResponse.json();

	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ ); // Login sets the auth cookie via redirect; navigating before it lands races back to wp-login.
	await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
	await page.waitForFunction( () => window.wp && window.wp.apiFetch );

	const post = await page.evaluate( ( postId ) => {
		return window.wp.apiFetch( { path: `/wp/v2/posts/${ postId }` } );
	}, id );

	// Invariant 4: an editor fetching through REST must get the original
	// embed, or gating looks like data loss.
	expect( post.content.rendered ).toContain( 'youtube.com/embed' );
	expect( post.content.rendered ).not.toContain( 'cg-embed' );
} );

test( 'withdraw shortcode renders a real button with its live region', async ( { page } ) => {
	await page.goto( '/withdraw-page/' );

	const button = page.locator( 'button.cg-withdraw[data-cg-withdraw]' );
	await expect( button ).toHaveCount( 1 );
	const statusId = await button.getAttribute( 'aria-controls' );
	expect( statusId ).toBeTruthy();
	await expect( page.locator( `#${ statusId }[aria-live="polite"]` ) ).toHaveCount( 1 );
} );

test( 'pre-rename identifiers in stored content keep working through the aliases', async ( { page } ) => {
	// /legacy-page/ was authored before the 0.9.0 rename: it uses the old
	// [consent_gate_withdraw] shortcode and the old consent-gate/withdraw
	// block. Both must render real buttons — an unregistered shortcode would
	// show as literal text on someone's privacy page, and an unregistered
	// block renders nothing.
	await page.goto( '/legacy-page/' );

	await expect( page.locator( 'button.cg-withdraw[data-cg-withdraw]' ) ).toHaveCount( 2 );
	await expect( page.locator( 'body' ) ).not.toContainText( '[consent_gate_withdraw]' );
} );

test( 'admin: appearance controls are novice-usable — pickers, live preview, contrast report', async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ ); // Login sets the auth cookie via redirect; navigating before it lands races back to wp-login.

	const offenders = trackThirdPartyRequests( page );
	await page.goto( '/wp-admin/options-general.php?page=calucon-embed-gate' );
	await page.waitForLoadState( 'networkidle' );

	// The preview sample carries a YouTube payload, but it is inert data:
	// the plugin must add no request to the settings screen (invariant 9).
	// Core's own admin toolbar fetches the user's Gravatar on every wp-admin
	// page, plugin or no plugin — that is outside what the plugin controls.
	const pluginOffenders = () => offenders.filter( ( url ) => ! url.includes( 'gravatar.com' ) );
	expect( pluginOffenders(), 'settings screen made a third-party request' ).toEqual( [] );

	// All four colours got a real WordPress colour picker — no hex typing.
	await expect( page.locator( '.wp-picker-container' ) ).toHaveCount( 4 );

	// The Appearance panel lives behind its tab.
	await page.click( '#cg-tabbtn-appearance' );

	// The live preview is the real placeholder markup, and the contrast
	// report measured every colour pair.
	const sample = page.locator( '#cg-preview-stage .cg-embed' );
	await expect( sample ).toBeVisible();
	await expect( sample.locator( '.cg-embed__button' ) ).toBeVisible();
	await expect( page.locator( '#cg-contrast-report' ) ).toContainText( ':1' );

	// Switching the panel style restyles the preview immediately.
	await page.selectOption( '#cg-preset', 'minimal' );
	await expect( page.locator( '#cg-preview-stage.cg-preview--minimal' ) ).toHaveCount( 1 );

	// The preview's fallback link is defused — clicking it must not
	// navigate the owner away (nor toward the provider).
	await sample.locator( '.cg-embed__fallback a' ).click();
	expect( page.url() ).toContain( 'options-general.php?page=calucon-embed-gate' );
	expect( pluginOffenders() ).toEqual( [] );

	// Deep link: a panel id in the hash opens that tab directly. Leave the
	// page first — a hash-only goto would be a same-document navigation and
	// prove nothing.
	await page.goto( '/wp-admin/index.php' );
	await page.goto( '/wp-admin/options-general.php?page=calucon-embed-gate#cg-tab-appearance' );
	await expect( page.locator( '#cg-preview-stage .cg-embed' ) ).toBeVisible();
	await expect( page.locator( '#cg-tabbtn-appearance' ) ).toHaveAttribute( 'aria-selected', 'true' );
} );

test( 'admin: settings screen is tabbed — providers, detection, consent, status', async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ ); // Login sets the auth cookie via redirect; navigating before it lands races back to wp-login.

	await page.goto( '/wp-admin/options-general.php?page=calucon-embed-gate' );

	await expect( page.locator( 'h1' ) ).toContainText( 'Calucon Third-Party Embed Gate' );

	// Providers is the default tab; the other panels are behind their tabs.
	await expect( page.locator( 'td', { hasText: 'YouTube' } ).first() ).toBeVisible();
	await expect( page.locator( '#cg-own-hosts' ) ).toBeHidden();

	await page.click( '#cg-tabbtn-detection' );
	await expect( page.locator( '#cg-own-hosts' ) ).toBeVisible();
	await expect( page.locator( 'td', { hasText: 'YouTube' } ).first() ).toBeHidden();

	await page.click( '#cg-tabbtn-consent' );
	await expect( page.locator( '#cg-memory' ) ).toBeVisible();

	// Status & tools is read-only: the CSP snippet shows, the Save button
	// does not.
	await page.click( '#cg-tabbtn-status' );
	await expect( page.locator( 'textarea[aria-label="Content-Security-Policy snippet"]' ) ).toContainText( 'frame-src' );
	await expect( page.locator( 'form p.submit' ) ).toBeHidden();
	await page.click( '#cg-tabbtn-providers' );
	await expect( page.locator( 'form p.submit' ) ).toBeVisible();

	// The Status scan's legacy anchor still lands on the right tab.
	await page.goto( '/wp-admin/options-general.php?page=calucon-embed-gate&cg-scan=1#cg-status' );
	await expect( page.locator( '#cg-tabbtn-status' ) ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( page.locator( '#cg-status' ) ).toBeVisible();
} );
