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
	 * Raw-text and inert containers (§9.4): their content never renders as
	 * markup. When one is left unterminated, browsers swallow the rest of the
	 * document into it — nothing after it can make a request — so excluding
	 * to end-of-input matches what actually happens in a browser.
	 */
	private const RAW_CONTAINERS = array( 'script', 'style', 'textarea', 'title', 'template' );

	/**
	 * Parsed containers excluded only when properly closed (§9.4): browsers
	 * keep parsing markup inside an unclosed <pre>/<code>, so an iframe there
	 * still fires its request. Fail closed — an unterminated opener excludes
	 * nothing and the embed stays gateable.
	 */
	private const PARSED_CONTAINERS = array( 'pre', 'code' );

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
	 * A single sequential pass in document order, the way a browser tokenises.
	 * Running comment and container matching as two independent global regexes
	 * (the previous implementation) cross-contaminates: a literal '<!--'
	 * inside a script body (JSON-LD, legacy script-hiding) opened a bogus
	 * comment range to end-of-input and every embed after it went ungated —
	 * silently, which is the §3.2 failure mode all over again.
	 *
	 * @param string $html HTML.
	 * @return array<int,array{0:int,1:int}> List of [start, end) ranges.
	 */
	private function excluded_ranges( string $html ): array {
		$ranges = array();
		$len    = strlen( $html );
		$pos    = 0;

		$opener = '/<!--|<(' . implode( '|', array_merge( self::RAW_CONTAINERS, self::PARSED_CONTAINERS ) ) . ')(?=[\s\/>])/i';

		while ( $pos < $len && preg_match( $opener, $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$start = $m[0][1];

			if ( '<!--' === $m[0][0] ) {
				$close = strpos( $html, '-->', $start + 4 );
				if ( false === $close ) {
					// Browsers comment out the rest of the document; nothing
					// in it can load, so excluding it all matches reality.
					$ranges[] = array( $start, $len );
					break;
				}
				$end      = $close + 3;
				$ranges[] = array( $start, $end );
				$pos      = $end;
				continue;
			}

			$tag = strtolower( $m[1][0] );
			if ( preg_match( '/<\/' . $tag . '\s*>/i', $html, $close_tag, PREG_OFFSET_CAPTURE, $start + 1 ) ) {
				$end      = $close_tag[0][1] + strlen( $close_tag[0][0] );
				$ranges[] = array( $start, $end );
				$pos      = $end;
				continue;
			}

			if ( in_array( $tag, self::PARSED_CONTAINERS, true ) ) {
				// Unterminated <pre>/<code>: browsers still render the markup
				// inside, so an iframe there still fires. Exclude nothing.
				$pos = $start + 1;
				continue;
			}

			// Unterminated raw-text/inert container: the rest of the document
			// is its content in browsers; no request can originate there.
			$ranges[] = array( $start, $len );
			break;
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
			// Strictly inside: a candidate at the range start IS the container
			// tag itself, which must stay scannable (ScriptRule reads script
			// tags; their bodies stay off limits).
			if ( $offset > $range[0] && $offset < $range[1] ) {
				return true;
			}
		}
		return false;
	}
}
