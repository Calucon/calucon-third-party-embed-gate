<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Support\AssetVersion;
use PHPUnit\Framework\TestCase;

/**
 * A browser caches an asset by its URL. Two builds of the same version serve
 * different bytes at an identical URL, and the browser is right to keep what
 * it has — which is how a tester ends up looking at last week's CSS.
 */
final class AssetVersionTest extends TestCase {

	public function test_the_version_follows_the_file_not_just_the_release(): void {
		$file = tempnam( sys_get_temp_dir(), 'cg-asset' );
		file_put_contents( $file, 'a{}' );
		touch( $file, 1750000000 );
		clearstatcache( true, $file );

		$first = AssetVersion::for_file( $file, '0.11.0' );
		self::assertSame( '0.11.0.1750000000', $first );

		// Same release, changed file: the URL must change.
		file_put_contents( $file, 'a{color:red}' );
		touch( $file, 1750000123 );
		clearstatcache( true, $file );

		$second = AssetVersion::for_file( $file, '0.11.0' );
		self::assertSame( '0.11.0.1750000123', $second );
		self::assertNotSame( $first, $second, 'a changed file must bust the cache' );

		// The release still leads, so the URL stays readable.
		self::assertStringStartsWith( '0.11.0.', $second );

		unlink( $file );
	}

	public function test_an_unreadable_file_falls_back_to_the_release(): void {
		// A coarse cache key beats none at all.
		self::assertSame( '0.11.0', AssetVersion::for_file( '/no/such/file.css', '0.11.0' ) );
	}
	/**
	 * The rule is only worth anything if every enqueue follows it: one file
	 * still keyed on the bare release is one file a tester keeps seeing the
	 * old version of, which is exactly the bug this class exists to fix.
	 */
	public function test_every_bundled_asset_is_enqueued_through_this_class(): void {
		$offenders = array();
		$root      = dirname( __DIR__, 2 ) . '/src';
		$files     = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root ) );

		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			if ( ! preg_match_all( '/wp_(?:enqueue|register)_(?:script|style)\s*\((.*?)\);/s', $code, $calls ) ) {
				continue;
			}
			foreach ( $calls[1] as $arguments ) {
				// Handle-only enqueues (a script registered elsewhere) carry
				// no version argument at all.
				if ( false === strpos( $arguments, 'plugins_url' ) ) {
					continue;
				}
				if ( false === strpos( $arguments, 'AssetVersion::of(' ) ) {
					$offenders[] = basename( $file->getPathname() ) . ': ' . trim( preg_split( '/\R/', trim( $arguments ) )[0] );
				}
			}
		}

		self::assertSame( array(), $offenders, 'enqueue these through AssetVersion::of()' );
	}
}
