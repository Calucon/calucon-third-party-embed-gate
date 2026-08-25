<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The shipped translations (PLAN.md §11). A translation that silently falls
 * behind the source is worse than none: the panel then mixes two languages,
 * and the half a visitor reads is the half that describes what loading an
 * embed does. These tests fail the moment a new string is added without a
 * German counterpart.
 */
final class TranslationTest extends TestCase {

	/** Hand-written; every other German locale is derived from these. */
	private const SOURCE_LOCALES = array( 'de_DE', 'de_DE_formal' );

	private const LOCALES = array( 'de_DE', 'de_DE_formal', 'de_AT', 'de_CH', 'de_CH_informal' );

	/**
	 * msgid => msgstr for one PO file.
	 *
	 * Written as a real (multi-line) PO reader rather than a line regex:
	 * gettext wraps long strings at 78 columns, and so do Poedit and a
	 * GlotPress export — the files this project will be edited with.
	 *
	 * @param string $path PO path.
	 * @return array
	 */
	private function entries( string $path ): array {
		$out     = array();
		$key     = null;
		$field   = null;
		$buffer  = array( 'msgid' => '', 'msgstr' => '' );
		$flush   = static function () use ( &$out, &$buffer, &$key ) {
			if ( null !== $key && '' !== $buffer['msgid'] ) {
				$out[ $buffer['msgid'] ] = $buffer['msgstr'];
			}
			$buffer = array( 'msgid' => '', 'msgstr' => '' );
			$key    = null;
		};

		foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				$flush();
				$field = null;
				continue;
			}
			if ( 0 === strpos( $line, 'msgid ' ) ) {
				$flush();
				$field = 'msgid';
				$key   = true;
				$line  = substr( $line, 6 );
			} elseif ( 0 === strpos( $line, 'msgstr ' ) ) {
				$field = 'msgstr';
				$line  = substr( $line, 7 );
			} elseif ( 0 !== strpos( $line, '"' ) || null === $field ) {
				continue;
			}
			if ( preg_match( '/^"((?:[^"\\\\]|\\\\.)*)"$/', $line, $m ) ) {
				$buffer[ $field ] .= self::unescape( $m[1] );
			}
		}
		$flush();

		unset( $out[''] ); // The header entry.
		return $out;
	}

	/**
	 * @param string $value Escaped PO string.
	 * @return string
	 */
	private static function unescape( string $value ): string {
		return str_replace( array( '\\n', '\\t', '\\"', '\\\\' ), array( "\n", "\t", '"', '\\' ), $value );
	}

	/**
	 * @return array msgid list from the POT.
	 */
	private function pot_msgids(): array {
		return array_keys( $this->entries( dirname( __DIR__, 2 ) . '/languages/calucon-third-party-embed-gate.pot' ) );
	}

	public function test_every_source_string_is_translated(): void {
		$expected = $this->pot_msgids();
		self::assertGreaterThan( 300, count( $expected ), 'the POT looks truncated' );

		foreach ( self::LOCALES as $locale ) {
			$entries = $this->entries( dirname( __DIR__, 2 ) . "/languages/calucon-third-party-embed-gate-$locale.po" );
			$missing = array();
			foreach ( $expected as $msgid ) {
				if ( ! isset( $entries[ $msgid ] ) || '' === trim( $entries[ $msgid ] ) ) {
					$missing[] = $msgid;
				}
			}
			self::assertSame( array(), $missing, "$locale: untranslated strings — run msgmerge and translate them" );
		}
	}

	/**
	 * A dropped or renamed placeholder is a runtime error, not a wording
	 * problem: sprintf() with a missing argument throws in PHP 8.
	 */
	public function test_placeholders_survive_translation(): void {
		foreach ( self::LOCALES as $locale ) {
			foreach ( $this->entries( dirname( __DIR__, 2 ) . "/languages/calucon-third-party-embed-gate-$locale.po" ) as $msgid => $msgstr ) {
				preg_match_all( '/%(?:\d+\$)?[sd]/', $msgid, $source );
				preg_match_all( '/%(?:\d+\$)?[sd]/', $msgstr, $target );
				sort( $source[0] );
				sort( $target[0] );
				self::assertSame( $source[0], $target[0], "$locale: placeholders differ for “{$msgid}”" );
			}
		}
	}

	/**
	 * The block editor reads its strings from JSON, not from the .mo, so an
	 * editor.js string can be translated everywhere else and still show up in
	 * English. Regenerate with bin/make-json-translations.php.
	 */
	public function test_the_block_editor_json_covers_every_editor_string(): void {
		$root   = dirname( __DIR__, 2 );
		$source = (string) file_get_contents( $root . '/assets/js/editor.js' );
		preg_match_all( "/__\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'calucon-third-party-embed-gate'\s*\)/", $source, $m );
		$wanted = array_unique( array_map(
			static function ( string $s ): string {
				return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $s );
			},
			$m[1]
		) );
		self::assertNotEmpty( $wanted );

		foreach ( self::LOCALES as $locale ) {
			$path = $root . "/languages/calucon-third-party-embed-gate-$locale-calucon-embed-gate-editor.json";
			self::assertFileExists( $path );
			$json = json_decode( (string) file_get_contents( $path ), true );
			$data = $json['locale_data']['messages'] ?? array();

			foreach ( $wanted as $string ) {
				self::assertArrayHasKey( $string, $data, "$locale: missing from the editor JSON" );
				self::assertNotSame( '', (string) ( $data[ $string ][0] ?? '' ), "$locale: empty translation for “{$string}”" );
			}
		}
	}

	/**
	 * The compiled .mo is what WordPress actually reads; a stale one ships
	 * yesterday's wording however good the .po is.
	 */
	public function test_the_compiled_mo_matches_the_po(): void {
		foreach ( self::LOCALES as $locale ) {
			$base = dirname( __DIR__, 2 ) . "/languages/calucon-third-party-embed-gate-$locale";
			self::assertFileExists( "$base.mo" );

			$mo    = (string) file_get_contents( "$base.mo" );
			$count = 0;
			foreach ( $this->entries( "$base.po" ) as $msgstr ) {
				if ( false !== strpos( $mo, $msgstr ) ) {
					++$count;
				}
			}
			self::assertGreaterThan( 370, $count, "$locale: the .mo is out of date — run msgfmt over the .po" );
		}
	}
	/**
	 * The derived locales exist because WordPress does not fall back between
	 * German variants: without its own file, a de_AT site sees English.
	 * Regenerate with bin/derive-german-locales.php (bin/update-translations.sh
	 * runs it) — never edit them by hand.
	 */
	public function test_derived_locales_match_the_locale_they_come_from(): void {
		$dir = dirname( __DIR__, 2 ) . '/languages/calucon-third-party-embed-gate-';

		$sources = array(
			'de_AT'          => 'de_DE',
			'de_CH'          => 'de_DE_formal',
			'de_CH_informal' => 'de_DE',
		);

		foreach ( $sources as $locale => $source ) {
			$from = $this->entries( "$dir$source.po" );
			$to   = $this->entries( "$dir$locale.po" );

			self::assertSame( array_keys( $from ), array_keys( $to ), "$locale covers different strings than $source" );

			$swiss = 0 === strpos( $locale, 'de_CH' );
			foreach ( $from as $msgid => $text ) {
				// Switzerland writes ss for ß and quotes with guillemets;
				// everything else is the source translation verbatim.
				$expected = $swiss ? str_replace( array( 'ß', '„', '“' ), array( 'ss', '«', '»' ), $text ) : $text;
				self::assertSame( $expected, $to[ $msgid ], "$locale drifted from $source" );
			}

			if ( $swiss ) {
				$body = (string) file_get_contents( "$dir$locale.po" );
				self::assertStringNotContainsString( 'ß', $body, "$locale must not contain a sharp S" );
				self::assertStringNotContainsString( '„', $body, "$locale quotes with guillemets" );
			}
		}

		// The source locales keep German-Germany orthography.
		self::assertStringContainsString( 'ß', (string) file_get_contents( $dir . 'de_DE.po' ) );
	}

	/**
	 * wp_set_script_translations() must be given the plugin's languages
	 * directory. Without that third argument it looks only in
	 * WP_LANG_DIR/plugins, where a file bundled with the plugin never lives —
	 * so the block editor stays English on every WordPress before 6.7 while
	 * the front end and the settings screen are translated.
	 *
	 * That combination is the reason this is a static check rather than a
	 * behavioural one: recent WordPress finds the JSON anyway through its
	 * textdomain registry, so the integration test passes on a current
	 * install whether the path is there or not. It was only visible when the
	 * suite was pointed at WordPress 6.6, which is inside the range this
	 * plugin claims to support.
	 */
	public function test_script_translations_are_given_the_bundled_path(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/BlockEditor.php' );

		self::assertSame(
			1,
			preg_match(
				'/wp_set_script_translations\(\s*[^;]*CALUCON_EMBED_GATE_DIR\s*\.\s*\x27\/languages\x27\s*\)/',
				$source
			),
			'wp_set_script_translations() needs CALUCON_EMBED_GATE_DIR . \'/languages\' as its third argument, '
				. 'or bundled editor translations are ignored on WordPress below 6.7'
		);
	}
}
