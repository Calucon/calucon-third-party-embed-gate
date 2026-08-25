// Every placeholder, at every screen size (PLAN.md §5.3, §8).
//
// Two regressions came from this area within a day: a hard aspect-ratio box
// hid the fallback and privacy links behind a vertical scrollbar on a phone,
// and the grid that fixed it took its column from max-content, so the notice
// stopped wrapping and scrolled sideways instead. Both were invisible to the
// suite because nothing checked panel geometry across viewports. This does.
// @ts-check
const { test, expect } = require( '@playwright/test' );

const VIEWPORTS = [
	{ name: 'mobile-360', width: 360, height: 740 },
	{ name: 'mobile-390', width: 390, height: 844 },
	{ name: 'mobile-414', width: 414, height: 896 },
	{ name: 'tablet-768', width: 768, height: 1024 },
	{ name: 'tablet-834', width: 834, height: 1112 },
	{ name: 'tablet-1024', width: 1024, height: 768 },
	{ name: 'desktop-1280', width: 1280, height: 800 },
	{ name: 'desktop-1440', width: 1440, height: 900 },
	{ name: 'desktop-1920', width: 1920, height: 1080 },
];

// Every page the harness serves that renders panels. '/page/aspect' is
// excluded from the PAGE-level width check only: it deliberately contains a
// site-authored <div style="width:640px"> to exercise §5.3, and a box wider
// than the screen is the page's own doing. Its panels are still checked, and
// so is the rule that the gate never widens its container.
const PAGES = [
	'/page/gated', '/page/scripts', '/page/scripts-multi', '/page/memory', '/page/light',
	'/page/aspect', '/page/shapes', '/page/poster', '/page/poster-mismatch',
	'/page/custom-provider', '/page/companions', '/page/inline-write',
	'/page/narrow', '/page/companion-hole', '/page/collision',
];
const PAGES_WITH_AUTHORED_FIXED_WIDTH = [ '/page/aspect' ];

/**
 * Panel geometry as the browser lays it out.
 */
async function measure( page ) {
	return page.evaluate( () => {
		const panels = [];
		document.querySelectorAll( '.cg-embed:not(.cg-embed--silent)' ).forEach( ( el ) => {
			const parent = el.parentElement;
			panels.push( {
				provider: el.getAttribute( 'data-cg-provider' ),
				hiddenBelow: el.scrollHeight - el.clientHeight,
				hiddenBeside: el.scrollWidth - el.clientWidth,
				widerThanParent: parent ? Math.round( el.getBoundingClientRect().width - parent.clientWidth ) : 0,
			} );
		} );
		return {
			pageOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			panels,
		};
	} );
}

for ( const vp of VIEWPORTS ) {
	test.describe( `at ${ vp.name }`, () => {
		test.use( { viewport: { width: vp.width, height: vp.height } } );

		test( 'no placeholder hides its own contents, and none widens its container', async ( { page } ) => {
			// Third-party requests are stubbed: this is a layout check, and the
			// gate must not reach the network before a click anyway.
			await page.route( '**/*', ( route ) =>
				route.request().url().startsWith( 'http://127.0.0.1' )
					? route.continue()
					: route.fulfill( { contentType: 'text/html', body: 'stub' } )
			);

			for ( const path of PAGES ) {
				await page.goto( path );

				const { pageOverflow, panels } = await measure( page );
				expect( panels.length, `${ path } should render panels` ).toBeGreaterThan( 0 );

				for ( const panel of panels ) {
					const where = `${ path } @ ${ vp.name } (${ panel.provider })`;
					expect( panel.hiddenBelow, `${ where }: content hidden below the fold of its own box` ).toBeLessThanOrEqual( 1 );
					expect( panel.hiddenBeside, `${ where }: content hidden beside its own box` ).toBeLessThanOrEqual( 1 );
					expect( panel.widerThanParent, `${ where }: the gate widened its container` ).toBeLessThanOrEqual( 1 );
				}

				if ( ! PAGES_WITH_AUTHORED_FIXED_WIDTH.includes( path ) ) {
					expect( pageOverflow, `${ path } @ ${ vp.name }: the page scrolls sideways` ).toBeLessThanOrEqual( 1 );
				}
			}
		} );

		test( 'the whole panel stays operable: button and both links are reachable', async ( { page } ) => {
			await page.route( '**/*', ( route ) =>
				route.request().url().startsWith( 'http://127.0.0.1' )
					? route.continue()
					: route.fulfill( { contentType: 'text/html', body: 'stub' } )
			);
			await page.goto( '/page/narrow' );

			const panel = page.locator( '.cg-embed' ).first();
			await expect( panel.locator( '.cg-embed__button' ) ).toBeInViewport();
			await expect( panel.locator( '.cg-embed__fallback a' ) ).toBeInViewport();
			await expect( panel.locator( '.cg-embed__privacy a' ) ).toBeInViewport();

			// The button keeps its WCAG 2.5.8 hit area at every width.
			const box = await panel.locator( '.cg-embed__button' ).boundingBox();
			expect( box.height ).toBeGreaterThanOrEqual( 24 );
			expect( box.width ).toBeGreaterThanOrEqual( 24 );
		} );
	} );
}
