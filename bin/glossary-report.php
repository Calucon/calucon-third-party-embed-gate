<?php
/**
 * Every place the German departs from the de_DE glossary — for a human to judge.
 *
 * The companion to tests/Unit/GlossaryTest.php, and deliberately the opposite
 * trade. The test forbids a short list of words known to be wrong: no false
 * positives, so it can fail a build, but it cannot find a term nobody has got
 * wrong yet. This reports every departure it can see, which does find new ones
 * — and produces a good deal of noise doing it, which is exactly why it prints
 * a report instead of failing anything.
 *
 * Most of what it lists is fine. The glossary maps some terms by context
 * (`header` is Header generally, Kopfzeile only in tables), some entries match
 * technical names that happen to contain the word (`default` fires on the CSP
 * directive `default-src`), and a translation is allowed to restructure a
 * sentence rather than carry a word across. Read it the way you would read a
 * spell-checker: most hits are noise, and the few that are not are worth the
 * scroll.
 *
 * Run it after translating, before submitting to translate.wordpress.org:
 *
 *     php bin/glossary-report.php            # the hand-written sources
 *     php bin/glossary-report.php --all      # every locale, derived included
 *
 * When something here is a real miss, fix the wording AND add the wrong word to
 * GlossaryTest::FORBIDDEN, so it cannot come back quietly.
 *
 * @package CaluconEmbedGate
 */

require_once __DIR__ . '/../tests/Support/PoReader.php';
require_once __DIR__ . '/../tests/Support/ReadmeMarkdown.php';

use CaluconEmbedGate\Tests\Support\PoReader;
use CaluconEmbedGate\Tests\Support\ReadmeMarkdown;

$root  = dirname( __DIR__ );
$all   = in_array( '--all', $argv, true );
$count = in_array( '--count', $argv, true );
$files = array(
	'languages/calucon-third-party-embed-gate-de_DE.po',
	'languages/calucon-third-party-embed-gate-de_DE_formal.po',
	'.wordpress-org/readme-de_DE.po',
	'.wordpress-org/readme-de_DE_formal.po',
	// The listing German is AUTHORED in these, and the gates check them; the
	// wide sweep has to reach them too, or the one German surface with a
	// public audience is checked only for the words already known to be wrong.
	'.wordpress-org/readme-de_DE.md',
	'.wordpress-org/readme-de_DE_formal.md',
);
if ( $all ) {
	foreach ( glob( $root . '/languages/*.po' ) as $path ) {
		$files[] = substr( $path, strlen( $root ) + 1 );
	}
	foreach ( glob( $root . '/.wordpress-org/readme-*.po' ) as $path ) {
		$files[] = substr( $path, strlen( $root ) + 1 );
	}
	$files = array_values( array_unique( $files ) );
}

$csv = $root . '/tests/Support/data/de-glossary.csv';
if ( ! is_readable( $csv ) ) {
	fwrite( STDERR, "missing $csv — export it from translate.wordpress.org/locale/de/default/glossary/\n" );
	exit( 1 );
}

// English term => every German the glossary allows for it. Terms whose German
// is the English are dropped: nothing can violate "Plugin" → "Plugin".
$glossary = array();
$handle   = fopen( $csv, 'r' );
fgetcsv( $handle );
while ( false !== ( $row = fgetcsv( $handle ) ) ) {
	if ( count( $row ) < 2 ) {
		continue;
	}
	$en = trim( (string) $row[0] );
	$de = trim( (string) $row[1] );
	if ( '' === $en || '' === $de || strtolower( $en ) === strtolower( $de ) ) {
		continue;
	}
	$glossary[ strtolower( $en ) ][] = $de;
}
fclose( $handle );

/**
 * Is this German carrying the prescribed word, allowing for inflection and
 * compounds? "Rand" counts inside "Ränder" and "Randbreite".
 *
 * @param string $german    Translation.
 * @param string $rendering Prescribed German, possibly offering alternatives.
 * @return bool
 */
function cg_renders( string $german, string $rendering ): bool {
	foreach ( preg_split( '#[/,]#', $rendering ) as $option ) {
		$option = trim( $option );
		if ( '' === $option ) {
			continue;
		}
		$stem = mb_substr( $option, 0, max( 4, (int) ( mb_strlen( $option ) * 0.7 ) ) );
		$stem = strtr( mb_strtolower( $stem ), array( 'ä' => '.', 'ö' => '.', 'ü' => '.' ) );
		if ( 1 === preg_match( '/' . preg_quote( $stem, '/' ) . '/iu', mb_strtolower( $german ) ) ) {
			return true;
		}
	}
	return false;
}

$total = 0;
foreach ( $files as $relative ) {
	$path = $root . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		continue;
	}

	$rows    = array();
	$entries = '.md' === substr( $relative, -3 )
		? ReadmeMarkdown::pairs( $path )
		: array_map( null, array_keys( PoReader::translations( $path ) ), array_values( PoReader::translations( $path ) ) );
	foreach ( $entries as list( $english, $german ) ) {
		foreach ( $glossary as $term => $renderings ) {
			if ( 1 !== preg_match( '/\b' . preg_quote( $term, '/' ) . '\b/iu', $english ) ) {
				continue;
			}
			foreach ( $renderings as $rendering ) {
				if ( cg_renders( $german, $rendering ) ) {
					continue 2;
				}
			}
			$context = $english;
			if ( preg_match( '/.{0,38}\b' . preg_quote( $term, '/' ) . '\b.{0,38}/iu', $english, $found ) ) {
				$context = '…' . $found[0] . '…';
			}
			$rows[] = sprintf( "  %-16s want %-28s %s", $term, implode( '/', $renderings ), $context );
		}
	}

	if ( ! $count ) {
		printf( "\n=== %s — %d to look at\n", basename( $relative ), count( $rows ) );
		sort( $rows );
		echo implode( "\n", $rows ) . "\n";
	}
	$total += count( $rows );
}

// --count prints the number alone, for bin/update-translations.sh to quote in
// its stage-4 summary. It used to slice this closing paragraph with
// "tail -3 | head -1", which selected the blank line above it, so every run
// printed an empty advisory — the one place the pipeline surfaces this sweep.
// A flag cannot drift the way line arithmetic does.
if ( $count ) {
	printf( "%d\n", $total );
	exit( 0 );
}

printf(
	"\n%d lines. Most will be context the glossary does not cover; fix the ones that are not,\nand add the wrong word to GlossaryTest::FORBIDDEN so it cannot return.\n",
	$total
);
