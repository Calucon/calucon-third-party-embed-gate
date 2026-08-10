// Playwright project for the WordPress integration tests (tests/WP/).
//
// Two interchangeable backends serve the same seeded site:
//   1. Default (no env): WordPress Playground — real WordPress on PHP-WASM
//      with SQLite, no Docker required. Started automatically below.
//   2. Docker: bring the stack up first (bash tests/wp/setup.sh), then run
//      with WP_BASE_URL=http://127.0.0.1:8890 — the webServer block is
//      skipped and the tests target the running container.
// @ts-check
const { defineConfig } = require( '@playwright/test' );

const externalServer = !! process.env.WP_BASE_URL;
const baseURL = process.env.WP_BASE_URL || 'http://127.0.0.1:8890';

module.exports = defineConfig( {
	testDir: 'tests/WP',
	testMatch: '**/*.spec.js',
	timeout: 60000,
	// One WordPress, shared state (storage, logins): keep runs serial.
	workers: 1,
	use: {
		baseURL,
		launchOptions: process.env.PW_CHROMIUM_PATH
			? { executablePath: process.env.PW_CHROMIUM_PATH }
			: {},
	},
	webServer: externalServer
		? undefined
		: {
			command: 'bash tests/wp/serve-playground.sh',
			// Readiness = the SEEDED site, not just a listening socket: the
			// Playground HTTP server answers before the blueprint finishes
			// seeding, and a test that wins that race sees a 404. This URL
			// only returns 200 once the seed's posts and permalinks exist.
			url: baseURL + '/gated-classic/',
			timeout: 300000,
			reuseExistingServer: true,
		},
} );
