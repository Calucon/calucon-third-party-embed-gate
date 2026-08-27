<?php
/**
 * Guards the German against the de_DE glossary terms this plugin gets wrong.
 *
 * A reviewer at translate.wordpress.org flagged the translation for not
 * following the glossary and was right about six terms: Reiter for tab, Rahmen
 * for border, Eigene for custom, Positivliste for safelist, Auszüge for
 * excerpts, Umfrage for survey. Nothing in TranslationTest could have caught
 * them — those checks are structural (completeness, placeholders, address
 * form), and every one of these was a structurally perfect wrong word.
 *
 * **Why this is a forbidden-word list and not a glossary sweep.** The obvious
 * design — for every glossary term in the English, require the prescribed
 * German — was built first and produced mostly noise: the glossary maps
 * `header` to both Header and Kopfzeile depending on context, `default` fires
 * on the CSP directive `default-src`, and a translation is allowed to
 * restructure a sentence instead of carrying a word across. A test with fifty
 * documented exceptions is a test somebody eventually deletes.
 *
 * So the hard check is narrow and certain: a short list of words that are
 * *known* to be the wrong choice, each paired with the glossary term it
 * violates. Zero false positives, which is what keeps it switched on. It
 * cannot find a term nobody has got wrong yet — for that, run the advisory
 * sweep, which reports every departure for a human to judge:
 *
 *     php bin/glossary-report.php
 *
 * Refresh tests/Support/data/de-glossary.csv from the Export link at
 * https://translate.wordpress.org/locale/de/default/glossary/ when the German
 * team changes it. The formal glossary carries identical term pairs and
 * differs only in how its notes address the reader, so one copy serves both.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Tests\Support\PoReader;
use PHPUnit\Framework\TestCase;

/**
 * @group translation-sources
 */
final class GlossaryTest extends TestCase {

	/**
	 * Wrong German => [ the glossary term it violates, the German to use ].
	 *
	 * Every entry here is a mistake that was actually made and corrected, or
	 * the obvious near-miss for one. Add to it whenever a reviewer catches a
	 * term, so the same word cannot come back.
	 *
	 * The match is a plain case-sensitive substring, so it catches compounds
	 * too: "Rahmenbreite" trips the Rahmen rule.
	 *
	 * @var array<string,string[]>
	 */
	private const FORBIDDEN = array(
		'Reiter'          => array( 'tab', 'Tab' ),
		'Registerkarte'   => array( 'tab', 'Tab' ),
		'Rahmen'          => array( 'border', 'Rand' ),
		'Umrandung'       => array( 'border', 'Rand' ),
		'Positivliste'    => array( 'safelist', 'Freigabeliste' ),
		'Weisse Liste'    => array( 'safelist', 'Freigabeliste' ),
		'Sperrliste für'  => array( 'safelist', 'Freigabeliste' ),
		'Medienbibliothek' => array( 'media library', 'Mediathek' ),
		'Datenschutzrichtlinie' => array( 'privacy policy', 'Datenschutzerklärung' ),
		'Webseite '       => array( 'site', 'Website' ),
		'Vorschaubildchen' => array( 'thumbnail', 'Vorschaubild' ),
	);

	/**
	 * Terms whose German depends on what the English says, so the forbidden
	 * word is only wrong in some strings: wrong German => [ term, correct
	 * German, English fragment that makes it wrong ].
	 *
	 * "Umfrage" is right for poll and wrong for survey; "eigen…" is right for
	 * "your own" and wrong for "custom". A flat list would ban words that are
	 * correct three lines away.
	 *
	 * @var array<int,array{0:string,1:string,2:string,3:string}>
	 */
	private const CONDITIONAL = array(
		array( 'Umfrage', 'survey', 'Befragung', 'survey' ),
		array( 'Eigene', 'custom', 'Individuell', 'custom' ),
		array( 'Eigener', 'custom', 'Individueller', 'custom' ),
		array( 'Eigenes', 'custom', 'Individuelles', 'custom' ),
		array( 'eigene', 'custom', 'individuelle', 'custom' ),
		array( 'Auszüge', 'excerpts', 'Textauszüge', 'excerpt' ),
		array( 'Auszügen', 'excerpts', 'Textauszügen', 'excerpt' ),
	);

	/**
	 * English fragments that make a CONDITIONAL match a false alarm — the
	 * English uses the trigger word for something else entirely.
	 *
	 * @var string[]
	 */
	private const NOT_REALLY = array(
		'CSS custom properties', // The W3C feature name, not the adjective.
	);

