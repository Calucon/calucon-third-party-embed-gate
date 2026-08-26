<?php
/**
 * Derive the Austrian and Swiss readme translations from the two written by hand.
 *
 * The sibling of bin/derive-german-locales.php, for the OTHER half of the
 * German translation: the wordpress.org listing text. Same reasoning, same
 * rules — WordPress does not fall back between German locales, so a de_AT or
 * de_CH visitor to the plugin page sees English unless that locale has its own
 * translation, even though the de_DE text fits word for word.
 *
 *   readme-de_DE.po         (du)   — filled by hand from the GlotPress export
 *   readme-de_DE_formal.po  (Sie)  — filled by hand from the GlotPress export
 *   readme-de_AT.po         (du)   — de_DE verbatim
 *   readme-de_CH.po         (Sie)  — de_DE_formal, Swiss orthography
 *   readme-de_CH_informal.po (du)  — de_DE, Swiss orthography
 *
 * The Swiss conversion is ss for ß and «…» for „…“ — the same two rules
 * bin/derive-german-locales.php applies to the plugin strings, and the same
 * ones the Swiss team's own converter applies (see that file's note).
 *
 * These files are NOT shipped: .wordpress-org/ is excluded from the zip. They
 * exist to be imported at translate.wordpress.org, which is the only place a
 * listing translation can live.
 *
 * Usage: php bin/derive-readme-locales.php
 *
 * @package CaluconEmbedGate
 */

$root = dirname( __DIR__ ) . '/.wordpress-org';

$targets = array(
	// locale => [ source, swiss orthography?, GlotPress locale slug ]
	'de_AT'          => array( 'source' => 'de_DE', 'swiss' => false, 'slug' => 'de-at' ),
	'de_CH'          => array( 'source' => 'de_DE_formal', 'swiss' => true, 'slug' => 'de-ch' ),
	'de_CH_informal' => array( 'source' => 'de_DE', 'swiss' => true, 'slug' => 'de-ch' ),
);

/**
 * Swiss orthography. Applied to translations only — never to a msgid, whose
 * English is the key GlotPress matches on, and never to a header.
 *
 * @param string $text Translation line.
 * @return string
 */
function cg_readme_swiss( string $text ): string {
	return str_replace( array( 'ß', '„', '“' ), array( 'ss', '«', '»' ), $text );
}

foreach ( $targets as $locale => $spec ) {
	$source = $root . '/readme-' . $spec['source'] . '.po';
	if ( ! is_readable( $source ) ) {
		fwrite( STDERR, "missing source: $source\n" );
		exit( 1 );
	}

	$lines     = explode( "\n", (string) file_get_contents( $source ) );
	$out       = array();
	$in_msgstr = false;
	$header    = true;
	$changed   = 0;

	foreach ( $lines as $line ) {
		$is_msgstr = 0 === strpos( $line, 'msgstr ' );
		if ( 0 === strpos( $line, 'msgid ' ) ) {
			$in_msgstr = false;
		}
		if ( $is_msgstr ) {
			$in_msgstr = true;
		}
		// The header entry is the first msgstr; the blank line ends it.
		if ( $header && $in_msgstr && '' === trim( $line ) ) {
			$header = false;
		}

		if ( $header ) {
			// GlotPress writes the locale SLUG here, not the WP_Locale —
			// its own de_DE and de_DE_formal exports both say "de".
			$line = preg_replace( '/^"Language: [^\\\\"]*\\\\n"$/', '"Language: ' . $spec['slug'] . '\n"', $line );
		} elseif ( $spec['swiss'] && ( $is_msgstr || ( $in_msgstr && 0 === strpos( $line, '"' ) ) ) ) {
			$swiss = cg_readme_swiss( $line );
			if ( $swiss !== $line ) {
				++$changed;
			}
			$line = $swiss;
		}

		$out[] = $line;
	}

	$path = $root . '/readme-' . $locale . '.po';
	file_put_contents( $path, implode( "\n", $out ) );
	printf(
		"  readme-%-15s ← readme-%-13s %s\n",
		$locale . '.po',
		$spec['source'] . '.po',
		$spec['swiss'] ? "($changed lines converted to Swiss orthography)" : '(verbatim)'
	);
}
