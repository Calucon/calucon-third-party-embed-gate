// Layout preservation (PLAN.md §5.3): the gated panel must occupy the space
// the real embed would, or the page reflows on activation and the panel
// renders under a large empty rectangle. Verified by measurement, not by eye.
// @ts-check
const { test, expect } = require( '@playwright/test' );

test( 'panel fills the reserved aspect box exactly', async ( { page } ) => {
	await page.goto( '/page/aspect' );

	const wrapper = page.locator( '.wp-has-aspect-ratio .wp-block-embed__wrapper' );
	const panel = wrapper.locator( '.cg-embed' );

	const wrapperBox = await wrapper.boundingBox();
	const panelBox = await panel.boundingBox();

	// The §5.3 assertion: panel bounding box equals the wrapper's.
	expect( Math.abs( panelBox.x - wrapperBox.x ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( panelBox.y - wrapperBox.y ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( panelBox.width - wrapperBox.width ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( panelBox.height - wrapperBox.height ) ).toBeLessThanOrEqual( 1 );

	// And the reserved box is actually 16:9-shaped, so the spacer worked.
	expect( wrapperBox.height ).toBeGreaterThan( 0 );
	expect( Math.abs( wrapperBox.width / wrapperBox.height - 16 / 9 ) ).toBeLessThan( 0.05 );
} );

test( 'without a reserved box the panel takes the embed aspect from width/height', async ( { page } ) => {
	await page.goto( '/page/aspect' );

	const panel = page.locator( '[data-cg-provider="generic"]' );
	const box = await panel.boundingBox();

	// 640×360 attributes → 16:9 via the --cg-aspect custom property.
	expect( Math.abs( box.width / box.height - 16 / 9 ) ).toBeLessThan( 0.05 );
} );

test( 'activation does not reflow the reserved box', async ( { page } ) => {
	await page.route( '**', ( route ) => {
		const host = new URL( route.request().url() ).hostname;
		return [ '127.0.0.1', 'localhost' ].includes( host )
			? route.continue()
			: route.fulfill( { contentType: 'text/html', body: '<!doctype html><p>frame</p>' } );
	} );

	await page.goto( '/page/aspect' );

	const wrapper = page.locator( '.wp-has-aspect-ratio .wp-block-embed__wrapper' );
	const before = await wrapper.boundingBox();

	await wrapper.locator( '.cg-embed__button' ).click();
	const frame = wrapper.locator( 'iframe' );
	await expect( frame ).toHaveCount( 1 );

	const after = await wrapper.boundingBox();
	const frameBox = await frame.boundingBox();

	// The box neither grew nor collapsed, and the iframe fills it.
	expect( Math.abs( after.height - before.height ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( frameBox.width - after.width ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( frameBox.height - after.height ) ).toBeLessThanOrEqual( 1 );
} );
