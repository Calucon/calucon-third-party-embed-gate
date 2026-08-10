<?php
/**
 * Privacy-policy disclosure draft generator (PLAN.md §14).
 *
 * Every descriptor already carries the facts a privacy policy needs to name
 * a provider — label, controller, privacy-policy URL, and what loading the
 * embed transmits. This assembles them into a DRAFT for the site owner to
 * review and adapt. It is a starting point, never a compliance artefact:
 * the plugin cannot know the site's processing purposes (invariant 10).
 *
 * WordPress-free by design (PLAN.md §2.2).
 *
 * @package ConsentGate
 */

namespace ConsentGate\Support;

/**
 * Builds plain text, one paragraph per enabled provider.
 */
final class Disclosure {

	/**
	 * @param array[]       $providers Normalised (or raw) descriptors.
	 * @param callable|null $translate Maps English strings to the site language.
	 * @return string Plain-text draft; '' when nothing is disclosable.
	 */
	public static function draft( array $providers, ?callable $translate = null ): string {
		$t = $translate ?? static function ( string $text ): string {
			return $text;
		};

		$paragraphs = array();

		foreach ( $providers as $descriptor ) {
			if ( isset( $descriptor['enabled'] ) && false === $descriptor['enabled'] ) {
				continue;
			}
			$label      = isset( $descriptor['label'] ) && is_string( $descriptor['label'] ) ? trim( $descriptor['label'] ) : '';
			$controller = isset( $descriptor['controller'] ) && is_string( $descriptor['controller'] ) ? trim( $descriptor['controller'] ) : '';
			$policy     = isset( $descriptor['privacy_url'] ) && is_string( $descriptor['privacy_url'] ) ? trim( $descriptor['privacy_url'] ) : '';
			if ( '' === $label || '' === $controller ) {
				continue; // The generic entries carry no nameable party.
			}

			$lines = array();
			/* translators: 1: provider label, 2: the provider's legal entity and seat. */
			$lines[] = sprintf( $t( '%1$s — provided by %2$s.' ), $label, $controller );
			/* translators: %s: provider label. */
			$lines[] = sprintf( $t( 'Embedded %s content on this site is loaded only after you actively click its placeholder. Before that click, your browser does not contact this provider. When you do load it, the provider receives your IP address, the address of the page you are on and technical details of your browser, and may set cookies or similar identifiers on your device.' ), $label );
			if ( '' !== $policy ) {
				/* translators: %s: URL of the provider's privacy policy. */
				$lines[] = sprintf( $t( 'Privacy policy: %s' ), $policy );
			}

			$paragraphs[] = implode( ' ', $lines );
		}

		if ( array() === $paragraphs ) {
			return '';
		}

		return implode( "\n\n", $paragraphs );
	}
}
