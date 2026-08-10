<?php
/**
 * Regenerate expected.html for gated fixtures.
 *
 * Usage: php tests/bin/generate-fixtures.php [--force]
 *
 * Only writes expected.html where it is missing (or with --force). The
 * generated file must be reviewed by a human before committing — an expected
 * file that merely records a bug makes the bug permanent. Pass-through
 * fixtures are hand-copied from input.html, never generated.
 *
 * @package ConsentGate
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use ConsentGate\Tests\Support\PipelineFactory;

$force = in_array( '--force', $argv, true );
$root  = dirname( __DIR__ ) . '/Fixtures';

foreach ( scandir( $root ) as $entry ) {
	$dir = $root . '/' . $entry;
	if ( '.' === $entry[0] || ! is_dir( $dir ) ) {
		continue;
	}

	$expected_file = $dir . '/expected.html';
	if ( is_file( $expected_file ) && ! $force ) {
		continue;
	}

	$input  = (string) file_get_contents( $dir . '/input.html' );
	$output = PipelineFactory::gate( $input, array( 'example.test' ), PipelineFactory::fixture_ctx( $dir ) );

	file_put_contents( $expected_file, $output );
	echo ( $output === $input ? 'PASS-THROUGH ' : 'GATED        ' ) . $entry . PHP_EOL;
}
