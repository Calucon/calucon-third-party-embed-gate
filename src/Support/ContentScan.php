<?php
/**
 * Read-only content scan for the Status screen (PLAN.md §7.1): which
 * third-party hosts appear in content, and whether each is currently gated.
 * No outbound requests — this inspects markup, it never fetches anything.
 *
 * WordPress-free by design (PLAN.md §2.2); the admin screen feeds it
 * rendered post content.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Support;

use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use ConsentGate\Providers\Registry;

/**
 * Reports what the gate WOULD do, row by row — including what it would
 * NOT gate and why, which is the part a site owner cannot see from the
 * front end.
 */
final class ContentScan {

	public const GATED             = 'gated';
	public const STRIPPED          = 'stripped';
	public const OWN_HOST          = 'own-host';
	public const NO_USABLE_URL     = 'no-usable-url';
	public const RULE_DISABLED     = 'rule-disabled';
	public const PROVIDER_DISABLED = 'provider-disabled';

	/** @var HtmlScanner */
	private HtmlScanner $scanner;

	/** @var HostMatcher */
	private HostMatcher $hosts;

	/** @var Registry */
	private Registry $providers;

	/** @var array Detection flags: iframes, scripts, images. */
	private array $flags;

	public function __construct( HtmlScanner $scanner, HostMatcher $hosts, Registry $providers, array $flags ) {
		$this->scanner   = $scanner;
		$this->hosts     = $hosts;
		$this->providers = $providers;
		$this->flags     = $flags;
	}

	/**
	 * @param string $html Rendered content HTML (before this plugin gates it).
	 * @return array[] Rows: tag, url, host, status, label.
	 */
	public function scan( string $html ): array {
		$rows = array();

		foreach ( $this->scanner->find_tags( $html, 'iframe' ) as $tag_match ) {
			$rows[] = $this->row( 'iframe', $this->url_of( $tag_match['attributes'], array( 'src', 'data-src', 'data-lazy-src', 'data-original' ) ), 'iframes' );
		}
		foreach ( $this->scanner->find_tags( $html, 'embed' ) as $tag_match ) {
			$rows[] = $this->row( 'embed', $this->url_of( $tag_match['attributes'], array( 'src' ) ), 'iframes' );
		}
		foreach ( $this->scanner->find_tags( $html, 'object' ) as $tag_match ) {
			$rows[] = $this->row( 'object', $this->url_of( $tag_match['attributes'], array( 'data' ) ), 'iframes' );
		}
		foreach ( $this->scanner->find_tags( $html, 'script' ) as $tag_match ) {
			$rows[] = $this->row( 'script', $this->url_of( $tag_match['attributes'], array( 'src' ) ), 'scripts', true );
		}
		foreach ( $this->scanner->find_tags( $html, 'img' ) as $tag_match ) {
			$rows[] = $this->row( 'img', $this->url_of( $tag_match['attributes'], array( 'src', 'data-src', 'data-lazy-src' ) ), 'images' );
		}

		return array_values( array_filter( $rows ) );
	}

	/**
	 * Aggregate per-source scan rows into one row per (tag, host, status)
	 * with a count and the first source it was seen in. Pure — shared by the
	 * Status screen and the WP-CLI `scan` command so both report identically.
	 *
	 * @param array[] $scanned Entries of shape:
	 *                         array( 'source' => string, 'rows' => array[] )
	 *                         where rows are what scan() returns.
	 * @return array[] Rows: tag, host, label, status, count, first_seen, url
	 *                 (the first URL seen for the group).
	 */
	public static function aggregate( array $scanned ): array {
		$groups = array();

		foreach ( $scanned as $entry ) {
			$source = isset( $entry['source'] ) ? (string) $entry['source'] : '';
			$rows   = isset( $entry['rows'] ) && is_array( $entry['rows'] ) ? $entry['rows'] : array();
			foreach ( $rows as $row ) {
				$key = $row['tag'] . '|' . $row['host'] . '|' . $row['status'];
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'tag'        => $row['tag'],
						'host'       => $row['host'],
						'label'      => $row['label'],
						'status'     => $row['status'],
						'count'      => 0,
						'first_seen' => $source,
						'url'        => $row['url'],
					);
				}
				++$groups[ $key ]['count'];
			}
		}

		return array_values( $groups );
	}

	/**
	 * @param array    $attributes Tag attributes.
	 * @param string[] $names      Attribute names that may carry the URL.
	 * @return string
	 */
	private function url_of( array $attributes, array $names ): string {
		foreach ( $names as $name ) {
			if ( isset( $attributes[ $name ] ) && is_string( $attributes[ $name ] ) && '' !== trim( $attributes[ $name ] ) ) {
				return trim( $attributes[ $name ] );
			}
		}
		return '';
	}

	/**
	 * @param string $tag       Tag name.
	 * @param string $url       Its URL ('' when none).
	 * @param string $rule_flag Which detection flag governs it.
	 * @param bool   $is_script Resolve against script hosts instead.
	 * @return array|null Null for rows not worth reporting (inline scripts).
	 */
	private function row( string $tag, string $url, string $rule_flag, bool $is_script = false ) {
		if ( '' === $url ) {
			return 'script' === $tag ? null : array(
				'tag'    => $tag,
				'url'    => '',
				'host'   => '',
				'status' => self::NO_USABLE_URL,
				'label'  => '',
			);
		}

		$class = $this->hosts->classify( $url );
		if ( HostMatcher::FOREIGN !== $class ) {
			// Own-host scripts/images are the owner's own assets: noise, not
			// a finding. Own-host iframes are worth one look, so keep those.
			if ( 'iframe' !== $tag ) {
				return null;
			}
			return array(
				'tag'    => $tag,
				'url'    => $url,
				'host'   => (string) $this->hosts->host_of( $url ),
				'status' => HostMatcher::OWN === $class ? self::OWN_HOST : self::NO_USABLE_URL,
				'label'  => '',
			);
		}

		$host = $this->hosts->host_of( $url );
		if ( null === $host ) {
			return null;
		}

		$provider = $is_script
			? $this->providers->resolve_for_script_url( $url, $host )
			: $this->providers->resolve_for_url( $url, $host );

		$status = self::GATED;
		if ( empty( $this->flags[ $rule_flag ] ) ) {
			$status = self::RULE_DISABLED;
		} elseif ( false === $provider['enabled'] ) {
			$status = self::PROVIDER_DISABLED;
		}

		return array(
			'tag'    => $tag,
			'url'    => $url,
			'host'   => $host,
			'status' => $status,
			'label'  => (string) $provider['label'],
		);
	}
}
