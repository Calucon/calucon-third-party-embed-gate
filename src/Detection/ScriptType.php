<?php
/**
 * Is a <script> element JavaScript at all?
 *
 * WordPress-free by design (PLAN.md §2.2).
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A script element whose type is not a JavaScript MIME type is inert data
 * to a browser — JSON-LD, a template, the plugin's own payload carrier —
 * and never a loader, a companion, a scan finding or something to strip.
 * Re-running such a block as a classic script (what an earlier ScriptRule
 * did to an inert block sitting next to a provider's panel) would execute
 * data the browser never executed.
 */
final class ScriptType {

	/**
	 * The HTML Standard's JavaScript MIME type essence list, plus the
	 * `module` keyword. A missing or empty type is JavaScript.
	 */
	private const JAVASCRIPT = array(
		'application/ecmascript',
		'application/javascript',
		'application/x-ecmascript',
		'application/x-javascript',
		'text/ecmascript',
		'text/javascript',
		'text/javascript1.0',
		'text/javascript1.1',
		'text/javascript1.2',
		'text/javascript1.3',
		'text/javascript1.4',
		'text/javascript1.5',
		'text/jscript',
		'text/livescript',
		'text/x-ecmascript',
		'text/x-javascript',
		'module',
	);

	/**
	 * @param array $attributes Lowercased, decoded attributes of the tag.
	 * @return bool
	 */
	public static function is_javascript( array $attributes ): bool {
		if ( ! isset( $attributes['type'] ) || true === $attributes['type'] ) {
			return true; // Absent, or present without a value.
		}
		$type = strtolower( trim( (string) $attributes['type'] ) );
		if ( '' === $type ) {
			return true;
		}
		// Parameters do not change the essence: "text/javascript; charset=utf-8".
		$semicolon = strpos( $type, ';' );
		if ( false !== $semicolon ) {
			$type = trim( substr( $type, 0, $semicolon ) );
		}
		return in_array( $type, self::JAVASCRIPT, true );
	}
}
