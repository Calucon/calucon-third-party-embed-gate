<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Providers\CustomProviders;
use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Support\Options;
use CaluconEmbedGate\Tests\Support\PipelineFactory;
use PHPUnit\Framework\TestCase;

final class CustomProvidersTest extends TestCase {

	private function rows(): array {
		return Options::sanitize(
			array(
				'custom_providers' => array(
					array( 'label' => 'Example Partner', 'hosts' => "widgets.example-partner.com\nhttps://www.example-partner.com/embed/1", 'kind' => 'video' ),
					array( 'label' => 'Widget SDK', 'script_hosts' => 'cdn.widget-sdk.example' ),
				),
			)
		)['custom_providers'];
	}

	public function test_descriptors_carry_label_hosts_kind_and_generic_wording(): void {
		$descriptors = CustomProviders::descriptors( $this->rows() );

		self::assertCount( 2, $descriptors );
		$partner = $descriptors[0];
		self::assertSame( 'custom-example-partner', $partner['id'] );
		self::assertSame( 'Example Partner', $partner['label'] );
		self::assertSame( array( 'widgets.example-partner.com', 'www.example-partner.com' ), $partner['match']['iframe_host'] );
		self::assertArrayNotHasKey( 'script_host', $partner['match'] );
		self::assertSame( 'video', $partner['kind'] );
		self::assertSame( 'iframe', $partner['strategy'] );
		self::assertTrue( $partner['custom'] );
		self::assertNull( $partner['load_host'], 'a custom provider never rewrites the load target' );
		self::assertSame( 'Load content from Example Partner', $partner['action'] );
		self::assertStringContainsString( 'connects your browser to Example Partner', $partner['note'] );

		$sdk = $descriptors[1];
		self::assertSame( 'script', $sdk['strategy'] );
		self::assertSame( array( 'cdn.widget-sdk.example' ), $sdk['match']['script_host'] );
	}

	public function test_translation_callable_is_applied_to_the_wording(): void {
		$descriptors = CustomProviders::descriptors(
			$this->rows(),
			static function ( string $text ): string {
				return 'Load content from %s' === $text ? 'Inhalt von %s laden' : $text;
			}
		);

		self::assertSame( 'Inhalt von Example Partner laden', $descriptors[0]['action'] );
	}

	public function test_gated_with_the_owner_label_end_to_end(): void {
		$providers = array_merge( CustomProviders::descriptors( $this->rows() ), Descriptors::all() );

		$html = PipelineFactory::gate(
			'<iframe src="https://widgets.example-partner.com/embed/9" title="W" sandbox="allow-scripts"></iframe>'
			. '<script src="https://cdn.widget-sdk.example/sdk.js"></script>',
			array( 'example.test' ),
			array(),
			$providers
		);

		self::assertStringContainsString( 'data-cg-provider="custom-example-partner"', $html );
		self::assertStringContainsString( 'Load content from Example Partner', $html );
		self::assertStringContainsString( 'data-cg-provider="custom-widget-sdk"', $html );
		// Privilege never widens: the sandbox survives in the payload.
		self::assertStringContainsString( 'allow-scripts', $html );
		self::assertStringNotContainsString( '<iframe', $html );
	}

	public function test_custom_provider_listed_first_takes_precedence_over_a_builtin_host(): void {
		$custom = Options::sanitize(
			array( 'custom_providers' => array( array( 'label' => 'My Tube', 'hosts' => 'www.youtube.com' ) ) )
		)['custom_providers'];
		$registry = new Registry( array_merge( CustomProviders::descriptors( $custom ), Descriptors::all() ) );

		$resolved = $registry->resolve_for_url( 'https://www.youtube.com/embed/abc', 'www.youtube.com' );

		self::assertSame( 'custom-my-tube', $resolved['id'] );
		self::assertNull( $resolved['load_host'] );
	}

	public function test_per_provider_overrides_apply_to_custom_ids_too(): void {
		$options = Options::sanitize(
			array(
				'custom_providers' => array( array( 'label' => 'Example Partner', 'hosts' => 'widgets.example-partner.com' ) ),
				'providers'        => array( 'custom-example-partner' => array( 'note' => 'Partner rules.', 'privacy_url' => 'https://example-partner.com/privacy' ) ),
			)
		);
		$providers = Options::apply_provider_overrides( CustomProviders::descriptors( $options['custom_providers'] ), $options );

		self::assertSame( 'Partner rules.', $providers[0]['note'] );
		self::assertSame( 'https://example-partner.com/privacy', $providers[0]['privacy_url'] );
	}

	public function test_id_for_slugifies_and_disambiguates(): void {
		self::assertSame( 'custom-example-videos', CustomProviders::id_for( 'Example Videos!', array() ) );
		self::assertSame( 'custom-example-videos-2', CustomProviders::id_for( 'Example Videos', array( 'custom-example-videos' ) ) );
		self::assertSame( 'custom-example-videos-3', CustomProviders::id_for( 'Example Videos', array( 'custom-example-videos', 'custom-example-videos-2' ) ) );
		self::assertSame( 'custom-provider', CustomProviders::id_for( '!!!', array() ) );
		self::assertMatchesRegularExpression( '/^custom-[a-z0-9-]{1,40}$/', CustomProviders::id_for( str_repeat( 'Very long label ', 10 ), array() ) );
	}
}
