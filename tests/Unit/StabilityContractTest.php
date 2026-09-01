<?php
/**
 * The 1.0 stability promise, pinned: what docs/customizing.md says is
 * public must be what the code exposes, and vice versa.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StabilityContractTest extends TestCase {

	/** The eight CSS custom properties the §5.1 contract promises. */
	private const CSS_PROPERTIES = array(
		'--cg-bg',
		'--cg-fg',
		'--cg-accent',
		'--cg-accent-fg',
		'--cg-radius',
		'--cg-gap',
		'--cg-font',
		'--cg-aspect',
	);

	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	private static function promise_section(): string {
		$doc = (string) file_get_contents( self::root() . '/docs/customizing.md' );
		$at  = strpos( $doc, '## What 1.0 promises' );
		self::assertNotFalse( $at, 'docs/customizing.md must carry the "What 1.0 promises" section' );
		$end = strpos( $doc, "\n## ", $at + 10 );
		return false === $end ? substr( $doc, $at ) : substr( $doc, $at, $end - $at );
	}

	public function test_the_promised_css_properties_are_the_ones_the_stylesheet_and_renderer_use(): void {
		$css  = (string) file_get_contents( self::root() . '/assets/css/gate.css' );
		$php  = (string) file_get_contents( self::root() . '/src/Support/AppearanceCss.php' )
			. (string) file_get_contents( self::root() . '/src/Rendering/PlaceholderRenderer.php' )
			. (string) file_get_contents( self::root() . '/templates/placeholder.php' );
		$used = array();
		preg_match_all( '/--cg-[a-z-]+/', $css . $php, $m );
		$used = array_values( array_unique( $m[0] ) );
		sort( $used );

		$promised = self::CSS_PROPERTIES;
		sort( $promised );

		// Every promised property is really used, and nothing unpromised
		// has crept into the public surface unnoticed.
		self::assertSame( $promised, $used, 'the --cg-* custom properties in code differ from the eight promised in docs/customizing.md' );

		$section = self::promise_section();
		foreach ( self::CSS_PROPERTIES as $property ) {
			self::assertStringContainsString( '`' . $property . '`', $section, "$property is not listed in the promise" );
		}
	}

	public function test_the_promise_names_every_hook_and_no_hook_it_does_not_have(): void {
		$section = self::promise_section();
		$found   = array();
		$files   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::root() . '/src' ) );
		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			if ( preg_match_all( "/(?:apply_filters|do_action)\(\s*'(calucon_embed_gate_[a-z_]+)'/", (string) file_get_contents( $file->getPathname() ), $m ) ) {
				$found = array_merge( $found, $m[1] );
			}
		}
		$found = array_values( array_unique( $found ) );
		sort( $found );
		self::assertNotEmpty( $found );
		preg_match_all( '/`(calucon_embed_gate_[a-z_]+)`/', $section, $m );
		// The option name shares the prefix and is promised in its own bullet;
		// it is not a hook.
		$listed = array_values( array_unique( array_diff( $m[1], array( 'calucon_embed_gate_options' ) ) ) );
		sort( $listed );
		self::assertSame( $found, $listed, 'the hooks the promise lists must be exactly the hooks the code fires' );
	}

	public function test_the_markup_contract_names_are_the_ones_the_renderer_emits(): void {
		$renderer = (string) file_get_contents( self::root() . '/src/Rendering/PlaceholderRenderer.php' );
		$section  = self::promise_section();
		foreach ( array( 'cg-embed', 'cg-embed__button', 'cg-embed__payload', 'data-cg-provider', 'data-cg-host' ) as $name ) {
			self::assertStringContainsString( $name, $renderer, "$name is promised but the renderer no longer emits it" );
			self::assertStringContainsString( '`' . $name . '`', $section, "$name is emitted but not listed in the promise" );
		}
	}

	public function test_the_version_is_one_point_something(): void {
		$header = (string) file_get_contents( self::root() . '/calucon-third-party-embed-gate.php' );
		self::assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*[1-9]\d*\.\d+\.\d+\s*$/m', $header, 'the promise section describes a 1.x plugin' );
	}
}
