<?php
/**
 * The German against the de_DE style guide.
 *
 * Rules taken from the four pages of
 * https://de.wordpress.org/team/handbook/polyglots-team/style-guide/
 * (Allgemein, Rechtschreibung, Stilistisches, Titel). Each check below names
 * the rule it enforces; where the guide states it in German, the German is
 * quoted, so a disagreement can be taken up with the handbook rather than with
 * this file.
 *
 * **What this cannot check.** The guide's most important instruction is
 * "Niemand liest gerne eine wörtliche Übersetzung" — convey the meaning, do
 * not carry the word order across. That is invisible to a test: a sentence can
 * satisfy every rule here, and every entry in the glossary, while being an
 * English sentence wearing German words. Every problem of that kind found in
 * this project so far was found by a person reading the German aloud with the
 * English out of sight, which is what `php bin/translation-review.php` sets
 * up. Do not mistake a green run here for good German.
 *
 * The same applies to the guide's anthropomorphism rule ("Hardware oder
 * Software sollten keine menschlichen Eigenschaften oder Gefühle zugeschrieben
 * werden") and its rules for titles, which need to know which strings are
 * titles. Those stay with the human too.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Tests\Support\PoReader;
use PHPUnit\Framework\TestCase;

/**
 * @group translation-sources
 */
final class StyleGuideTest extends TestCase {

	private const NBSP = "\u{00A0}";

	/** The informal branch. */
	private const INFORMAL = array(
		'languages/calucon-third-party-embed-gate-de_DE.po',
		'.wordpress-org/readme-de_DE.po',
		'.wordpress-org/readme-de_DE.md',
	);

	/** The formal branch. */
	private const FORMAL = array(
		'languages/calucon-third-party-embed-gate-de_DE_formal.po',
		'.wordpress-org/readme-de_DE_formal.po',
		'.wordpress-org/readme-de_DE_formal.md',
	);

	/**
	 * du-imperatives, curated rather than derived.
	 *
	 * A morphological detector for German imperatives cannot be made
	 * false-positive-free — "Sperrt" is third-person in a bullet list and
	 * second-person plural in a sentence — so this is a list of forms that
	 * have actually appeared, in the manner of GlossaryTest::FORBIDDEN. It
	 * caught "Vergib" in the formal file during the 0.12.0 release, after an
	 * audit built on a hand-written verb list had missed it.
	 *
	 * @var string[]
	 */
	private const DU_IMPERATIVES = array(
		'Aktiviere', 'Behalte', 'Beachte', 'Denk', 'Denke', 'Ergänze', 'Ersetze',
		'Füge', 'Frag', 'Frage', 'Gehe', 'Gib', 'Halte', 'Klicke', 'Kopiere',
		'Lade', 'Lass', 'Lies', 'Mach', 'Melde', 'Nimm', 'Nutze', 'Probiere',
		'Prüfe', 'Schalte', 'Schau', 'Schicke', 'Schreibe', 'Setze', 'Sieh',
		'Speichere', 'Stell', 'Stelle', 'Suche', 'Teste', 'Trage', 'Vergib',
		'Vergiss', 'Verwende', 'Wähle', 'Wechsle', 'Zeige', 'Öffne',
		'aktiviere', 'füge', 'gib', 'klicke', 'lies', 'nimm', 'nutze',
		'probiere', 'prüfe', 'schalte', 'setze', 'suche', 'trage', 'wähle',
		'öffne',
	);

	/**
	 * "Wir verwenden typografische Anführungszeichen („xxx“)" — Rechtschreibung.
	 *
	 * Straight quotes inside HTML attributes and inside <code> are markup, not
	 * prose, and stay as they are.
	 */
	public function test_quotes_are_typographic(): void {
		foreach ( self::each_translation() as $where => $german ) {
			$prose = self::prose_only( $german );
			self::assertStringNotContainsString(
				'"',
				$prose,
				"$where uses a straight quote; the style guide asks for „…“"
			);
		}
	}

	/**
	 * "Das &-Zeichen wird im Deutschen selten verwendet. Daher sollte auf
	 * „und“ zurückgegriffen werden." — Rechtschreibung.
	 */
	public function test_ampersand_is_spelled_out(): void {
		foreach ( self::each_translation() as $where => $german ) {
			// &amp; &#8222; &nbsp; and friends are entities, not the word "and".
			self::assertSame(
				0,
				preg_match( '/&(?!amp;|nbsp;|quot;|lt;|gt;|#\d)/', self::prose_only( $german ) ),
				"$where uses \"&\"; the style guide asks for „und“"
			);
		}
	}

