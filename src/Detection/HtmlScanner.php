<?php
/**
 * Attribute-tolerant HTML tag reader.
 *
 * WordPress-free by design (see PLAN.md §2.2): takes strings, returns arrays.
 * Never use DOMDocument here — this scanner touches only the spans it matches
 * and leaves every other byte of the input alone (PLAN.md §3.1).
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	private const RAW_CONTAINERS = array( 'script', 'style', 'textarea', 'title', 'template', 'iframe' );

	/**
	 * Parsed containers excluded only when properly closed (§9.4): browsers
	 * keep parsing markup inside an unclosed <pre>/<code>, so an iframe there
	 * still fires its request. Fail closed — an unterminated opener excludes
	 * nothing and the embed stays gateable.
	 */
	private const PARSED_CONTAINERS = array( 'pre', 'code' );

	/** @var string|null The input the memoised tokenisation belongs to. */
	private ?string $memo_html = null;

	/** @var array{tags:array,excluded:array}|null Tokenisation of $memo_html. */
	private ?array $memo = null;

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
		$matches = array();
		$tokens  = $this->tokenize( $html );

		foreach ( $tokens['tags'] as $tag ) {
			if ( $tag['name'] !== $tag_name ) {
				continue;
			}

			$end = $tag['after'];
			if ( ! $tag['self_closing'] ) {
				// Include the closing tag (and any fallback content between)
				// in the span. A missing closing tag leaves the span at the
				// start tag, which is how browsers recover too.
				$close = $this->find_close_tag( $html, $tag_name, $tag['after'] );
				if ( null !== $close ) {
					$end = $close[1];
				}
			}

			$matches[] = array(
				'start'        => $tag['start'],
				'end'          => $end,
				'attributes'   => $tag['attributes'],
				'self_closing' => $tag['self_closing'],
			);
		}

		return $matches;
	}

	/**
	 * Offset just past the '>' of the start tag that begins at $start, or
	 * null when it never terminates. find_tags() reports an element's END
	 * (up to its closing tag); a rule that replaces an element's contents
	 * needs where its opening tag stops, and this is that, with the same
	 * tolerance for stripped quotes and newlines inside the tag.
	 *
	 * @param string $html  HTML.
	 * @param int    $start Offset of the '<' of the start tag.
	 * @return int|null
	 */
	public function start_tag_end( string $html, int $start ): ?int {
		if ( ! preg_match( '/^<([A-Za-z][A-Za-z0-9-]*)/', substr( $html, $start, 64 ), $m ) ) {
			return null;
		}
		$parsed = $this->parse_start_tag( $html, $start + 1 + strlen( $m[1] ) );
		return null === $parsed ? null : (int) $parsed['after'];
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
	 * One sequential pass over the fragment, the way a browser tokenises:
	 * every start tag with its attributes, and the byte ranges nothing may
	 * be matched inside (comments, raw-text containers, closed parsed
	 * containers).
	 *
	 * Sequential and tag-aware for two reasons that are both silent
	 * failures otherwise:
	 *
	 * - Running comment and container matching as independent global
	 *   regexes cross-contaminates: a literal '<!--' inside a script body
	 *   (JSON-LD, legacy script-hiding) opened a bogus comment range to
	 *   end-of-input and every embed after it went ungated.
	 * - Scanning raw bytes is attribute-blind: `<div data-x="<!--">` above
	 *   an iframe opened a comment the browser never sees, and the iframe —
	 *   and in whole-page mode the rest of the document — went ungated. The
	 *   same blindness let `<img alt="<iframe src=…">` produce a placeholder
	 *   spliced INTO the alt attribute. Both are within reach of any author
	 *   WordPress lets write attributes (kses keeps '<' inside a value). So
	 *   a tag is only ever opened where a browser would open one: never
	 *   inside another start tag's attribute values, never inside a comment
	 *   or raw-text body.
	 *
	 * Unterminated shapes follow the browser too: an unclosed comment,
	 * raw-text container or start tag swallows the rest of the document, so
	 * nothing after it can make a request and excluding it all is exact.
	 * An unclosed <pre>/<code> excludes nothing — browsers keep parsing
	 * markup inside those, so an iframe there still fires (fail closed).
	 *
	 * Memoised for the last input: the rules each ask for a different tag
	 * name on the same fragment.
	 *
	 * @param string $html HTML.
	 * @return array{tags:array<int,array{start:int,name:string,after:int,attributes:array,self_closing:bool}>,excluded:array<int,array{0:int,1:int}>}
	 */
	private function tokenize( string $html ): array {
		if ( null !== $this->memo && $this->memo_html === $html ) {
			return $this->memo;
		}

		$tags     = array();
		$excluded = array();
		$len      = strlen( $html );
		$pos      = 0;

		// Once a close-tag search for a tag name has failed, every later
		// search for the same name scans a strict suffix of that failed scan
		// and must fail too. Without this memo, N unterminated <code> openers
		// cost N full-tail scans — quadratic on adversarial input.
		$no_close = array();

		while ( $pos < $len ) {
			$lt = strpos( $html, '<', $pos );
			if ( false === $lt ) {
				break;
			}
			$next = $lt + 1 < $len ? $html[ $lt + 1 ] : '';

			if ( '!' === $next || '?' === $next ) {
				if ( '<!--' === substr( $html, $lt, 4 ) ) {
					$close = strpos( $html, '-->', $lt + 4 );
					if ( false === $close ) {
						// Browsers comment out the rest of the document.
						$excluded[] = array( $lt, $len );
						break;
					}
					$excluded[] = array( $lt, $close + 3 );
					$pos        = $close + 3;
					continue;
				}
				// A doctype, CDATA section or processing instruction: not a
				// tag, ends at the next '>'.
				$gt  = strpos( $html, '>', $lt );
				$pos = false === $gt ? $len : $gt + 1;
				continue;
			}

			if ( '/' === $next ) {
				// An end tag on its own (its opener was somewhere earlier, or
				// never): skip it whole.
				$gt  = strpos( $html, '>', $lt );
				$pos = false === $gt ? $len : $gt + 1;
				continue;
			}

			// The lookahead requires a real tag boundary so '<iframexyz' does
			// not match; '&lt;iframe' cannot match at all.
			if ( ! preg_match( '/\G<([A-Za-z][A-Za-z0-9-]*)(?=[\s\/>])/', $html, $m, 0, $lt ) ) {
				$pos = $lt + 1; // A lone '<' in text.
				continue;
			}

			$name   = strtolower( $m[1] );
			$parsed = $this->parse_start_tag( $html, $lt + strlen( $m[0] ) );
			if ( null === $parsed ) {
				// An unterminated start tag: the rest of the document is
				// attribute soup to a browser; nothing in it renders.
				$excluded[] = array( $lt, $len );
				break;
			}

			$tags[] = array(
				'start'        => $lt,
				'name'         => $name,
				'after'        => $parsed['after'],
				'attributes'   => $parsed['attributes'],
				'self_closing' => $parsed['self_closing'],
			);
			$pos    = $parsed['after'];

			$raw              = in_array( $name, self::RAW_CONTAINERS, true );
			$parsed_container = ! $raw && in_array( $name, self::PARSED_CONTAINERS, true );
			if ( ! $raw && ! $parsed_container ) {
				continue;
			}
			if ( $parsed['self_closing'] && ! $raw ) {
				continue; // `<pre/>` opens nothing worth excluding.
			}

			$close = isset( $no_close[ $name ] ) ? null : $this->find_close_tag( $html, $name, $pos );
			if ( null !== $close ) {
				$excluded[] = array( $lt, $close[1] );
				$pos        = $close[1];
				continue;
			}
			$no_close[ $name ] = true;

			if ( $parsed_container ) {
				// Unterminated <pre>/<code>: browsers still render the markup
				// inside, so an iframe there still fires. Exclude nothing.
				continue;
			}

			// Unterminated raw-text container: the rest of the document is
			// its content in browsers; no request can originate there.
			$excluded[] = array( $lt, $len );
			break;
		}

		$this->memo_html = $html;
		$this->memo      = array(
			'tags'     => $tags,
			'excluded' => $excluded,
		);
		return $this->memo;
	}

	/**
	 * The first end tag for $name at or after $from, with the HTML5 boundary
	 * rule: `</iframe foo>` closes an iframe just as `</iframe>` does, and
	 * `</iframes>` closes nothing.
	 *
	 * @param string $html HTML.
	 * @param string $name Lowercase tag name.
	 * @param int    $from Offset to search from.
	 * @return array{0:int,1:int}|null [start, end) of the end tag.
	 */
	private function find_close_tag( string $html, string $name, int $from ): ?array {
		if ( ! preg_match( '/<\/' . preg_quote( $name, '/' ) . '(?=[\s\/>])[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE, $from ) ) {
			return null;
		}
		return array( (int) $m[0][1], (int) $m[0][1] + strlen( $m[0][0] ) );
	}
}
