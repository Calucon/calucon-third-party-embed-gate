<?php
/**
 * Provider descriptor helpers.
 *
 * A provider is data, not a class hierarchy (PLAN.md §4.1). This class only
 * normalises descriptor arrays so the rest of the code can rely on the keys
 * existing. WordPress-free by design.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Providers;

/**
 * Fills a provider descriptor with defaults and interpolates {placeholders}.
 */
final class Provider {

	/**
	 * Descriptor keys every provider carries after normalisation.
	 *
	 * @param array $descriptor Partial descriptor.
	 * @return array Complete descriptor.
	 */
	public static function normalize( array $descriptor ): array {
		return array_merge(
			array(
				'id'           => 'generic',
				'label'        => '',
				'match'        => array(),
				'load_host'    => null,
				'load_path'    => null,
				'fallback'     => '',
				'privacy_url'  => null,
				'controller'   => null,
				'note'         => '',
				'action'       => '',
				'thumbnail'    => null,
				'aspect'       => null,
				'iframe_allow' => null,
				'strategy'     => 'iframe',
			),
			$descriptor
		);
	}

	/**
	 * Interpolate named captures into a URL template. Every value is
	 * URL-encoded at substitution time, never at template-authoring time
	 * (PLAN.md §4.1).
	 *
	 * @param string $template Template containing {name} placeholders.
	 * @param array  $values   name => raw value.
	 * @return string
	 */
	public static function interpolate( string $template, array $values ): string {
		return preg_replace_callback(
			'/\{([a-z0-9_]+)\}/i',
			static function ( array $m ) use ( $values ) {
				return isset( $values[ $m[1] ] ) ? rawurlencode( (string) $values[ $m[1] ] ) : $m[0];
			},
			$template
		);
	}
}
