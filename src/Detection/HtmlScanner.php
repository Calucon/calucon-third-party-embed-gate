<?php
/**
 * Attribute-tolerant HTML tag reader.
 *
 * WordPress-free by design (see PLAN.md §2.2): takes strings, returns arrays.
 * Never use DOMDocument here — this scanner touches only the spans it matches
 * and leaves every other byte of the input alone (PLAN.md §3.1).
 *
 * @package ConsentGate
 */

namespace ConsentGate\Detection;

/**
 * Finds start tags of a given name and parses their attributes without
 * assuming pretty-printed HTML. Minifiers strip attribute quotes and insert
 * newlines inside tags (PLAN.md §3.2) — every rule here exists to survive that.
 */
final class HtmlScanner {

	/**
	 * Regions the scanner must never match inside: script, style, textarea,
	 * pre (may contain escaped markup examples) and HTML comments.
	 */
	private const EXCLUDED_CONTAINERS = array( 'script', 'style', 'textarea', 'pre' );

	/**
	 * Find every occurrence of a tag, tolerant of minified markup.
	 *
	 * Each match is an array:
	 *  - 'start'        int    Byte offset of the '<'.
	 *  - 'end'          int    Byte offset just past the tag's span. For a tag
	 *                          with a closing tag this includes the closing tag
	 *                          and everything between; for a self-closed or
	 *                          unclosed tag it is the end of the start tag.
	 *  - 'attributes'   array  Lowercased name => decoded value. Boolean
	 *                          attributes (no '=') carry the value true.
	 *  - 'self_closing' bool
	 *
	 * @param string $html    Full HTML fragment.
	 * @param string $tag_name Lowercase tag name, e.g. 'iframe'.
	 * @return array[] Matches in document order.
	 */
	public function find_tags( string $html, string $tag_name ): array {
		$matches  = array();
		$excluded = $this->excluded_ranges( $html );

		// Candidate start tags. The lookahead requires a real tag boundary so
		// '<iframexyz' does not match; '&lt;iframe' cannot match at all.
		if ( ! preg_match_all(
			'/<' . preg_quote( $tag_name, '/' ) . '(?=[\s\/>])/i',
			$html,
			$candidates,
			PREG_OFFSET_CAPTURE
		) ) {
			return $matches;
		}

		foreach ( $candidates[0] as $candidate ) {
			$start = $candidate[1];
			if ( $this->in_excluded_range( $start, $excluded ) ) {
				continue;
			}

			$parsed = $this->parse_start_tag( $html, $start + strlen( $candidate[0] ) );
			if ( null === $parsed ) {
				continue; // Unterminated tag; leave it alone.
			}

			$end = $parsed['after'];
			if ( ! $parsed['self_closing'] ) {
				// Include the closing tag (and any fallback content between)
				// in the span. A missing closing tag leaves the span at the
				// start tag, which is how browsers recover too.
				if ( preg_match(
					'/<\/' . preg_quote( $tag_name, '/' ) . '\s*>/i',
					$html,
					$close,
					PREG_OFFSET_CAPTURE,
					$parsed['after']
				) ) {
					$end = $close[0][1] + strlen( $close[0][0] );
				}
			}

			$matches[] = array(
				'start'        => $start,
				'end'          => $end,
				'attributes'   => $parsed['attributes'],
				'self_closing' => $parsed['self_closing'],
			);
		}

		return $matches;
	}

	/**
	 * Parse attributes from just past the tag name to the closing '>'.
	 *
	 * Tolerates: any whitespace including newlines between name and attributes;
	 * double-quoted, single-quoted and bare values; boolean attributes; any
	 * attribute order; mixed case names (normalised to lowercase). Values are
	 * entity-decoded, so '&amp;' in a query string compares correctly.
	 *
	 * @param string $html HTML.
	 * @param int    $pos  Offset just past the tag name.
	 * @return array{after:int,attributes:array,self_closing:bool}|null Null if
	 *         the tag never terminates.
	 */
	private function parse_start_tag( string $html, int $pos ) {
		$len          = strlen( $html );
		$attributes   = array();
		$self_closing = false;

		while ( $pos < $len ) {
			// Whitespace (newlines included) is legal anywhere between attributes.
			if ( preg_match( '/\G\s+/', $html, $ws, 0, $pos ) ) {
				$pos += strlen( $ws[0] );
				continue;
			}

			$char = $html[ $pos ];

			if ( '>' === $char ) {
				return array(
					'after'        => $pos + 1,
					'attributes'   => $attributes,
					'self_closing' => $self_closing,
				);
			}

			if ( '/' === $char ) {
				// A solidus directly before '>' marks self-closing; anywhere
				// else it is stray and skipped, as browsers do.
				$self_closing = ( $pos + 1 < $len && '>' === $html[ $pos + 1 ] );
				++$pos;
				continue;
			}

			// Attribute: name, optionally '=' and a quoted or bare value.
			// The value group is optional — that is what makes boolean
			// attributes (allowfullscreen, defer) work.
			if ( preg_match(
				'/\G([^\s\/>=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]*)))?/',
				$html,
				$attr,
				0,
				$pos
			) && '' !== $attr[0] ) {
				$name = strtolower( $attr[1] );
				if ( ! array_key_exists( $name, $attributes ) ) {
					if ( isset( $attr[2] ) && ( '' !== $attr[2] || false !== strpos( $attr[0], '=' ) ) ) {
						$value = $attr[2];
						if ( '' === $value && isset( $attr[3] ) && '' !== $attr[3] ) {
							$value = $attr[3];
						}
						if ( '' === $value && isset( $attr[4] ) && '' !== $attr[4] ) {
							$value = $attr[4];
						}
						$attributes[ $name ] = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					} else {
						$attributes[ $name ] = true;
					}
				}
				$pos += strlen( $attr[0] );
				continue;
			}

			++$pos; // Unparseable byte inside the tag; skip it rather than loop forever.
		}

		return null;
	}

	/**
	 * Byte ranges the scanner must not match inside.
	 *
	 * @param string $html HTML.
	 * @return array<int,array{0:int,1:int}> List of [start, end) ranges.
	 */
	private function excluded_ranges( string $html ): array {
		$ranges = array();

		// Comments; an unterminated comment swallows the rest of the document.
		if ( preg_match_all( '/<!--.*?(?:-->|$)/s', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m[0] as $hit ) {
				$ranges[] = array( $hit[1], $hit[1] + strlen( $hit[0] ) );
			}
		}

		$tags = implode( '|', self::EXCLUDED_CONTAINERS );
		if ( preg_match_all(
			'/<(' . $tags . ')\b.*?(?:<\/\1\s*>|$)/is',
			$html,
			$m,
			PREG_OFFSET_CAPTURE
		) ) {
			foreach ( $m[0] as $hit ) {
				$ranges[] = array( $hit[1], $hit[1] + strlen( $hit[0] ) );
			}
		}

		return $ranges;
	}

	/**
	 * @param int   $offset Byte offset.
	 * @param array $ranges Ranges from excluded_ranges().
	 * @return bool
	 */
	private function in_excluded_range( int $offset, array $ranges ): bool {
		foreach ( $ranges as $range ) {
			if ( $offset >= $range[0] && $offset < $range[1] ) {
				return true;
			}
		}
		return false;
	}
}
