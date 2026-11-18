<?php
/**
 * A vendor's page through the plugin's pipeline — one half of the check for
 * products that cannot be installed without a licence (Enfold, Avada,
 * WPBakery, Flatsome, Bricks). See docs/field-validation.md, "Commercial
 * builders".
 *
 * Usage:
 *   curl -sL https://vendor.example/demo/page/ -o page.html
 *   php tests/bin/vendor-page-gate.php page.html vendor.example > gated.html
 *   node tests/bin/vendor-page-run.js https://vendor.example/demo/page/ page.html vendor.example   # as it is
 *   node tests/bin/vendor-page-run.js https://vendor.example/demo/page/ gated.html vendor.example  # through the gate
 *
 * The page's body goes through the WordPress-free pipeline with the
 * vendor's own hosts treated as the site's own, as a real install of that
 * product would have it. Nothing here is a test and nothing here ships:
 * the saved pages are the vendors' and are never committed.
 *
 * @package CaluconEmbedGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // Sentinel for the plugin's direct-access guards.
}
if ( ! isset( $argv[2] ) ) {
	fwrite( STDERR, "usage: php tests/bin/vendor-page-gate.php <saved.html> <own-host>[,<own-host>…]\n" );
	exit( 2 );
}
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

/**
 * The page with its body gated — body only, like Integration\OutputBuffer.
 *
 * @param string   $html Saved page.
 * @param string[] $own  The vendor's own hosts.
 * @return string
 */
function calucon_embed_gate_vendor_page( string $html, array $own ): string {
	if ( ! preg_match( '#<body\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$at  = $m[0][1] + strlen( $m[0][0] );
	$end = strripos( $html, '</body>' );
	$end = false === $end ? strlen( $html ) : $end;
	return substr( $html, 0, $at )
		. \CaluconEmbedGate\Tests\Support\PipelineFactory::gate( substr( $html, $at, $end - $at ), $own )
		. substr( $html, $end );
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a command-line tool writing a file, not a response.
echo calucon_embed_gate_vendor_page( (string) file_get_contents( $argv[1] ), explode( ',', $argv[2] ) );
