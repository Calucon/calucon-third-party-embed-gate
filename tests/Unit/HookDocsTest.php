<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * readme.txt calls these hooks "documented", and docs/customizing.md ships in
 * the zip as where that documentation is. A hook added without a line there
 * makes the claim false for exactly the person who went looking; a hook
 * removed leaves a reference to something that no longer exists.
 */
final class HookDocsTest extends TestCase {

	/**
	 * @return string[] Hook names as the source actually fires them.
	 */
	private function hooks_in_source(): array {
		$found = array();
		$root  = dirname( __DIR__, 2 ) . '/src';
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root ) );

		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			if ( preg_match_all( "/(?:apply_filters|do_action)\(\s*'(calucon_embed_gate_[a-z_]+)'/", $code, $m ) ) {
				$found = array_merge( $found, $m[1] );
			}
		}

		$found = array_values( array_unique( $found ) );
		sort( $found );
		return $found;
	}

	public function test_every_hook_is_documented_with_its_signature(): void {
		$docs = (string) file_get_contents( dirname( __DIR__, 2 ) . '/docs/customizing.md' );

		preg_match_all( '/\| `(calucon_embed_gate_[a-z_]+)` \| `([^`]+)` \|/', $docs, $m, PREG_SET_ORDER );
		$documented = array();
		foreach ( $m as $row ) {
			$documented[ $row[1] ] = $row[2];
		}

		$hooks = $this->hooks_in_source();
		self::assertGreaterThan( 15, count( $hooks ), 'the hook scan looks broken' );

		self::assertSame(
			array(),
			array_values( array_diff( $hooks, array_keys( $documented ) ) ),
			'hooks fired by the plugin but absent from docs/customizing.md'
		);
		self::assertSame(
			array(),
			array_values( array_diff( array_keys( $documented ), $hooks ) ),
			'hooks documented in docs/customizing.md that the plugin does not fire'
		);

		// A signature, not just a name: parameters and (for filters) a return.
		foreach ( $documented as $hook => $signature ) {
			self::assertMatchesRegularExpression( '/^\(.*\)/', $signature, "$hook: signature must show its parameters" );
		}
	}

	/**
	 * The readme's list is what a developer reads on wordpress.org, where
	 * docs/customizing.md is not visible — so it has to name the same hooks
	 * and say where they are documented.
	 */
	public function test_the_readme_lists_the_same_hooks_and_points_at_the_reference(): void {
		$readme = (string) file_get_contents( dirname( __DIR__, 2 ) . '/readme.txt' );
		$bullet = '';
		foreach ( explode( "\n", $readme ) as $line ) {
			if ( 0 === strpos( $line, '* Documented' ) ) {
				$bullet = $line;
				break;
			}
		}
		self::assertNotSame( '', $bullet, 'the readme should list the documented hooks' );

		foreach ( $this->hooks_in_source() as $hook ) {
			self::assertStringContainsString( $hook, $bullet, "readme.txt does not list $hook" );
		}
		self::assertStringContainsString( 'docs/customizing.md', $bullet, 'the readme must say where the hooks are documented' );
	}
}
