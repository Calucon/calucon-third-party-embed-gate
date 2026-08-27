<?php
/**
 * Apply the German style guide's whitespace rules to the translation sources.
 *
 * The companion to tests/Unit/StyleGuideTest.php, in the same relationship as
 * `composer lint:fix` to `composer lint`: the test says what is wrong, this
 * fixes the half that is mechanical.
 *
 * Only whitespace is touched. Nothing here can change a word, which is what
 * makes it safe to run unattended — and is asserted in the test suite by
 * normalising every protected space back to a plain one and requiring the
 * result to be byte-identical to the input.
 *
 * The rules, from
 * https://de.wordpress.org/team/handbook/polyglots-team/style-guide/rechtschreibung/
 *
 *   - "Vor den Gedankenstrich setzt man ein geschütztes Leerzeichen."
 *   - "Zwischen Zahlen und Prozentzeichen oder Maßen steht ein geschütztes
 *     Leerzeichen." (40 %, 25 kg)
 *
 * Expect to need this often. U+00A0 is invisible in every editor and diff, so
 * a new string will almost always be written with a plain space — and the
 * invisibility cuts both ways: a literal search-and-replace over a string that
 * already contains one silently matches nothing. That trap has cost this repo
 * time twice.
 *
 * Usage: php bin/fix-style.php [--dry-run]
 *
 * @package CaluconEmbedGate
 */

$root    = dirname( __DIR__ );
$dry_run = in_array( '--dry-run', $argv, true );

/** The hand-written German. Derived locales regenerate from these. */
$sources = array(
	'languages/calucon-third-party-embed-gate-de_DE.po',
	'languages/calucon-third-party-embed-gate-de_DE_formal.po',
	'.wordpress-org/readme-de_DE.po',
	'.wordpress-org/readme-de_DE_formal.po',
	'.wordpress-org/readme-de_DE.md',
	'.wordpress-org/readme-de_DE_formal.md',
);

const NBSP = "\u{00A0}";

/**
 * Put a protected space before a Gedankenstrich and between a number and its
 * unit, in translated text only.
 *
 * @param string $text A line of German.
 * @return string
 */
function cg_protect_spaces( string $text ): string {
	// " – " → nbsp before the dash. Only a dash with space on BOTH sides is a
	// Gedankenstrich; "Button-Größe/-Stil" and "–, was" must be left alone.
	$text = preg_replace( '/(?<=\S) (–\s)/u', NBSP . '$1', $text );

	// "40 %" and "25 kg". Digits then a plain space then a unit.
	$text = preg_replace( '/(\d) (%|(?:px|kg|MB|KB|GB|ms)\b)/u', '$1' . NBSP . '$2', $text );

	return $text;
}

$changed_files = 0;
$changed_lines = 0;

foreach ( $sources as $relative ) {
	$path = $root . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "missing: $relative\n" );
		exit( 1 );
	}

	$is_po = '.po' === substr( $relative, -3 );
	$lines = explode( "\n", (string) file_get_contents( $path ) );
	$out   = array();
	$hits  = 0;
	$in_translation = ! $is_po; // Markdown is German throughout.

	foreach ( $lines as $line ) {
		if ( $is_po ) {
			if ( 0 === strpos( $line, 'msgid ' ) || 0 === strpos( $line, '#' ) ) {
				$in_translation = false;
			} elseif ( 0 === strpos( $line, 'msgstr ' ) ) {
				$in_translation = true;
			}
			// A bare "…" continuation line belongs to whatever came before.
		} elseif ( 0 === strpos( $line, '**EN:**' ) ) {
			// The English half of the markdown is the locator, not translation.
			$out[] = $line;
			continue;
		}

		if ( $in_translation ) {
			$fixed = cg_protect_spaces( $line );
			if ( $fixed !== $line ) {
				++$hits;
				$line = $fixed;
			}
		}

		$out[] = $line;
	}

	if ( $hits > 0 ) {
		++$changed_files;
		$changed_lines += $hits;
		if ( ! $dry_run ) {
			file_put_contents( $path, implode( "\n", $out ) );
		}
	}

	printf( "  %-56s %s%d\n", $relative, $dry_run ? 'would fix ' : 'fixed ', $hits );
}

printf(
	"\n%s %d line(s) across %d file(s).%s\n",
	$dry_run ? 'Would fix' : 'Fixed',
	$changed_lines,
	$changed_files,
	$dry_run ? '' : ' Re-derive the other locales: bash bin/update-translations.sh'
);
