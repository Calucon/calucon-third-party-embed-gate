<?php
/**
 * Decides whether a URL points at the site itself or at a third party.
 *
 * WordPress-free by design (PLAN.md §2.2): the own-host list is injected by
 * the integration layer, which is where home_url()/site_url() live.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classifies embed URLs. The failure mode must always be "gated something
 * harmless", never "let a new tracker through" (PLAN.md §1, invariant 6) —
 * but URLs that cannot trigger a network request at all are never gated,
 * because a placeholder whose fallback link points nowhere is worse than
 * the original (PLAN.md §9.5).
 */
final class HostMatcher {

	/** URL cannot cause a third-party request; pass through, never rebuild. */
	public const SKIP = 'skip';

	/** Same-origin (or declared own host); pass through. */
	public const OWN = 'own';

	/** Cross-origin http(s); gate it. */
	public const FOREIGN = 'foreign';

	/** @var string[] Normalised exact own hosts. */
	private array $own_hosts = array();

	/** @var string[] Normalised wildcard suffixes, e.g. '.example.com'. */
	private array $wildcards = array();

	/** @var bool Treat www.example.com and example.com as the same site. */
	private bool $www_equivalence;

	/** @var callable|null Extra veto/approve hook: fn( bool $own, string $host ): bool */
	private $is_own_filter;

	/**
	 * @param string[]      $own_hosts       Hosts that count as "ours". Entries
	 *                                       starting with '*.' match any subdomain.
	 * @param bool          $www_equivalence On by default (PLAN.md §3.4).
	 * @param callable|null $is_own_filter   Bridge for the consent_gate_is_own_host filter.
	 */
	public function __construct( array $own_hosts, bool $www_equivalence = true, ?callable $is_own_filter = null ) {
		$this->www_equivalence = $www_equivalence;
		$this->is_own_filter   = $is_own_filter;

		foreach ( $own_hosts as $host ) {
			$host = trim( (string) $host );
			if ( '' === $host ) {
				continue;
			}
			if ( 0 === strpos( $host, '*.' ) ) {
				$this->wildcards[] = $this->normalize_host( substr( $host, 1 ) ); // Keep the leading dot.
			} else {
				$this->own_hosts[] = $this->normalize_host( $host );
			}
		}
	}

	/**
	 * Classify a URL as SKIP, OWN or FOREIGN.
	 *
	 * @param string $url Raw (already entity-decoded) URL from the markup.
	 * @return string One of the class constants.
	 */
	public function classify( string $url ): string {
		$url = $this->preprocess( $url );
		if ( '' === $url ) {
			return self::SKIP;
		}

		// Schemes that never produce a third-party request: never gated,
		// never rebuilt (PLAN.md §3.4).
		if ( preg_match( '/^(data|blob|about|javascript):/i', $url ) ) {
			return self::SKIP;
		}

		if ( preg_match( '#^https?:#i', $url ) ) {
			// Collapse the scheme and any run of (already slash-normalised)
			// authority slashes into 'scheme://'. Browsers ignore extra,
			// missing or backslash authority slashes for special schemes, so
			// 'https:/\/evil.com' and 'https:evil.com' both name host evil.com
			// — parse_url() alone would miss both and let them through (§3.4).
			$url  = preg_replace( '#^(https?:)/*#i', '$1//', $url );
			$host = $this->extract_host( $url );
		} elseif ( preg_match( '#^/{2,}#', $url ) ) {
			// Protocol-relative: resolves against the page scheme, host decides.
			// Two-or-more leading slashes — including the '/\' open-redirect
			// shape, now '//' after slash normalisation — reach here.
			$host = $this->extract_host( 'https:' . preg_replace( '#^/+#', '//', $url ) );
		} elseif ( 0 === strpos( $url, '/' ) ) {
			// Single leading slash: a same-origin absolute path, never gated.
			return self::OWN;
		} elseif ( preg_match( '/^[a-z][a-z0-9+.-]*:/i', $url ) ) {
			// Unknown scheme (mailto:, tel:, …): an iframe will not fetch it.
			return self::SKIP;
		} else {
			// Relative URL: same-origin by definition, never gated.
			return self::OWN;
		}

		if ( null === $host || '' === $host ) {
			// Unparseable http(s) URL: the browser will not load it either,
			// and a placeholder would have no working fallback link.
			return self::SKIP;
		}

		return $this->is_own_host( $host ) ? self::OWN : self::FOREIGN;
	}

