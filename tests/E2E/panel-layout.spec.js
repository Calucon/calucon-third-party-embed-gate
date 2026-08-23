// The panel's own box (PLAN.md §5.3, §8). The space reserved from the
// embed's aspect ratio keeps the page from jumping on activation, but it is
// a MINIMUM: a theme with roomy type needs more height than 9/16 of a
// phone's width, and a hard box turned that into a scrollbar nobody sees —
// on the showcase site the privacy link sat below the fold of the panel.
// @ts-check
const { test, expect } = require( '@playwright/test' );

test.describe( 'panel box', () => {
	test.use( { viewport: { width: 390, height: 844 } } );

	test( 'grows to fit its contents instead of hiding them behind a scrollbar', async ( { page } ) => {
		await page.goto( '/page/narrow' );

		const box = page.locator( '.cg-embed' ).first();
		const m = await box.evaluate( ( el ) => ( {
			scroll: el.scrollHeight,
			client: el.clientHeight,
			scrollW: el.scrollWidth,
			clientW: el.clientWidth,
			panel: el.querySelector( '.cg-embed__panel' ).getBoundingClientRect().bottom,
			bottom: el.getBoundingClientRect().bottom,
		} ) );

		expect( m.scroll, 'nothing scrolls out of sight' ).toBeLessThanOrEqual( m.client );
		expect( m.scrollW, 'and nothing scrolls sideways either' ).toBeLessThanOrEqual( m.clientW );
		expect( m.panel, 'the panel ends inside its own box' ).toBeLessThanOrEqual( m.bottom + 1 );

		// Every part of the panel is reachable without scrolling inside it.
		for ( const part of [ '.cg-embed__note', '.cg-embed__button', '.cg-embed__fallback a', '.cg-embed__privacy a' ] ) {
			await expect( box.locator( part ) ).toBeInViewport();
		}
	} );

} );

test.describe( 'panel box at desktop width', () => {
	test.use( { viewport: { width: 1280, height: 900 } } );

	test( 'still reserves the embed\'s space when the panel fits inside it', async ( { page } ) => {
		await page.goto( '/page/gated' );

		// 500x281 with a compact panel: the box keeps the embed's ratio, so
		// the page does not jump when the real iframe replaces it.
		const ratio = await page.locator( '.cg-embed[data-cg-provider="youtube"]' ).first().evaluate( ( el ) => {
			const r = el.getBoundingClientRect();
			return r.width / r.height;
		} );
		expect( ratio ).toBeGreaterThan( 500 / 281 - 0.15 );
		expect( ratio ).toBeLessThan( 500 / 281 + 0.15 );
	} );
} );