	/**
	 * "Vor den Gedankenstrich setzt man ein geschütztes Leerzeichen." and
	 * "Zwischen Zahlen und Prozentzeichen oder Maßen steht ein geschütztes
	 * Leerzeichen." — Rechtschreibung.
	 *
	 * php bin/fix-style.php applies both; it only ever touches whitespace.
	 */
	public function test_protected_spaces_before_dashes_and_units(): void {
		foreach ( self::each_translation() as $where => $german ) {
			self::assertSame(
				0,
				preg_match( '/(?<=\S) –\s/u', $german ),
				"$where has a plain space before a Gedankenstrich — run php bin/fix-style.php"
			);
			self::assertSame(
				0,
				preg_match( '/\d (?:%|(?:px|kg|MB|KB|GB)\b)/u', $german ),
				"$where has a plain space before a unit — run php bin/fix-style.php"
			);
		}
	}

	/**
	 * "WordPress wird immer „WordPress“ geschrieben." — Allgemein.
	 */
	public function test_wordpress_is_spelled_wordpress(): void {
		foreach ( self::each_translation() as $where => $german ) {
			self::assertSame( 0, preg_match( '/\bWordpress\b/', $german ), "$where misspells WordPress" );
		}
	}

	/**
	 * "Vermeide umgangssprachliche Verkürzungen" — Stilistisches names fürs,
	 * vorm, drauf and nochmal explicitly.
	 */
	public function test_no_colloquial_contractions(): void {
		foreach ( self::each_translation() as $where => $german ) {
			self::assertSame(
				0,
				preg_match( '/\b(fürs|vorm|drauf|nochmal|garnicht|runter|rauf)\b/iu', $german ),
				"$where uses a colloquial contraction; write it out"
			);
		}
	}

	/**
	 * "Verwende nur gängige Abkürzungen (z. B., d. h.)" — Stilistisches. Both
	 * carry a space between the letters.
	 */
	public function test_abbreviations_keep_their_space(): void {
		foreach ( self::each_translation() as $where => $german ) {
			self::assertSame(
				0,
				preg_match( '/\b(z\.B\.|d\.h\.|u\.a\.|i\.d\.R\.)/u', $german ),
				"$where writes an abbreviation without its space (z. B., d. h.)"
			);
		}
	}

	/**
	 * "Bei der Übersetzung von WordPress verwenden wir als Standard die
	 * informelle Schreibweise (kleingeschriebenes „du“). Parallel dazu werden
	 * Übersetzungen in formeller Schreibweise (großgeschriebenes „Sie“) in
	 * einem eigenen Zweig angeboten." — Allgemein.
	 *
	 * The two branches must not bleed into each other. This is the check with
	 * the best record in this project: it is what catches a Sie-form left in
	 * the du file after a copy, or the reverse.
	 */
	public function test_the_two_address_forms_stay_apart(): void {
		$du  = '/\b(du|dich|dir|dein|deine|deinem|deinen|deiner|deines)\b/iu';
		// Mid-sentence only. Sentence-initial "Sie" is ambiguous — "Eine
		// Content-Security-Policy … Sie legt fest, …" is the ordinary pronoun
		// for a feminine noun, not the polite form. Requiring a preceding
		// lowercase letter or comma keeps this check free of false alarms;
		// a Sie-branch string copied into the du file always trips it
		// somewhere, because the polite form is capitalised everywhere.
		$sie = '/[a-zäöüß,;)] (Sie|Ihnen|Ihre|Ihrem|Ihren|Ihrer|Ihres)\b/u';

		foreach ( self::each_translation( self::FORMAL ) as $where => $german ) {
			// "Deutschland (du und Sie)" names the two branches; it addresses
			// nobody. The English says exactly the same thing.
			if ( false !== strpos( $german, 'du und Sie' ) ) {
				continue;
			}
			self::assertSame( 0, preg_match( $du, $german ), "$where: du-form in the formal branch" );
		}

		foreach ( self::each_translation( self::INFORMAL ) as $where => $german ) {
			if ( false !== strpos( $german, 'du und Sie' ) ) {
				continue;
			}
			self::assertSame( 0, preg_match( $sie, $german ), "$where: Sie-form in the informal branch" );
		}
	}

	/**
	 * The formal branch must not carry a du-imperative either. Pronouns are
	 * only half of the address form: "Nimm den einfachen Einbettungscode" has
	 * no pronoun at all and is still du.
	 */
	public function test_the_formal_branch_has_no_du_imperative(): void {
		$pattern = '/(?:^|[.!?:„–]\s*|\n)(' . implode( '|', self::DU_IMPERATIVES ) . ')\b(?!\s+Sie)/u';

		foreach ( self::each_translation( self::FORMAL ) as $where => $german ) {
			self::assertSame(
				0,
				preg_match( $pattern, $german, $found ),
				"$where: du-imperative in the formal branch" . ( isset( $found[1] ) ? " (\"{$found[1]}\")" : '' )
			);
		}
	}

	/**
	 * "du" is lowercase in the informal branch — Allgemein.
	 */
	public function test_du_is_lowercase(): void {
		foreach ( self::each_translation( self::INFORMAL ) as $where => $german ) {
			self::assertSame(
				0,
				preg_match( '/[a-zäöüß,;)] (Du|Dein|Deine|Deinem|Deinen|Deiner|Deines|Dir|Dich)\b/u', $german ),
				"$where capitalises a du-form mid-sentence"
			);
		}
	}

