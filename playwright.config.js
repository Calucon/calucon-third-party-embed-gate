// @ts-check
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: 'tests/E2E',
	testMatch: '**/*.spec.js',
	use: {
		baseURL: 'http://127.0.0.1:8931',
		// The CI/remote image pre-installs Chromium at a fixed path; using it
		// directly avoids a browser download (and any version-skew re-fetch).
		launchOptions: process.env.PW_CHROMIUM_PATH
			? { executablePath: process.env.PW_CHROMIUM_PATH }
			: {},
	},
	webServer: {
		command: 'php -S 127.0.0.1:8931 tests/E2E/app/router.php',
		url: 'http://127.0.0.1:8931/healthz',
		reuseExistingServer: true,
	},
} );
