<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The wp.org listing — description, installation, FAQ, screenshot captions —
 * is translated on translate.wordpress.org and cannot be shipped in the zip.
 * Its German lives in .wordpress-org/readme-de_DE.md and -de_DE_formal.md,
 * which nothing else would ever notice going stale: the plugin still works,
 * the tests still pass, and the German plugin page quietly describes a version
 * that no longer exists.
 *
 * So this test pins the German files to the English they were written from.
 * Edit readme.txt's prose and it fails, with the new stamp to paste once the
 * German is updated. The changelog is deliberately excluded — it changes every
 * release and is not part of the translated text.
 */
final class ReadmeTranslationTest extends TestCase {

	private const GERMAN = array(
		'.wordpress-org/readme-de_DE.md',
		'.wordpress-org/readme-de_DE_formal.md',
	);

	/**
	 * The sections of readme.txt that the German files cover.
	 *
	 * @return string
	 */
	private function translated_source(): string {
		$readme = (string) file_get_contents( dirname( __DIR__, 2 ) . '/readme.txt' );

		// From the short description down to the changelog, which is where the
		// translated text ends.
		$start = strpos( $readme, '== Description ==' );
		$end   = strpos( $readme, '== Changelog ==' );
		self::assertIsInt( $start );
		self::assertIsInt( $end );

		$short = trim( explode( "\n", substr( $readme, strpos( $readme, "\n\n" ) + 2 ) )[0] );

		return $short . "\n" . substr( $readme, $start, $end - $start );
	}

	public function test_the_german_listing_text_is_in_step_with_readme_txt(): void {
		$stamp = substr( hash( 'sha256', $this->translated_source() ), 0, 16 );

		foreach ( self::GERMAN as $relative ) {
			$path = dirname( __DIR__, 2 ) . '/' . $relative;
			self::assertFileExists( $path );
			$german = (string) file_get_contents( $path );

			self::assertSame(
				1,
				preg_match( '/^<!-- readme\.txt: ([0-9a-f]{16}) -->$/m', $german, $found ),
				"$relative needs a stamp line: <!-- readme.txt: $stamp -->"
			);
			self::assertSame(
				$stamp,
				$found[1],
				"readme.txt changed since $relative was written. Update the German for the changed "
					. "paragraphs, then set the stamp to: <!-- readme.txt: $stamp -->"
			);
		}
	}

	/**
	 * Both variants must cover the same paragraphs — a chunk added to one and
	 * forgotten in the other means one German plugin page is short a section.
	 */
	public function test_both_address_forms_cover_the_same_chunks(): void {
		$counts = array();
		foreach ( self::GERMAN as $relative ) {
			$german          = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $relative );
			$counts[ $relative ] = substr_count( $german, '**DE' );
		}
		self::assertSame( array_values( $counts )[0], array_values( $counts )[1], 'the du and Sie files differ in chunk count' );

		// And each is written in its own address form.
		$du  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . self::GERMAN[0] );
		$sie = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . self::GERMAN[1] );
		self::assertStringContainsString( 'Ihre Datenschutzerklärung', $sie );
		self::assertStringContainsString( 'deine Datenschutzerklärung', $du );
	}
}
