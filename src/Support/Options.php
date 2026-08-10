<?php
/**
 * Options: typed, defaulted, sanitised — schema and defaults in one place
 * (PLAN.md §7.1). Stored as a single option array.
 *
 * The schema and every transform here are WordPress-free pure functions;
 * only the option NAME constant is WordPress-facing. Reading and writing
 * the option happens in Plugin.php and Admin/.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Support;

/**
 * Single source of truth for the option shape.
 */
final class Options {

	public const OPTION = 'consent_gate_options';

	/**
	 * @return array Complete default option tree.
	 */
	public static function defaults(): array {
		return array(
			'providers' => array(
				// '<provider id>' => array(
				//     'enabled'         => true,   gate embeds of this provider
				//     'privacy_variant' => true,   load via nocookie/dnt target
				//     'note'            => '',     override panel note text
				//     'action'          => '',     override button text
				// ),
			),
			'detection' => array(
				'iframes'         => true,
				'scripts'         => true,
				'own_hosts'       => array(), // Site's own CDN / media hosts.
				'never_gate'      => array(), // Hosts the owner exempts (their responsibility).
				'www_equivalence' => true,
				// Whole-document buffer for page builders (§3.3). Invasive;
				// off by default, behind a warning in the UI.
				'output_buffer'   => false,
			),
			'consent'   => array(
				// Consent memory (§6.2). Off by default: out of the box,
				// consent applies to the one embed clicked and is stored
				// nowhere. Client-side only (§6.3) — a server-side state
				// would make every page uncacheable.
				'memory'        => 'off',      // off | session | persistent.
				'scope'         => 'provider', // embed | provider | all.
				'duration_days' => 180,        // Persistent lifetime.
			),
		);
	}

	/**
	 * Sanitise a raw (user-submitted or stored) option tree against the schema.
	 *
	 * @param mixed $raw Anything.
	 * @return array Safe, complete option tree.
	 */
	public static function sanitize( $raw ): array {
		$defaults = self::defaults();
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$clean = $defaults;

		if ( isset( $raw['providers'] ) && is_array( $raw['providers'] ) ) {
			foreach ( $raw['providers'] as $id => $row ) {
				if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9_-]{1,64}$/', $id ) || ! is_array( $row ) ) {
					continue;
				}
				$entry = array();
				if ( array_key_exists( 'enabled', $row ) ) {
					$entry['enabled'] = self::truthy( $row['enabled'] );
				}
				if ( array_key_exists( 'privacy_variant', $row ) ) {
					$entry['privacy_variant'] = self::truthy( $row['privacy_variant'] );
				}
				foreach ( array( 'note', 'action' ) as $text_key ) {
					if ( isset( $row[ $text_key ] ) && is_string( $row[ $text_key ] ) ) {
						$text = trim( strip_tags( $row[ $text_key ] ) );
						if ( '' !== $text ) {
							$entry[ $text_key ] = $text;
						}
					}
				}
				if ( array() !== $entry ) {
					$clean['providers'][ $id ] = $entry;
				}
			}
		}

		if ( isset( $raw['detection'] ) && is_array( $raw['detection'] ) ) {
			$d = $raw['detection'];
			foreach ( array( 'iframes', 'scripts', 'www_equivalence', 'output_buffer' ) as $flag ) {
				if ( array_key_exists( $flag, $d ) ) {
					$clean['detection'][ $flag ] = self::truthy( $d[ $flag ] );
				}
			}
			foreach ( array( 'own_hosts', 'never_gate' ) as $list ) {
				if ( isset( $d[ $list ] ) ) {
					$clean['detection'][ $list ] = self::sanitize_host_list( $d[ $list ] );
				}
			}
		}

		if ( isset( $raw['consent'] ) && is_array( $raw['consent'] ) ) {
			$c = $raw['consent'];
			if ( isset( $c['memory'] ) && in_array( $c['memory'], array( 'off', 'session', 'persistent' ), true ) ) {
				$clean['consent']['memory'] = $c['memory'];
			}
			if ( isset( $c['scope'] ) && in_array( $c['scope'], array( 'embed', 'provider', 'all' ), true ) ) {
				$clean['consent']['scope'] = $c['scope'];
			}
			if ( isset( $c['duration_days'] ) && is_numeric( $c['duration_days'] ) ) {
				$clean['consent']['duration_days'] = max( 1, min( 730, (int) $c['duration_days'] ) );
			}
		}

		return $clean;
	}

	/**
	 * Apply per-provider overrides to the descriptor set. Pure.
	 *
	 * Disabling a provider means its embeds pass through (the owner's
	 * explicit decision) — the descriptor still matches, so the generic
	 * fallback cannot re-gate what the owner exempted.
	 *
	 * @param array[] $providers Descriptors.
	 * @param array   $options   Sanitised option tree.
	 * @return array[]
	 */
	public static function apply_provider_overrides( array $providers, array $options ): array {
		$overrides = isset( $options['providers'] ) ? $options['providers'] : array();
		if ( array() === $overrides ) {
			return $providers;
		}

		foreach ( $providers as $i => $descriptor ) {
			$id = isset( $descriptor['id'] ) ? $descriptor['id'] : null;
			if ( null === $id || ! isset( $overrides[ $id ] ) ) {
				continue;
			}
			$row = $overrides[ $id ];

			if ( array_key_exists( 'enabled', $row ) ) {
				$descriptor['enabled'] = (bool) $row['enabled'];
			}
			if ( isset( $row['privacy_variant'] ) && false === $row['privacy_variant'] ) {
				// Load from the original host: drop the rewrite, keep the gate.
				$descriptor['load_host']  = null;
				$descriptor['load_path']  = null;
				$descriptor['load_query'] = array();
			}
			foreach ( array( 'note', 'action' ) as $text_key ) {
				if ( isset( $row[ $text_key ] ) && '' !== $row[ $text_key ] ) {
					$descriptor[ $text_key ] = $row[ $text_key ];
				}
			}

			$providers[ $i ] = $descriptor;
		}

		return $providers;
	}

	/**
	 * @param mixed $value Checkbox-ish input.
	 * @return bool
	 */
	private static function truthy( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'on', 'yes', 'true' ), true );
	}

	/**
	 * One host per entry; accepts an array or a newline-separated string.
	 * Entries may use a leading '*.' wildcard (PLAN.md §3.4).
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	private static function sanitize_host_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n,]+/', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$hosts = array();
		foreach ( $value as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}
			$entry = strtolower( trim( $entry ) );
			// Tolerate pasted URLs: keep only the host part.
			$entry = preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $entry );
			$entry = preg_replace( '#[/:].*$#', '', (string) $entry );
			$entry = rtrim( (string) $entry, '.' ); // FQDN trailing dot.
			if ( '' === $entry ) {
				continue;
			}
			if ( preg_match( '/^(\*\.)?[a-z0-9\x80-\xff]([a-z0-9\x80-\xff.-]*[a-z0-9\x80-\xff])?$/', $entry ) ) {
				$hosts[] = $entry;
			}
		}

		return array_values( array_unique( $hosts ) );
	}
}
