<?php
/**
 * Owner-defined providers (settings screen), as descriptors.
 *
 * WordPress-free by design (PLAN.md §2.2): rows from the sanitised option
 * tree in, descriptor arrays out. The rows are deliberately small — a
 * label, the hosts, a kind. Everything else an owner may want per
 * provider (gate on/off, note, button text, privacy-policy link) is the
 * same per-provider override row the built-ins use, so the Providers
 * table treats both alike.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns `custom_providers` option rows into provider descriptors.
 */
final class CustomProviders {

	/** Id prefix that marks a descriptor as owner-defined. */
	public const ID_PREFIX = 'custom-';

	/**
	 * @param array[]       $rows      Sanitised `custom_providers` rows
	 *                                 (id, label, hosts, script_hosts, kind).
	 * @param callable|null $translate Maps an English string to the site
	 *                                 language; identity when null.
	 * @return array[] Descriptors, in row order. Never rewrites the load
	 *                 target: a custom provider loads exactly the URL the
	 *                 embed carries, after the click.
	 */
	public static function descriptors( array $rows, ?callable $translate = null ): array {
		$t = $translate ?? static function ( string $text ): string {
			return $text;
		};

		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['label'] ) ) {
				continue;
			}
			$hosts   = isset( $row['hosts'] ) ? array_values( (array) $row['hosts'] ) : array();
			$scripts = isset( $row['script_hosts'] ) ? array_values( (array) $row['script_hosts'] ) : array();
			if ( array() === $hosts && array() === $scripts ) {
				continue;
			}
			$label = (string) $row['label'];
			$match = array();
			if ( array() !== $hosts ) {
				$match['iframe_host'] = $hosts;
			}
			if ( array() !== $scripts ) {
				$match['script_host'] = $scripts;
			}

			$out[] = Provider::normalize(
				array(
					'id'       => (string) $row['id'],
					'label'    => $label,
					'match'    => $match,
					'kind'     => isset( $row['kind'] ) ? (string) $row['kind'] : '',
					// Same wording the generic fallback uses for an unknown
					// host, with the owner's label in place of the host.
					/* translators: %s: host name of the third-party embed. */
					'note'     => sprintf( $t( 'Loading this content connects your browser to %s, which receives your IP address and which page you are on, and may set cookies.' ), $label ),
					/* translators: %s: host name of the third-party embed. */
					'action'   => sprintf( $t( 'Load content from %s' ), $label ),
					'strategy' => array() !== $hosts ? 'iframe' : 'script',
					'custom'   => true,
				)
			);
		}

		return $out;
	}

	/**
	 * Stable id for a new row: the label slugified under the custom prefix,
	 * unique against the ids already taken.
	 *
	 * @param string   $label Owner-typed label.
	 * @param string[] $taken Ids already in use.
	 * @return string
	 */
	public static function id_for( string $label, array $taken ): string {
		$slug = strtolower( $label );
		if ( function_exists( 'iconv' ) ) {
			$ascii = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- iconv notices on glibc for untransliterable input; the fallback below covers it.
			if ( is_string( $ascii ) && '' !== $ascii ) {
				$slug = strtolower( $ascii );
			}
		}
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $slug ), '-' );
		$slug = substr( $slug, 0, 40 );
		$slug = trim( $slug, '-' );
		if ( '' === $slug ) {
			$slug = 'provider';
		}
		$base = self::ID_PREFIX . $slug;
		$id   = $base;
		$n    = 2;
		while ( in_array( $id, $taken, true ) ) {
			$id = $base . '-' . $n;
			++$n;
		}
		return $id;
	}
}