	/**
	 * Normalised host of an absolute or protocol-relative URL.
	 *
	 * @param string $url URL.
	 * @return string|null Null when the URL carries no host.
	 */
	public function host_of( string $url ) {
		$url = $this->preprocess( $url );
		if ( '' === $url ) {
			return null;
		}
		if ( preg_match( '#^https?:#i', $url ) ) {
			$url = preg_replace( '#^(https?:)/*#i', '$1//', $url );
		} elseif ( preg_match( '#^/{2,}#', $url ) ) {
			$url = 'https:' . preg_replace( '#^/+#', '//', $url );
		}
		$host = $this->extract_host( $url );
		return null === $host ? null : $this->normalize_host( $host );
	}

	/**
	 * Browser-style URL preprocessing applied before parse_url(), so this
	 * class and the browser agree on the authority (invariant 6). Browsers
	 * strip ASCII tab/newline characters anywhere in a URL, and for the
	 * special schemes this plugin gates they treat a backslash as a forward
	 * slash. Without this, 'https://evil.com\@own.example/' parses to host
	 * 'own.example' in PHP but connects to 'evil.com' in every browser — a
	 * third party slipping past the gate.
	 *
	 * @param string $url Raw URL from the markup.
	 * @return string
	 */
	private function preprocess( string $url ): string {
		$url = str_replace( array( "\t", "\n", "\r" ), '', $url );
		$url = str_replace( '\\', '/', $url );
		return trim( $url );
	}

	/**
	 * Is this (already extracted) host one of ours?
	 *
	 * @param string $host Host name, possibly mixed case / IDN.
	 * @return bool
	 */
	public function is_own_host( string $host ): bool {
		$host = $this->normalize_host( $host );
		$own  = $this->matches_own( $host );

		if ( null !== $this->is_own_filter ) {
			$own = (bool) call_user_func( $this->is_own_filter, $own, $host );
		}

		return $own;
	}

	/**
	 * @param string $host Normalised host.
	 * @return bool
	 */
	private function matches_own( string $host ): bool {
		$variants = array( $host );
		if ( $this->www_equivalence ) {
			$variants[] = ( 0 === strpos( $host, 'www.' ) ) ? substr( $host, 4 ) : 'www.' . $host;
		}

		foreach ( $variants as $variant ) {
			if ( in_array( $variant, $this->own_hosts, true ) ) {
				return true;
			}
			foreach ( $this->wildcards as $suffix ) {
				// '*.example.com' matches sub.example.com and example.com itself.
				if ( substr( $variant, -strlen( $suffix ) ) === $suffix || substr( $suffix, 1 ) === $variant ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Does a host match a configured host list ('*.' wildcards allowed)?
	 * Shared by the always-gate setting, which must use the same matching
	 * rules as the own-host lists it overrides.
	 *
	 * @param string   $host Host name (any case).
	 * @param string[] $host_list Configured entries.
	 * @return bool
	 */
	public static function host_matches_list( string $host, array $host_list ): bool {
		$host = strtolower( rtrim( trim( $host ), '.' ) );
		foreach ( $host_list as $entry ) {
			if ( ! is_string( $entry ) || '' === $entry ) {
				continue;
			}
			$entry = strtolower( trim( $entry ) );
			if ( 0 === strpos( $entry, '*.' ) ) {
				$suffix = substr( $entry, 1 ); // Keep the leading dot.
				if ( substr( $host, -strlen( $suffix ) ) === $suffix || substr( $suffix, 1 ) === $host ) {
					return true;
				}
			} elseif ( $host === $entry ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Lowercase, strip trailing dot, and punycode IDNs so 'münchen.de'
	 * and 'xn--mnchen-3ya.de' compare equal (PLAN.md §3.4).
	 *
	 * @param string $host Raw host.
	 * @return string
	 */
	private function normalize_host( string $host ): string {
		$host = strtolower( rtrim( trim( $host ), '.' ) );

		if ( '' !== $host && function_exists( 'idn_to_ascii' ) && preg_match( '/[^\x00-\x7f]/', $host ) ) {
			$ascii = idn_to_ascii( $host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46 );
			if ( false !== $ascii && null !== $ascii ) {
				$host = $ascii;
			}
		}

		return $host;
	}

	/**
	 * @param string $url Absolute URL.
	 * @return string|null Host, or null when unparseable.
	 */
	private function extract_host( string $url ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress-free layer (PLAN.md §2.2); wp_parse_url() is unavailable in the no-WordPress fixture suite, and preprocess() already normalised the authority.
		$host = parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? $host : null;
	}
}
