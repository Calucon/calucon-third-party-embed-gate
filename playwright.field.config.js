// Playwright project for the field-validation suite (tests/Field/): the
// plugin's compatibility claims against the REAL wordpress.org plugins they
// name, one fresh Docker WordPress per group (tests/wp/field-setup.sh).
//
// Docker-only, on purpose. There is no Playground fallback: a run that
// silently pointed at a Playground with none of the plugins installed would
// be a suite that cannot fail. Bring a group up first:
//
//   bash tests/wp/field-setup.sh cmp-complianz
//   WP_BASE_URL=http://127.0.0.1:8890 npm run test:field -- --project=cmp-complianz
//
// or let tests/wp/field.sh loop over every group.
// @ts-check
const { defineConfig } = require( '@playwright/test' );
const { execFileSync } = require( 'node:child_process' );

if ( ! process.env.WP_BASE_URL ) {
	throw new Error( 'The field suite is Docker-only: bring a group up with `bash tests/wp/field-setup.sh <group>` and set WP_BASE_URL.' );
}

// The group list lives in one place (tests/wp/field-groups.sh), shared with
// the local loop and the CI matrix.
const groups = JSON.parse( execFileSync( 'bash', [ 'tests/wp/field-groups.sh', '--list-json' ], { encoding: 'utf8' } ) );

module.exports = defineConfig( {
	testDir: 'tests/Field',
	testMatch: '**/*.spec.js',
	timeout: 120000,
	// One WordPress, shared state (logins, options): serial, and never a
	// retry — a flaky pass against a real plugin is information, not noise.
	workers: 1,
	retries: 0,
	reporter: [
		[ 'list' ],
		// Outside outputDir (test-results/), which Playwright wipes at start —
		// tests/wp/field-setup.sh drops the installed-version dumps here too.
		[ 'json', { outputFile: 'field-results/report.json' } ],
	],
	use: {
		baseURL: process.env.WP_BASE_URL,
		launchOptions: process.env.PW_CHROMIUM_PATH
			? { executablePath: process.env.PW_CHROMIUM_PATH }
			: {},
	},
	projects: groups.map( ( group ) => ( {
		name: group,
		testMatch: `**/${ group }.spec.js`,
	} ) ),
} );
