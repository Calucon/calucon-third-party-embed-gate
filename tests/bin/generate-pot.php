<?php
/**
 * Regenerate languages/calucon-third-party-embed-gate.pot and languages/strings.php.
 *
 * Usage: php tests/bin/generate-pot.php
 *
 * Extracts translatable strings from src/ and templates/: the WordPress
 * i18n calls (__, _e, esc_html_e, esc_attr_e, esc_html__, esc_attr__ with
 * the 'calucon-third-party-embed-gate' domain) and the injected-translator calls
 * ($t( '…' )) used by the WordPress-free layers (see Plugin.php for the
 * bridge). The $t() strings are also mirrored into languages/strings.php as
 * literal __() calls: translate.wordpress.org builds language packs with its
 * own parser, which only sees literal gettext calls — without the mirror,
 * every provider note and button label would be invisible to translators.
 *
 * @package CaluconEmbedGate
 */

$root  = dirname( __DIR__, 2 );
$files = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( 'php' === $file->getExtension() ) {
		$files[] = $file->getPathname();
	}
}
$files[] = $root . '/templates/placeholder.php';
// The editor script's __( '…', 'calucon-third-party-embed-gate' ) calls are extracted with the
// same pattern; wp_set_script_translations() serves them from the JSON files
// translators build from this POT.
$files[] = $root . '/assets/js/editor.js';
sort( $files );

$strings  = array(); // msgid => list of "file:line" references.
$gettext  = array(); // msgids the wp.org parser sees itself (literal gettext calls).
$injected = array(); // msgids only visible via $t() — these need the strings.php mirror.

foreach ( $files as $path ) {
	$source   = (string) file_get_contents( $path );
	$relative = substr( $path, strlen( $root ) + 1 );

	$patterns = array(
		// __( 'Text', 'calucon-third-party-embed-gate' ) and escaping variants.
		'/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'
			. '(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")\s*,\s*\'calucon-third-party-embed-gate\'\s*\)/s',
		// $t( 'Text' ) — the injected translator in WordPress-free code.
		'/\$t\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")\s*\)/s',
	);

	foreach ( $patterns as $index => $pattern ) {
		if ( ! preg_match_all( $pattern, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			continue;
		}
		foreach ( $matches as $match ) {
			$raw  = '' !== $match[1][0] ? $match[1][0] : ( isset( $match[2] ) ? $match[2][0] : '' );
			$text = stripcslashes( $raw );
			if ( '' === $text ) {
				continue;
			}
			$line = substr_count( substr( $source, 0, $match[0][1] ), "\n" ) + 1;

			$strings[ $text ][] = $relative . ':' . $line;
			if ( 0 === $index ) {
				$gettext[ $text ] = true;
			} else {
				$injected[ $text ][] = $relative . ':' . $line;
			}
		}
	}
}

ksort( $strings );
ksort( $injected );

preg_match( '/^ \* Version:\s+(\S+)/m', (string) file_get_contents( $root . '/calucon-third-party-embed-gate.php' ), $version_match );
$version = isset( $version_match[1] ) ? $version_match[1] : '0.0.0';

$pot = <<<'HEADER'
# Calucon Third-Party Embed Gate.
# This file is distributed under the same license as the Calucon Third-Party Embed Gate plugin.
msgid ""
msgstr ""
"Project-Id-Version: Calucon Third-Party Embed Gate {{VERSION}}\n"
"Report-Msgid-Bugs-To: https://github.com/Calucon/calucon-third-party-embed-gate/issues\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Domain: calucon-third-party-embed-gate\n"


HEADER;

// The version comes from the plugin header, so releases cannot ship a stale
// Project-Id-Version.
$pot = str_replace( '{{VERSION}}', $version, $pot );

foreach ( $strings as $text => $refs ) {
	foreach ( array_unique( $refs ) as $ref ) {
		$pot .= '#: ' . $ref . "\n";
	}
	$pot .= 'msgid "' . addcslashes( $text, "\"\\\n" ) . "\"\n";
	$pot .= "msgstr \"\"\n\n";
}

if ( ! is_dir( $root . '/languages' ) ) {
	mkdir( $root . '/languages' );
}
file_put_contents( $root . '/languages/calucon-third-party-embed-gate.pot', $pot );

// The strings.php map: one literal __() call per $t() string, keyed by its
// msgid. It serves two masters at once: translate.wordpress.org runs its own
// extractor (wp i18n make-pot) over the shipped plugin and only sees literal
// gettext calls — this file makes the WordPress-free layers' strings visible
// to translators — and at runtime the $t() bridge in src/Plugin.php includes
// this file and resolves through the returned map, so the plugin contains no
// gettext call with a variable argument anywhere.
$mirror = "<?php\n"
	. "/**\n"
	. " * Generated file — do not edit; regenerate with `php tests/bin/generate-pot.php`.\n"
	. " *\n"
	. " * Literal gettext calls for the \$t() strings defined in the\n"
	. " * WordPress-free layers (src/Providers/, src/Detection/, …), keyed by\n"
	. " * msgid. The translate.wordpress.org parser extracts the literal calls;\n"
	. " * at runtime the \$t() bridge in src/Plugin.php resolves translations by\n"
	. " * looking its msgid up in the returned map — so no gettext call in the\n"
	. " * plugin ever takes a variable argument.\n"
	. " *\n"
	. " * @package CaluconEmbedGate\n"
	. " */\n"
	. "\n"
	. "// phpcs:ignoreFile\n"
	. "\n"
	. "if ( ! defined( 'ABSPATH' ) ) {\n"
	. "\texit;\n"
	. "}\n"
	. "\n"
	. "return array(\n";

foreach ( $injected as $text => $refs ) {
	foreach ( array_unique( $refs ) as $ref ) {
		$mirror .= "\t// Defined at " . $ref . ".\n";
	}
	$escaped = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $text );
	$mirror .= "\t'" . $escaped . "' => __( '" . $escaped . "', 'calucon-third-party-embed-gate' ),\n";
}

$mirror .= ");\n";

file_put_contents( $root . '/languages/strings.php', $mirror );

echo count( $strings ) . " strings -> languages/calucon-third-party-embed-gate.pot\n";
echo count( $injected ) . " \$t() strings mirrored -> languages/strings.php\n";
