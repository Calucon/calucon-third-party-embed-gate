<?php
/**
 * Minimal PO reader for the test suite.
 *
 * Shared by the translation tests, which all need the same thing: msgid =>
 * msgstr, unwrapped, with the header entry and untranslated strings dropped.
 * Gettext tooling is not a dev dependency and should not become one for this.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Support;

final class PoReader {

	/**
	 * @param string $path PO file.
	 * @return array<string,string> msgid => msgstr, translated entries only.
	 */
	public static function translations( string $path ): array {
		$out    = array();
		$msgid  = '';
		$msgstr = '';
		$target = null;

		$store = static function () use ( &$out, &$msgid, &$msgstr ): void {
			if ( '' !== $msgid && '' !== trim( $msgstr ) ) {
				$out[ $msgid ] = $msgstr;
			}
		};

		foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $line ) {
			if ( 0 === strpos( $line, 'msgid ' ) ) {
				$store();
				$msgid  = self::unquote( substr( $line, 6 ) );
				$msgstr = '';
				$target = 'msgid';
			} elseif ( 0 === strpos( $line, 'msgstr ' ) ) {
				$msgstr = self::unquote( substr( $line, 7 ) );
				$target = 'msgstr';
			} elseif ( '"' === substr( $line, 0, 1 ) ) {
				if ( 'msgstr' === $target ) {
					$msgstr .= self::unquote( $line );
				} elseif ( 'msgid' === $target ) {
					$msgid .= self::unquote( $line );
				}
			}
		}
		$store();

		return $out;
	}

	/**
	 * @param string $quoted A quoted PO fragment.
	 * @return string
	 */
	private static function unquote( string $quoted ): string {
		$quoted = trim( $quoted );
		if ( '"' !== substr( $quoted, 0, 1 ) ) {
			return '';
		}
		return stripcslashes( substr( $quoted, 1, -1 ) );
	}
}
