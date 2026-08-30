<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Tests\Support\PoReader;
use CaluconEmbedGate\Tests\Support\ReadmeMarkdown;
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

	/**
	 * @group translation-sources
	 */
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
	/**
	 * @group translation-sources
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

	/**
	 * The Austrian and Swiss listing translations are derived, not authored:
	 * de_AT is the du text verbatim, the Swiss pair is the same text with ss
	 * for ß and «…» for „…“. bin/derive-readme-locales.php regenerates them,
	 * and this fails if someone hand-edits a derived file or forgets to
	 * re-run it after changing a source.
	 *
	 * The two rules are the Swiss team's own: the output was checked against
	 * their converter at po.wpswitzerland.ch and matched entry for entry.
	 *
	 * With one exemption, restated here rather than imported so that this stays
	 * an independent check of the script: a ß with no letter beside it is the
	 * character being named, not used ("mit ss statt ß" — the FAQ answer that
	 * explains the Swiss rule), and it survives the conversion.
	 */
	/**
	 * @group translation-derived
	 */
	public function test_derived_readme_locales_match_their_source(): void {
		$dir = dirname( __DIR__, 2 ) . '/.wordpress-org/';

		$swiss = static function ( string $text ): string {
			$text = preg_replace( '/(?<=\p{L})ß|ß(?=\p{L})/u', 'ss', $text );

			return str_replace( array( '„', '“' ), array( '«', '»' ), (string) $text );
		};

		$cases = array(
			'de_AT'          => array( 'de_DE', false ),
			'de_CH'          => array( 'de_DE_formal', true ),
			'de_CH_informal' => array( 'de_DE', true ),
		);

		foreach ( $cases as $locale => list( $source, $is_swiss ) ) {
			$path = $dir . 'readme-' . $locale . '.po';
			self::assertFileExists( $path, "run bin/derive-readme-locales.php to create readme-$locale.po" );

			$expected = self::translations_of( $dir . 'readme-' . $source . '.po' );
			$actual   = self::translations_of( $path );

			if ( $is_swiss ) {
				$expected = array_map( $swiss, $expected );
			}

			self::assertSame(
				$expected,
				$actual,
				"readme-$locale.po is out of step with readme-$source.po — re-run bin/derive-readme-locales.php"
			);
		}
	}

	/**
	 * msgid => msgstr for one PO file, unwrapped.
	 *
	 * @param string $path PO file.
	 * @return array<string,string>
	 */
	private static function translations_of( string $path ): array {
		$out     = array();
		$msgid   = null;
		$target  = null;
		$current = '';

		foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $line ) {
			if ( 0 === strpos( $line, 'msgid ' ) ) {
				if ( 'msgstr' === $target && null !== $msgid ) {
					$out[ $msgid ] = $current;
				}
				$msgid   = self::po_string( substr( $line, 6 ) );
				$target  = 'msgid';
				$current = '';
			} elseif ( 0 === strpos( $line, 'msgstr ' ) ) {
				$target  = 'msgstr';
				$current = self::po_string( substr( $line, 7 ) );
			} elseif ( '"' === substr( $line, 0, 1 ) ) {
				if ( 'msgstr' === $target ) {
					$current .= self::po_string( $line );
				} else {
					$msgid .= self::po_string( $line );
				}
			}
		}
		if ( 'msgstr' === $target && null !== $msgid ) {
			$out[ $msgid ] = $current;
		}
		unset( $out[''] ); // The header entry.

		return $out;
	}

	/**
	 * @param string $quoted A quoted PO fragment.
	 * @return string
	 */
	private static function po_string( string $quoted ): string {
		$quoted = trim( $quoted );
		if ( '"' !== substr( $quoted, 0, 1 ) ) {
			return '';
		}
		return stripcslashes( substr( $quoted, 1, -1 ) );
	}

	/**
	 * The listing German exists in two shapes, and they must not drift apart.
	 *
	 * `.md` is where it is authored — chunked, English as locator, stamped
	 * against readme.txt. `.po` is the shape GlotPress imports. Neither is
	 * generated from the other, they are not isomorphic (56 German chunks
	 * against 175 msgids), and until now nothing compared them. Commit
	 * ace3c9e already changed one without the other.
	 *
	 * Exact equality is impossible, so this pairs a chunk with a msgstr that
	 * begins the same way and then requires them to be identical. That is the
	 * shape a real drift takes: someone fixes a wording in the file they had
	 * open, and the twin keeps the old sentence with the same opening.
	 *
	 * @group translation-sources
	 */
	public function test_the_markdown_and_po_listing_text_agree(): void {
		$root  = dirname( __DIR__, 2 );
		$pairs = array(
			'.wordpress-org/readme-de_DE.md'        => '.wordpress-org/readme-de_DE.po',
			'.wordpress-org/readme-de_DE_formal.md' => '.wordpress-org/readme-de_DE_formal.po',
		);

		$drifted = array();
		foreach ( $pairs as $markdown => $po ) {
			$md_path = $root . '/' . $markdown;
			$po_path = $root . '/' . $po;
			self::assertFileExists( $md_path );
			self::assertFileExists( $po_path );

			$translations = array_map( 'self::markup_free', array_values( PoReader::translations( $po_path ) ) );

			$chunks = ReadmeMarkdown::chunks( $md_path );
			self::assertSame(
				ReadmeMarkdown::expected_chunk_count( $md_path ),
				count( $chunks ),
				"the parser did not return every German chunk in {$markdown} — a partial loss makes this test pass, not fail"
			);

			foreach ( array_map( 'self::markup_free', $chunks ) as $chunk ) {
				// Short chunks are headings and labels; several legitimately
				// share an opening, and they are not where drift hides.
				if ( mb_strlen( $chunk ) < 60 ) {
					continue;
				}
				$closest = '';
				$best    = 0.0;
				foreach ( $translations as $german ) {
					if ( $chunk === $german ) {
						$closest = '';
						break;
					}
					// Similarity, not a shared opening: a wording fix often
					// lands in the first sentence, and a prefix pairing then
					// fails to pair the two at all and reports nothing —
					// silence that looks exactly like agreement.
					similar_text( $chunk, $german, $percent );
					if ( $percent > $best ) {
						$best    = $percent;
						$closest = $german;
					}
				}

				if ( '' !== $closest && $best >= 90.0 ) {
					$german = $closest;
					$drifted[] = sprintf(
						"  %s vs %s\n    .md: …%s\n    .po: …%s",
						basename( $markdown ),
						basename( $po ),
						mb_substr( $chunk, 0, 150 ),
						mb_substr( $german, 0, 150 )
					);
				}
			}
		}

		self::assertSame(
			array(),
			$drifted,
			"The two shapes of the German listing text have drifted apart. These pairs are\n"
				. "90%+ the same sentence and not identical, which means a wording change reached\n"
				. "one file and not the other — and only the .po is imported to GlotPress.\n\n"
				. implode( "\n\n", $drifted )
		);
	}


	/**
	 * The German of a chunk with its markup removed.
	 *
	 * The two files carry the same sentences in different markup — the .md in
	 * markdown, the .po in the HTML subset wordpress.org renders — so
	 * comparing them raw reports every code span as a difference. Only the
	 * markup is stripped: the words, punctuation and protected spaces are what
	 * this test is about and are left exactly as they are.
	 *
	 * @param string $text Chunk or translation.
	 * @return string
	 */
	private static function markup_free( string $text ): string {
		// Entities first: the .po escapes literal tag names the owner reads
		// (&lt;iframe&gt;) where the markdown just writes them.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Link text survives, target does not: [text](url) and <a …>text</a>.
		$text = (string) preg_replace( '#\[([^\]]*)\]\([^)]*\)#u', '$1', $text );
		$text = (string) preg_replace( '#<a [^>]*>(.*?)</a>#su', '$1', $text );
		$text = (string) preg_replace( '#</?(?:code|strong|em)>#u', '', $text );
		$text = str_replace( array( '`', '**', '*' ), '', $text );
		// The markdown carries the readme's list numbering; the PO does not.
		$text = (string) preg_replace( '/^\\s*(?:\\d+\\.|[-–])\\s+/u', '', $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

}
