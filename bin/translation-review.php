<?php
/**
 * Read the German as German — the one check no test can perform.
 *
 * The style guide's first instruction is the one that matters most and is the
 * hardest to enforce: "Niemand liest gerne eine wörtliche Übersetzung."
 * Convey the meaning; do not carry the English word order across.
 *
 * A test cannot see this. A sentence can satisfy every rule in
 * StyleGuideTest and every entry in the glossary while remaining an English
 * sentence wearing German words. Real examples from this plugin, all of which
 * passed every mechanical check:
 *
 *   "Der Content-Security-Policy-Helfer: was eine Richtlinie ist, eine Prüfung
 *    der eigenen Website, die genauen Zeilen …"   — an English list carried
 *    across item by item into a construction German does not tolerate
 *   "eine Art für das Button-Symbol"              — "a kind for", morpheme by morpheme
 *   "was ein Mensch liest"                        — the dictionary's first hit for "person"
 *   "…, mit einem Klick rückgängig zu machen"     — an English apposition transplanted whole
 *
 * Each was found the same way: by a native speaker reading the German with the
 * English out of sight. With the source visible the eye checks correspondence
 * — does the German say what the English says? — which is the wrong question,
 * and the reason a literal translation reads fine during review and badly
 * afterwards.
 *
 * So this prints the German alone, worst suspects first, and makes you ask for
 * the English separately.
 *
 * Usage:
 *   php bin/translation-review.php                 # the 25 likeliest, German only
 *   php bin/translation-review.php --all           # every string
 *   php bin/translation-review.php --with-english  # after forming a judgement
 *   php bin/translation-review.php --formal        # the Sie branch
 *
 * It reports and never fails. Nothing here is a defect on its own; the ranking
 * only says where a literal translation would hide if there were one.
 *
 * @package CaluconEmbedGate
 */

require_once __DIR__ . '/../tests/Support/PoReader.php';
require_once __DIR__ . '/../tests/Support/ReadmeMarkdown.php';

use CaluconEmbedGate\Tests\Support\PoReader;
use CaluconEmbedGate\Tests\Support\ReadmeMarkdown;

$root         = dirname( __DIR__ );
$formal       = in_array( '--formal', $argv, true );
$with_english = in_array( '--with-english', $argv, true );
$show_all     = in_array( '--all', $argv, true );
$branch       = $formal ? 'de_DE_formal' : 'de_DE';

$files = array(
	"languages/calucon-third-party-embed-gate-$branch.po",
	".wordpress-org/readme-$branch.po",
	// The listing German is written here first. Reading it as German — with
	// the English out of sight — is the only check that catches a sentence
	// which is grammatical, glossary-clean and still not German prose.
	".wordpress-org/readme-$branch.md",
);

/**
 * How likely is this string to be hiding a literal translation?
 *
 * Not a defect score. These are the shapes English produces that German has
 * to be restructured out of: long sentences, dash asides, a colon introducing
 * a list, heavy comma nesting, and a German that tracks the English length too
 * closely to have been rethought.
 *
 * @param string $english Source.
 * @param string $german  Translation.
 * @return array{0:int,1:string[]} Score and the reasons for it.
 */
function cg_suspicion( string $english, string $german ): array {
	$why   = array();
	$score = 0;

	$words = str_word_count( strip_tags( $german ), 0, 'äöüßÄÖÜ' );
	if ( $words > 45 ) {
		$score += 3;
		$why[]  = "$words words";
	} elseif ( $words > 25 ) {
		$score += 1;
	}

	$dashes = preg_match_all( '/\s–\s|\s–\s/u', $german );
	if ( $dashes >= 2 ) {
		$score += 2;
		$why[]  = "$dashes dash asides";
	}

	if ( preg_match( '/:\s*\S+,\s*\S+.*,/u', $german ) ) {
		$score += 2;
		$why[]  = 'colon introducing a list';
	}

	$commas = substr_count( $german, ',' );
	if ( $commas >= 6 ) {
		$score += 2;
		$why[]  = "$commas commas";
	}

	// German is normally longer than English. A ratio near 1.0 across a long
	// string often means the shape was preserved rather than the meaning.
	$ratio = mb_strlen( $english ) > 80 ? mb_strlen( $german ) / mb_strlen( $english ) : 1.15;
	if ( $ratio < 1.02 ) {
		$score += 2;
		$why[]  = sprintf( 'length ratio %.2f', $ratio );
	}

	if ( preg_match( '/\b(Mensch|Ding|Sache|Weg,|Art für)\b/u', $german ) ) {
		$score += 1;
		$why[]  = 'a word that is often the dictionary\'s first hit';
	}

	return array( $score, $why );
}

$rows = array();
foreach ( $files as $relative ) {
	$path = $root . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		continue;
	}
	$entries = array();
	if ( '.md' === substr( $relative, -3 ) ) {
		$entries = ReadmeMarkdown::pairs( $path );
	} else {
		foreach ( PoReader::translations( $path ) as $english => $german ) {
			$entries[] = array( $english, $german );
		}
	}
	foreach ( $entries as list( $english, $german ) ) {
		list( $score, $why ) = cg_suspicion( $english, $german );
		$rows[] = array(
			'file'    => basename( $relative ),
			'score'   => $score,
			'why'     => $why,
			'english' => $english,
			'german'  => $german,
		);
	}
}

usort(
	$rows,
	static function ( array $a, array $b ): int {
		return $b['score'] <=> $a['score'];
	}
);

if ( ! $show_all ) {
	$rows = array_slice( $rows, 0, 25 );
}

printf(
	"Reading the %s branch as German%s.\n%s\n\n",
	$branch,
	$with_english ? ', with the source shown' : ' — the English is deliberately hidden',
	$with_english
		? 'Compare only after you have judged the German on its own.'
		: 'Read each one aloud. Would a German developer have written this sentence?'
);

$n = 0;
foreach ( $rows as $row ) {
	++$n;
	printf( "%2d. [%s]%s\n", $n, $row['file'], $row['why'] ? '  ' . implode( ', ', $row['why'] ) : '' );
	echo '    ' . wordwrap( $row['german'], 92, "\n    " ) . "\n";
	if ( $with_english ) {
		echo "    EN: " . wordwrap( $row['english'], 92, "\n        " ) . "\n";
	}
	echo "\n";
}

echo "Nothing here is automatically wrong. Anything that reads like English,\n"
	. "rewrite so it says the same thing the way German says it — then run\n"
	. "bash bin/update-translations.sh to re-verify and re-derive.\n";
