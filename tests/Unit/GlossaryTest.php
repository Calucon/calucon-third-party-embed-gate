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
use CaluconEmbedGate\Tests\Support\ReadmeMarkdown;
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
		'Datenschutzseite' => array( 'privacy policy', 'Datenschutzerklärung' ),
		'Webseite '       => array( 'site', 'Website' ),
		'Vorschaubildchen' => array( 'thumbnail', 'Vorschaubild' ),
		// The third way to avoid „Tab", after Reiter and Registerkarte. It is
		// here rather than in CONDITIONAL because the readme .md files carry
		// German-only chunks — the upgrade notices have no "**EN:**" locator —
		// and a CONDITIONAL rule needs English to trigger on, so it would be
		// inert in exactly the place this word actually appeared. Unconditional
		// is safe: this corpus says „Einstellungsansicht" for the screen and
		// „Einstellungsabschnitte" for the sections, so the compound has no
		// remaining legitimate sense.
		'Einstellungsbereich' => array( 'tab', 'Tab' ),
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
		// "screen" is Ansicht; "page" is Seite. Using Seite for a screen
		// collides the two glossary entries, which is how six "Seite"s for
		// "settings screen" reached the wp.org listing. The escape below
		// keeps a long string that legitimately says both words quiet.
		array( 'Einstellungsseite', 'screen', 'Einstellungsansicht', 'screen' ),
		array( 'Seite', 'screen', 'Ansicht', 'screen' ),
		// "disable" is deaktivieren, in all three forms the glossary lists.
		// "abschalten" reached ten strings across all four source files —
		// and, like "screen", sat in bin/glossary-report.php's output the
		// whole time. Note the limit: the separable "schalte … ab" cannot be
		// caught by a substring rule without also firing on "Regel-Schalter",
		// so the report still has to be read. This catches the rest.
		array( 'abgeschaltet', 'disable', 'deaktiviert', 'disabl' ),
		array( 'abschalten', 'disable', 'deaktivieren', 'disabl' ),
		array( 'abzuschalten', 'disable', 'zu deaktivieren', 'disabl' ),
		// "default" is Standard. "Voreinstellung" is ordinary German and
		// reads fine, which is why it survived eight strings while the rest of
		// the corpus used Standard 13 times — a wrong word is not always an
		// ugly one. The prescribed term is spelled out rather than bare
		// "Standard" so the escape below cannot be satisfied by an unrelated
		// "Standardtext" three clauses away.
		array( 'Voreinstellung', 'default', 'Standardeinstellung', 'default' ),
		// "required" is erfordert/erforderlich. Note what this rule does NOT
		// claim: three other strings answer "require" by restructuring —
		// "Grants require both …" as „zählt nur, wenn …", "no code required"
		// as „ohne Code", "Requirements:" as „Voraussetzungen:" — and all
		// three are right. A translation may drop a word; it may not swap the
		// prescribed one for a synonym, which is what „nötig" did.
		array( 'nötig', 'required', 'erforderlich', 'requir' ),
		// "enabled" is aktiviert, not the bare adjective „aktiv" — Simon's
		// call, and the right one: „aktiv ist" reads better than „aktiviert
		// ist" and the glossary still wins. The wrong forms carry their
		// following character so the substring cannot match inside
		// „aktiviert" or „aktivieren", which would fire on every string.
		array( 'aktiv ', 'enabled', 'aktiviert', 'enabled' ),
		array( 'aktiv,', 'enabled', 'aktiviert', 'enabled' ),
		array( 'aktiv;', 'enabled', 'aktiviert', 'enabled' ),
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

	/**
	 * The German listing text, which is authored here and only later shaped
	 * into the .po files above.
	 *
	 * This is where the German for wordpress.org is actually written, so it
	 * is the wrong place to be blind — and it was: until this was added, the
	 * 0.12.1 upgrade notice said „Einstellungsbereich" for "settings tab"
	 * with nothing to notice it. The .po files are checked separately because
	 * the two are not isomorphic and neither is generated from the other.
	 *
	 * @var string[]
	 */
	private const MARKDOWN_SOURCES = array(
		'.wordpress-org/readme-de_DE.md',
		'.wordpress-org/readme-de_DE_formal.md',
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

		foreach ( self::MARKDOWN_SOURCES as $relative ) {
			$path = dirname( __DIR__, 2 ) . '/' . $relative;
			self::assertFileExists( $path );

			foreach ( ReadmeMarkdown::pairs( $path ) as list( $english, $german ) ) {
				foreach ( self::FORBIDDEN as $wrong => list( $term, $right ) ) {
					if ( false !== strpos( $german, $wrong ) ) {
						$found[] = self::report( $relative, $wrong, $term, $right, $german );
					}
				}

				foreach ( self::CONDITIONAL as list( $wrong, $term, $right, $trigger ) ) {
					if ( false === strpos( $german, $wrong ) ) {
						continue;
					}
					if ( 1 !== preg_match( '/\\b' . preg_quote( $trigger, '/' ) . '/i', $english ) ) {
						continue;
					}
					if ( self::false_alarm( $english ) ) {
						continue;
					}
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
		// Every occurrence, not the first: a word like "Seite" is legitimate
		// for "page" and wrong for "screen" in the same sentence, and quoting
		// only the first hit sends the reader to fix the innocent one.
		$excerpts = array();
		if ( preg_match_all( '/.{0,45}' . preg_quote( $wrong, '/' ) . '.{0,45}/u', $german, $found ) ) {
			foreach ( array_slice( $found[0], 0, 3 ) as $hit ) {
				$excerpts[] = '    …' . $hit . '…';
			}
		}
		if ( array() === $excerpts ) {
			$excerpts[] = '    ' . $german;
		}
		return sprintf(
			"  %s\n    \"%s\" → the glossary says %s (%s)\n%s",
			basename( $file ),
			$wrong,
			$right,
			$term,
			implode( "\n", $excerpts )
		);
	}


}
