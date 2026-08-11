<?php
/**
 * Regenerate languages/consent-gate.pot.
 *
 * Usage: php tests/bin/generate-pot.php
 *
 * Extracts translatable strings from src/ and templates/: the WordPress
 * i18n calls (__, _e, esc_html_e, esc_attr_e, esc_html__, esc_attr__ with
 * the 'consent-gate' domain) and the injected-translator calls ($t( '…' ))
 * used by the WordPress-free layers (see Plugin.php for the bridge).
 *
 * @package ConsentGate
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
// The editor script's __( '…', 'consent-gate' ) calls are extracted with the
// same pattern; wp_set_script_translations() serves them from the JSON files
// translators build from this POT.
$files[] = $root . '/assets/js/editor.js';
sort( $files );

$strings = array(); // msgid => list of "file:line" references.

foreach ( $files as $path ) {
	$source   = (string) file_get_contents( $path );
	$relative = substr( $path, strlen( $root ) + 1 );

	$patterns = array(
		// __( 'Text', 'consent-gate' ) and escaping variants.
		'/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'
			. '(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")\s*,\s*\'consent-gate\'\s*\)/s',
		// $t( 'Text' ) — the injected translator in WordPress-free code.
		'/\$t\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")\s*\)/s',
	);

	foreach ( $patterns as $pattern ) {
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
		}
	}
}

ksort( $strings );

preg_match( '/^ \* Version:\s+(\S+)/m', (string) file_get_contents( $root . '/consent-gate.php' ), $version_match );
$version = isset( $version_match[1] ) ? $version_match[1] : '0.0.0';

$pot = <<<'HEADER'
# Consent Gate.
# This file is distributed under the same license as the Consent Gate plugin.
msgid ""
msgstr ""
"Project-Id-Version: Consent Gate {{VERSION}}\n"
"Report-Msgid-Bugs-To: https://github.com/Calucon/consent-gate/issues\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Domain: consent-gate\n"


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
file_put_contents( $root . '/languages/consent-gate.pot', $pot );

echo count( $strings ) . " strings -> languages/consent-gate.pot\n";
