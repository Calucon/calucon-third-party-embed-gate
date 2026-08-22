// gate.js is the second, independent sanitisation layer: the documented
// calucon_embed_gate_payload filter (docs/customizing.md) lets site code put
// ARBITRARY data into data-cg-payload, so the client must enforce the same
// rules PHP does — non-http(s) src rejected, attributes safelisted, autoplay
// stripped (invariant 8), never wider privilege (invariant 7). Every other
// E2E page carries PHP-sanitised payloads, which means gate.js's own guards
// would otherwise be dead code as far as the suite knows: this file feeds it
// hostile payloads directly and is what fails if any client guard is removed.
// @ts-check
const { test, expect } = require( '@playwright/test' );

test( 'a javascript: src is rejected: no node built, error shown, fallback kept', async ( { page } ) => {
	await page.goto( '/page/gated' );
	const container = page.locator( '.cg-embed' ).first();
	await expect( container ).toBeVisible();

	await container.evaluate( ( node ) => {
		node.setAttribute( 'data-cg-payload', JSON.stringify( {
			src: 'javascript:window.__pwned = 1',
			attrs: {},
		} ) );
	} );
	await container.locator( '.cg-embed__button' ).click();

	await expect( container.locator( 'iframe' ) ).toHaveCount( 0 );
	await expect( container.locator( '.cg-embed__error' ) ).toBeVisible();
	// The panel survives a hostile payload: the visitor still has the link.
	await expect( container.locator( '.cg-embed__fallback a' ) ).toBeVisible();
	expect( await page.evaluate( () => window.__pwned ) ).toBeUndefined();
} );

test( 'a data: src is rejected too', async ( { page } ) => {
	await page.goto( '/page/gated' );
	const container = page.locator( '.cg-embed' ).first();
	await expect( container ).toBeVisible();

	await container.evaluate( ( node ) => {
		node.setAttribute( 'data-cg-payload', JSON.stringify( {
			src: 'data:text/html,<script>window.__pwned = 1</script>',
			attrs: {},
		} ) );
	} );
	await container.locator( '.cg-embed__button' ).click();

	await expect( container.locator( 'iframe' ) ).toHaveCount( 0 );
	await expect( container.locator( '.cg-embed__error' ) ).toBeVisible();
} );

test( 'hostile attributes never reach the built frame; autoplay is stripped client-side', async ( { page } ) => {
	await page.goto( '/page/gated' );
	const container = page.locator( '.cg-embed' ).first();
	await expect( container ).toBeVisible();

	// Valid same-origin src so the frame IS built — the attack is in attrs.
	await container.evaluate( ( node ) => {
		node.setAttribute( 'data-cg-payload', JSON.stringify( {
			src: window.location.origin + '/frame.html',
			attrs: {
				title: 'kept',
				sandbox: 'allow-scripts',
				allow: 'autoplay; encrypted-media; camera',
				onload: 'window.__pwned = 1',
				style: 'position:fixed;inset:0',
				srcdoc: '<script>window.__pwned = 1</script>',
				src: 'https://evil.example/other',
				'data-evil': 'x',
			},
		} ) );
	} );
	await container.locator( '.cg-embed__button' ).click();

	const frame = container.locator( 'iframe' );
	await expect( frame ).toHaveCount( 1 );
	await expect( frame ).toHaveAttribute( 'src', /\/frame\.html$/ );
	await expect( frame ).toHaveAttribute( 'title', 'kept' );
	await expect( frame ).toHaveAttribute( 'sandbox', 'allow-scripts' );
	// Client-side stripAutoplay: PHP never saw this payload (invariant 8).
	await expect( frame ).toHaveAttribute( 'allow', 'encrypted-media; camera' );
	for ( const name of [ 'onload', 'style', 'srcdoc', 'data-evil' ] ) {
		await expect( frame, name + ' must not be copied' ).not.toHaveAttribute( name );
	}
	expect( await page.evaluate( () => window.__pwned ) ).toBeUndefined();
} );
