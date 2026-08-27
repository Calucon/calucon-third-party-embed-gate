<?php
/**
 * Reader for the chunked German listing files.
 *
 * `.wordpress-org/readme-de_DE.md` and `-de_DE_formal.md` are where the German
 * for the wordpress.org listing is authored: alternating "**EN:** …" locator
 * lines and "**DE:** …" (or "**DE (Antwort):** …") translations, in readme.txt
 * order. They are not PO files and nothing generates one from the other.
 *
 * Four callers need to read them — GlossaryTest, StyleGuideTest,
 * bin/glossary-report.php and bin/translation-review.php — so the parser lives
 * here rather than being written four times and drifting three ways.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Support;

/**
 * Parses the readme markdown into the pairs and chunks the checks want.
 */
final class ReadmeMarkdown {

	/**
	 * English/German pairs, in file order.
	 *
	 * Each German chunk is paired with the last English one seen, which is
	 * what a context-dependent glossary rule needs in order to know which
	 * sense a word carries.
	 *
	 * The limit worth knowing: the upgrade-notice chunks are German-only and
	 * carry no "**EN:**" line, so they inherit whatever English came before
	 * them. Any rule that triggers on the English is therefore effectively
	 * inert in those chunks, and a word that must be caught there has to be
	 * caught unconditionally.
	 *
	 * @param string $path Absolute path to a readme markdown file.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function pairs( string $path ): array {
		$pairs   = array();
		$english = '';
		foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $line ) {
			if ( 0 === strpos( $line, '**EN:**' ) ) {
				$english = trim( substr( $line, 7 ) );
				continue;
			}
			$german = self::german_of( $line );
			if ( '' !== $german ) {
				$pairs[] = array( $english, $german );
			}
		}
		return $pairs;
	}

	/**
	 * The German chunks alone, in file order.
	 *
	 * The "**EN:**" lines are the translator's locator, not shipped text —
	 * running German orthography rules over them would fail on every straight
	 * quote and every "you".
	 *
	 * @param string $path Absolute path to a readme markdown file.
	 * @return string[]
	 */
	public static function chunks( string $path ): array {
		$out = array();
		foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $line ) {
			$german = self::german_of( $line );
			if ( '' !== $german ) {
				$out[] = $german;
			}
		}
		return $out;
	}

	/**
	 * The German text of one line, or '' when the line is not a German chunk.
	 *
	 * @param string $line One line of the markdown.
	 * @return string
	 */
	private static function german_of( string $line ): string {
		if ( 1 !== preg_match( '/^\*\*DE[^:]*:\*\*(.*)$/u', $line, $found ) ) {
			return '';
		}
		return trim( $found[1] );
	}
}
