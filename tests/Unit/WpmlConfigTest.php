<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * wpml-config.xml is read by WPML and Polylang; a typo there fails
 * silently on the site. Pin its shape against the code it describes.
 */
final class WpmlConfigTest extends TestCase {

	public function test_config_is_well_formed_and_names_the_real_option_and_attributes(): void {
		$path = dirname( __DIR__, 2 ) . '/wpml-config.xml';
		$xml  = simplexml_load_file( $path );
		self::assertNotFalse( $xml, 'wpml-config.xml must parse' );

		$option = (string) $xml->{'admin-texts'}->key['name'];
		self::assertSame( \CaluconEmbedGate\Support\Options::OPTION, $option );

		// Every gated block type the editor integration knows gets both
		// attribute keys — derived from editor.js so the two cannot drift.
		$editor = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/editor.js' );
		self::assertSame( 1, preg_match( '/GATED_BLOCKS = \[([^\]]+)\]/', $editor, $m ) );
		preg_match_all( "/'([a-z\/-]+)'/", $m[1], $ids );
		$expected = $ids[1];

		$declared = array();
		foreach ( $xml->{'gutenberg-blocks'}->{'gutenberg-block'} as $block ) {
			$keys = array();
			foreach ( $block->key as $key ) {
				$keys[] = (string) $key['name'];
			}
			self::assertSame( array( 'caluconEmbedGateAction', 'caluconEmbedGateNote' ), $keys, (string) $block['type'] );
			$declared[] = (string) $block['type'];
		}
		self::assertSame( $expected, $declared );
	}
	/**
	 * The registered admin texts have to be three things at once, and each is
	 * a separate way to break them silently on a live multilingual site:
	 *
	 *  - real option keys that survive sanitising (a renamed key registers a
	 *    string nobody ever writes);
	 *  - exactly the keys Plugin::localized_options() re-reads at render time
	 *    (register a key without re-reading it and the translation is stored
	 *    but never shown; re-read one without registering it and there is
	 *    nothing to translate);
	 *  - owner-typed TEXT only — never a behaviour key, because that read
	 *    happens after the boot snapshot and must not be able to change what
	 *    is gated.
	 */
	public function test_registered_admin_texts_are_storable_option_keys(): void {
		$xml = simplexml_load_file( dirname( __DIR__, 2 ) . '/wpml-config.xml' );

		$registered = array();
		foreach ( $xml->{'admin-texts'}->key->key as $subtree ) {
			$section = (string) $subtree['name'];
			foreach ( $subtree->key as $wildcard ) {
				foreach ( $wildcard->key as $leaf ) {
					$registered[ $section ][] = (string) $leaf['name'];
				}
			}
		}

		self::assertSame( array( 'note', 'action', 'privacy_url' ), $registered['providers'] );
		self::assertSame( array( 'label' ), $registered['custom_providers'] );

		// Storable: round-trip every registered key through sanitise.
		$clean = \CaluconEmbedGate\Support\Options::sanitize(
			array(
				'providers'        => array(
					'youtube' => array(
						'note'        => 'Übersetzter Hinweis.',
						'action'      => 'Video laden',
						'privacy_url' => 'https://policies.google.com/privacy?hl=de',
					),
				),
				'custom_providers' => array(
					array(
						'id'    => 'custom-partner',
						'label' => 'Partner (DE)',
						'hosts' => array( 'widgets.example-partner.com' ),
					),
				),
			)
		);
		foreach ( $registered['providers'] as $key ) {
			self::assertArrayHasKey( $key, $clean['providers']['youtube'], "providers/*/$key is registered but not storable" );
		}
		self::assertSame( 'Partner (DE)', $clean['custom_providers'][0]['label'] );
	}

	public function test_the_render_time_reread_covers_exactly_the_registered_texts(): void {
		$plugin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Plugin.php' );

		self::assertSame(
			1,
			preg_match( '/localized_options\(\): array \{(.+?)\n\t\}/s', $plugin, $body ),
			'Plugin::localized_options() is where translated texts are re-read'
		);

		self::assertSame( 1, preg_match( '/foreach \\( array\\( ([^)]+) \\) as \\$key \\)/', $body[1], $keys ) );
		preg_match_all( "/'([a-z_]+)'/", $keys[1], $found );
		self::assertSame( array( 'note', 'action', 'privacy_url' ), $found[1], 'the re-read and wpml-config.xml must name the same provider texts' );

		self::assertStringContainsString( "['label']", $body[1], 'custom provider labels are registered, so they must be re-read too' );

		// Behaviour keys must never come from the late read. Comments are
		// stripped first: the method explains in prose why it does not take
		// 'enabled', and that sentence must not be what passes the test.
		$code = (string) preg_replace( array( '#/\*.*?\*/#s', '#//[^\n]*#' ), '', $body[1] );
		foreach ( array( 'enabled', 'never_gate', 'always_gate', 'own_hosts', 'privacy_variant', 'output_buffer' ) as $behaviour ) {
			self::assertStringNotContainsString( "'" . $behaviour . "'", $code, "$behaviour must stay with the boot snapshot" );
		}
	}

	/**
	 * The Compatibility screen tells the owner WHERE to translate those
	 * strings; a plugin that translates the rendered page instead needs no
	 * such instruction. Both facts live in one table, so pin its shape.
	 */
	public function test_every_known_multilingual_plugin_declares_how_it_translates(): void {
		$plugins = \CaluconEmbedGate\Admin\Compatibility::multilingual_plugins();

		self::assertSame( array( 'WPML', 'Polylang', 'TranslatePress', 'Weglot' ), array_keys( $plugins ) );

		foreach ( $plugins as $name => $spec ) {
			self::assertContains( $spec['mode'], array( 'registry', 'output' ), $name );
			self::assertIsCallable( $spec['signal'], $name );
			self::assertFalse( call_user_func( $spec['signal'] ), "$name must not be detected in the unit suite" );

			if ( 'registry' === $spec['mode'] ) {
				self::assertNotSame( '', $spec['where'], "$name registers strings, so the owner needs to be told where to translate them" );
			} else {
				self::assertSame( '', $spec['where'], $name );
			}
		}
	}
}