	/**
	 * The files whose German is written by hand. The derived locales (de_AT
	 * and both Swiss ones) are generated from these, so checking the sources
	 * checks all five.
	 *
	 * @var string[]
	 */
	private const SOURCES = array(
		'languages/calucon-third-party-embed-gate-de_DE.po',
		'languages/calucon-third-party-embed-gate-de_DE_formal.po',
		'.wordpress-org/readme-de_DE.po',
		'.wordpress-org/readme-de_DE_formal.po',
	);

	public function test_no_known_wrong_glossary_term_comes_back(): void {
		$found = array();

		foreach ( self::SOURCES as $relative ) {
			$path = dirname( __DIR__, 2 ) . '/' . $relative;
			self::assertFileExists( $path );

			foreach ( PoReader::translations( $path ) as $english => $german ) {
				foreach ( self::FORBIDDEN as $wrong => list( $term, $right ) ) {
					if ( false !== strpos( $german, $wrong ) ) {
						$found[] = self::report( $relative, $wrong, $term, $right, $german );
					}
				}

				foreach ( self::CONDITIONAL as list( $wrong, $term, $right, $trigger ) ) {
					if ( false === strpos( $german, $wrong ) ) {
						continue;
					}
					if ( 1 !== preg_match( '/\b' . preg_quote( $trigger, '/' ) . '/i', $english ) ) {
						continue; // The English does not use that term here.
					}
					if ( self::false_alarm( $english ) ) {
						continue;
					}
					// A long string can use both words legitimately: the
					// settings-screen bullet says "custom note and button text"
					// AND "your own providers". If the prescribed term is
					// already there, the "custom" half is handled and the
					// remaining "eigen…" belongs to "own".
					if ( false !== stripos( $german, $right ) ) {
						continue;
					}
					$found[] = self::report( $relative, $wrong, $term, $right, $german );
				}
			}
		}

		self::assertSame(
			array(),
			$found,
			"A word the de_DE glossary rules out has come back.\n\n" . implode( "\n\n", $found )
				. "\n\nUse the prescribed term. If the glossary entry genuinely cannot apply here, "
				. "say so in GlossaryTest::NOT_REALLY with the reason.\n"
		);
	}

	/**
	 * The glossary this repo checks against must stay the real one.
	 */
	public function test_the_vendored_glossary_is_intact(): void {
		$path = dirname( __DIR__ ) . '/Support/data/de-glossary.csv';
		self::assertFileExists( $path, 'export it from translate.wordpress.org/locale/de/default/glossary/' );

		$rows = array_filter( array_map( 'str_getcsv', file( $path ) ) );
		self::assertSame( array( 'en', 'de', 'pos', 'description' ), $rows[0] );
		self::assertGreaterThan( 400, count( $rows ), 'the vendored glossary looks truncated' );

		// Every term this test forbids must still be prescribed by the CSV,
		// or the rule is enforcing a word the German team has moved away from.
		$prescribed = array();
		foreach ( array_slice( $rows, 1 ) as $row ) {
			if ( isset( $row[0], $row[1] ) ) {
				$prescribed[ strtolower( trim( $row[0] ) ) ][] = trim( $row[1] );
			}
		}
		foreach ( self::FORBIDDEN as $wrong => list( $term, $right ) ) {
			self::assertArrayHasKey(
				strtolower( $term ),
				$prescribed,
				"GlossaryTest forbids \"$wrong\" on account of \"$term\", which is no longer in the glossary"
			);
		}
	}

	/**
	 * @param string $english English source.
	 * @return bool
	 */
	private static function false_alarm( string $english ): bool {
		foreach ( self::NOT_REALLY as $fragment ) {
			if ( false !== stripos( $english, $fragment ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $file   Relative path.
	 * @param string $wrong  The word found.
	 * @param string $term   The English glossary term.
	 * @param string $right  The prescribed German.
	 * @param string $german The offending translation.
	 * @return string
	 */
	private static function report( string $file, string $wrong, string $term, string $right, string $german ): string {
		$excerpt = $german;
		if ( preg_match( '/.{0,45}' . preg_quote( $wrong, '/' ) . '.{0,45}/u', $german, $found ) ) {
			$excerpt = '…' . $found[0] . '…';
		}
		return sprintf( "  %s\n    \"%s\" → the glossary says %s (%s)\n    %s", basename( $file ), $wrong, $right, $term, $excerpt );
	}
}
