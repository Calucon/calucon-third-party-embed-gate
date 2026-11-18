<?php
/**
 * The two read-only reports the plugin gives about a site, as data.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "What is embedded in recent content, and is it gated?" and "which
 * providers does the gate know, as configured here?" — once, for every way
 * of asking: the WP-CLI commands (`wp calucon-embed-gate scan|providers`)
 * and, since 1.1, the abilities of the same names (Integration\Abilities).
 * The rows and their keys are the shape `--format=json` has had since 0.5
 * and docs/customizing.md promises; both callers print exactly these.
 *
 * Read-only by construction: nothing here writes, and nothing leaves the
 * site — the scan renders the site's own content through the site's own
 * filters and looks at the HTML.
 *
 * WordPress-aware (get_posts).
 */
final class Reports {

	/** Bounds of the scan's post count. */
	public const SCAN_DEFAULT = 50;
	public const SCAN_MAX     = 500;

	/** Row keys, in the order both callers print them. */
	public const SCAN_FIELDS     = array( 'host', 'tag', 'label', 'provider', 'status', 'count', 'first_seen', 'url' );
	public const PROVIDER_FIELDS = array( 'id', 'label', 'enabled', 'strategy', 'hosts', 'load_host', 'controller', 'privacy_url' );

	/**
	 * Every embed in the most recent published posts and pages, grouped by
	 * tag, host and status.
	 *
	 * @param ContentScan $scanner        The scanner, built with the site's rules.
	 * @param callable    $render_ungated fn( string $post_content ): string — the
	 *                                    content through the_content WITHOUT the gate.
	 * @param int         $limit          How many posts to read (clamped to 1–500).
	 * @return array[] Rows with the keys of SCAN_FIELDS.
	 */
	public static function scan( ContentScan $scanner, callable $render_ungated, int $limit = self::SCAN_DEFAULT ): array {
		$limit = max( 1, min( self::SCAN_MAX, $limit ) );
		$posts = get_posts(
			array(
				'post_type'        => array( 'post', 'page' ),
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'suppress_filters' => false,
			)
		);

		$scanned = array();
		foreach ( $posts as $post ) {
			$rendered  = (string) call_user_func( $render_ungated, $post->post_content );
			$scanned[] = array(
				'source' => get_the_title( $post ),
				'rows'   => $scanner->scan( $rendered ),
			);
		}

		$rows = array();
		foreach ( ContentScan::aggregate( $scanned ) as $row ) {
			$ordered = array();
			foreach ( self::SCAN_FIELDS as $field ) {
				$ordered[ $field ] = isset( $row[ $field ] ) ? $row[ $field ] : '';
			}
			$rows[] = $ordered;
		}
		return $rows;
	}

	/**
	 * The providers as the gate resolves them: built-ins, registrations
	 * through the calucon_embed_gate_providers filter, the owner's own, with
	 * the owner's per-provider settings applied.
	 *
	 * @param array[] $descriptors Resolved provider descriptors.
	 * @return array[] Rows with the keys of PROVIDER_FIELDS.
	 */
	public static function providers( array $descriptors ): array {
		$rows = array();
		foreach ( $descriptors as $descriptor ) {
			if ( ! is_array( $descriptor ) || ! isset( $descriptor['id'] ) ) {
				continue;
			}
			$match  = isset( $descriptor['match'] ) ? (array) $descriptor['match'] : array();
			$hosts  = array_merge(
				isset( $match['iframe_host'] ) ? (array) $match['iframe_host'] : array(),
				isset( $match['script_host'] ) ? (array) $match['script_host'] : array()
			);
			$rows[] = array(
				'id'          => (string) $descriptor['id'],
				'label'       => isset( $descriptor['label'] ) ? (string) $descriptor['label'] : '',
				'enabled'     => ! isset( $descriptor['enabled'] ) || false !== $descriptor['enabled'] ? 'yes' : 'no',
				'strategy'    => isset( $descriptor['strategy'] ) ? (string) $descriptor['strategy'] : 'iframe',
				'hosts'       => implode( ',', $hosts ),
				'load_host'   => isset( $descriptor['load_host'] ) ? (string) $descriptor['load_host'] : '',
				'controller'  => isset( $descriptor['controller'] ) ? (string) $descriptor['controller'] : '',
				'privacy_url' => isset( $descriptor['privacy_url'] ) ? (string) $descriptor['privacy_url'] : '',
			);
		}
		return $rows;
	}
}