	/**
	 * bin/fix-style.php must never be able to change a word — it is run
	 * unattended, over files nobody re-reads afterwards.
	 *
	 * The second half checks that the committed German is a fixed point of the
	 * fixer: run it again and it has nothing to do. That is worth more than it
	 * looks, because it pins the fixer and this test to each other. They encode
	 * the same two whitespace rules twice, and they have already drifted once —
	 * a `%` rule shipped in both that could never match, because `\b` cannot
	 * fire between `%` and a space. Either half alone would have stayed green.
	 *
	 * It also reaches the two files the rest of this class cannot see: INFORMAL
	 * and FORMAL list only the four .po files, so the German in the two readme
	 * .md files had no style coverage at all until this assertion, and was
	 * carrying a missing protected space in the 0.12.1 upgrade notice.
	 */
	public function test_the_style_fixer_only_ever_touches_whitespace(): void {
		$root   = dirname( __DIR__, 2 );
		$script = $root . '/bin/fix-style.php';
		self::assertFileExists( $script );

		$source = (string) file_get_contents( $script );
		self::assertStringNotContainsString(
			'str_replace',
			$source,
			'bin/fix-style.php must not do literal word replacement'
		);

		$command = sprintf(
			'cd %s && %s bin/fix-style.php --dry-run 2>&1',
			escapeshellarg( $root ),
			escapeshellarg( PHP_BINARY )
		);
		$report  = (string) shell_exec( $command );

		self::assertSame(
			1,
			preg_match( '/Would fix (\d+) line\(s\)/', $report, $found ),
			"bin/fix-style.php --dry-run did not report a count:\n$report"
		);
		self::assertSame(
			'0',
			$found[1],
			"The German is not a fixed point of bin/fix-style.php — run it:\n\n"
				. "    php bin/fix-style.php\n\n" . $report
		);
	}

	/**
	 * Every translated string in the given files, keyed by a readable location.
	 *
	 * @param string[]|null $files Defaults to both branches.
	 * @return array<string,string>
	 */
	private static function each_translation( ?array $files = null ): array {
		$files = $files ?? array_merge( self::INFORMAL, self::FORMAL );
		$out   = array();

		foreach ( $files as $relative ) {
			$path = dirname( __DIR__, 2 ) . '/' . $relative;
			self::assertFileExists( $path );

			// The listing German is authored as markdown, not as a PO. It is
			// the same German, published on the same plugin page, and it was
			// unchecked here until a reviewer-grade word turned up in it.
			if ( '.md' === substr( $relative, -3 ) ) {
				foreach ( self::markdown_chunks( $path ) as $index => $german ) {
					$out[ basename( $relative ) . ' :: chunk ' . $index ] = $german;
				}
				continue;
			}

			foreach ( PoReader::translations( $path ) as $english => $german ) {
				$key = basename( $relative ) . ' :: ' . mb_substr( $english, 0, 60 );
				$out[ $key ] = $german;
			}
		}

		return $out;
	}

	/**
	 * The German chunks of a readme markdown file, in order.
	 *
	 * Only the "**DE…:**" lines. The "**EN:**" lines are the English locator
	 * the translator works against and are not shipped text — running German
	 * orthography rules over them would fail on every straight quote and every
	 * "you".
	 *
	 * @param string $path Absolute path.
	 * @return string[]
	 */
	private static function markdown_chunks( string $path ): array {
		$out = array();
		foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $line ) {
			if ( 1 === preg_match( '/^\*\*DE[^:]*:\*\*(.*)$/u', $line, $found ) ) {
				$german = trim( $found[1] );
				if ( '' !== $german ) {
					$out[] = $german;
				}
			}
		}
		return $out;
	}

	/**
	 * The German with markup removed, so a quote inside an HTML attribute or a
	 * code span is not mistaken for prose punctuation.
	 *
	 * @param string $german Translation.
	 * @return string
	 */
	private static function prose_only( string $german ): string {
		$german = preg_replace( '#<code>.*?</code>#su', '', $german );
		$german = preg_replace( '#`[^`]*`#u', '', $german );
		$german = preg_replace( '#<[^>]*>#u', '', $german );
		// A bare HTML attribute, outside any tag: the Compatibility screen
		// tells the owner to switch Cloudflare's Rocket Loader off with
		// data-cfasync="false", and a German reader copies that verbatim. It
		// is markup quoted as an example, which is the same reason the three
		// rules above exist — but it has no tag around it to be caught by
		// them, and the cell is rendered with esc_html(), so <code> would
		// show up literally on the screen.
		$german = preg_replace( '#\b[a-z][a-z0-9-]*="[^"]*"#u', '', $german );
		return preg_replace( '#\[[a-z_]+\]#u', '', $german );
	}
}
