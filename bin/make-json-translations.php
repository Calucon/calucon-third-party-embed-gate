<?php
/**
 * Build the JS translation files WordPress needs for editor.js.
 *
 * A .mo file only reaches PHP. Strings that assets/js/editor.js passes through
 * wp.i18n's __() are loaded by wp_set_script_translations() from a JSON file
 * instead, so without this step the block-editor controls stay English while
 * the rest of the admin is translated.
 *
 * WordPress looks for `{domain}-{locale}-{handle}.json` first and falls back
 * to `{domain}-{locale}-{md5 of the script path relative to the plugin}.json`.
 * Both were verified to resolve on a real WordPress; the handle name is the
 * one shipped, because it is checked first and can be read. (`wp i18n
 * make-json` writes the md5 form — that is what a language pack from
 * translate.wordpress.org is named, and it lives in WP_LANG_DIR, so the two
 * never collide.) The WP integration test asserts a translated string inside
 * the editor, so a rename that breaks the lookup fails the suite.
 *
 * Usage: php bin/make-json-translations.php
 *
 * @package CaluconEmbedGate
 */

$root   = dirname( __DIR__ );
$domain = 'calucon-third-party-embed-gate';
$script = 'assets/js/editor.js';
$handle = 'calucon-embed-gate-editor';

$source = (string) file_get_contents( $root . '/' . $script );
preg_match_all( "/__\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'" . preg_quote( $domain, '/' ) . "'\s*\)/", $source, $m );
$wanted = array_values( array_unique( array_map( static function ( string $s ): string {
	return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $s );
}, $m[1] ) ) );

if ( array() === $wanted ) {
	fwrite( STDERR, "no __() calls found in $script\n" );
	exit( 1 );
}

foreach ( glob( $root . '/languages/' . $domain . '-*.po' ) as $po ) {
	$locale = (string) preg_replace( '/^.*-([a-z]{2}_[A-Z]{2}(?:_formal)?)\.po$/', '$1', $po );
	$body   = (string) file_get_contents( $po );

	// A real (multi-line) PO reader: gettext wraps at 78 columns, and so do
	// Poedit and a GlotPress export — whichever tool touched the file last.
	$map    = array();
	$field  = null;
	$buffer = array( 'msgid' => '', 'msgstr' => '' );
	$unescape = static function ( string $v ): string {
		return str_replace( array( '\\n', '\\t', '\\"', '\\\\' ), array( "\n", "\t", '"', '\\' ), $v );
	};
	foreach ( explode( "\n", $body ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			if ( '' !== $buffer['msgid'] ) {
				$map[ $buffer['msgid'] ] = $buffer['msgstr'];
			}
			$buffer = array( 'msgid' => '', 'msgstr' => '' );
			$field  = null;
			continue;
		}
		if ( 0 === strpos( $line, 'msgid ' ) ) {
			if ( '' !== $buffer['msgid'] ) {
				$map[ $buffer['msgid'] ] = $buffer['msgstr'];
			}
			$buffer = array( 'msgid' => '', 'msgstr' => '' );
			$field  = 'msgid';
			$line   = substr( $line, 6 );
		} elseif ( 0 === strpos( $line, 'msgstr ' ) ) {
			$field = 'msgstr';
			$line  = substr( $line, 7 );
		} elseif ( 0 !== strpos( $line, '"' ) || null === $field ) {
			continue;
		}
		if ( preg_match( '/^"((?:[^"\\\\]|\\\\.)*)"$/', $line, $m ) ) {
			$buffer[ $field ] .= $unescape( $m[1] );
		}
	}
	if ( '' !== $buffer['msgid'] ) {
		$map[ $buffer['msgid'] ] = $buffer['msgstr'];
	}

	$messages = array();
	$missing  = array();
	foreach ( $wanted as $string ) {
		if ( isset( $map[ $string ] ) && '' !== $map[ $string ] ) {
			$messages[ $string ] = array( $map[ $string ] );
		} else {
			$missing[] = $string;
		}
	}
	if ( array() !== $missing ) {
		fwrite( STDERR, "$locale: untranslated in the PO: " . implode( ' | ', $missing ) . "\n" );
		exit( 1 );
	}

	$json = array(
		'translation-revision-date' => gmdate( 'Y-m-d H:i:sO', filemtime( $po ) ),
		'generator'                 => 'bin/make-json-translations.php',
		'domain'                    => 'messages',
		'locale_data'               => array(
			'messages' => array(
				'' => array(
					'domain'       => 'messages',
					'lang'         => $locale,
					'plural-forms' => 'nplurals=2; plural=(n != 1);',
				),
			) + $messages,
		),
	);

	$path = $root . '/languages/' . $domain . '-' . $locale . '-' . $handle . '.json';
	file_put_contents( $path, (string) wp_json_encode_compat( $json ) . "\n" );
	echo basename( $path ), ' — ', count( $messages ), " strings\n";
}

/**
 * json_encode with the flags WordPress uses, without booting WordPress.
 *
 * @param mixed $data Data.
 * @return string
 */
function wp_json_encode_compat( $data ): string {
	return (string) json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}
