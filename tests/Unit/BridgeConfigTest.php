<?php
/**
 * The §6.4 fail-closed rules, pinned: the bridge config is null — meaning
 * not a byte of CMP code reaches the page — unless the owner enabled the
 * bridge AND a platform from the tested list is actually installed.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Cmp\BridgeConfig;
use ConsentGate\Support\Options;
use PHPUnit\Framework\TestCase;

final class BridgeConfigTest extends TestCase {

	private function cmp_options( array $overrides = array() ): array {
		return array_merge( Options::defaults()['cmp'], $overrides );
	}

	public function test_off_by_default_even_with_a_cmp_installed(): void {
		self::assertNull(
			BridgeConfig::build(
				array( array( 'id' => 'complianz', 'label' => 'Complianz' ) ),
				$this->cmp_options()
			)
		);
	}

	public function test_enabled_but_nothing_detected_stays_off(): void {
		// The fail-closed core: an untested or absent platform gets no
		// adapter, so gating stands exactly as without the feature.
		self::assertNull( BridgeConfig::build( array(), $this->cmp_options( array( 'bridge' => true ) ) ) );
	}

	public function test_first_detected_platform_supplies_the_adapter(): void {
		$config = BridgeConfig::build(
			array(
				array( 'id' => 'complianz', 'label' => 'Complianz' ),
				array( 'id' => 'wp-consent-api', 'label' => 'WP Consent API' ),
			),
			$this->cmp_options( array( 'bridge' => true ) )
		);

		// One authority only — the native adapter outranks the generic one.
		self::assertSame( 'complianz', $config['adapter'] );
		self::assertSame( 'marketing', $config['category'] );
		self::assertArrayNotHasKey( 'tcf', $config );
		self::assertArrayNotHasKey( 'borlabsGroup', $config );
	}

	public function test_cookieyes_uses_its_own_category_name(): void {
		$config = BridgeConfig::build(
			array( array( 'id' => 'cookieyes', 'label' => 'CookieYes' ) ),
			$this->cmp_options( array( 'bridge' => true ) )
		);

		self::assertSame( 'advertisement', $config['category'] );
	}

	public function test_borlabs_carries_the_configured_group(): void {
		$config = BridgeConfig::build(
			array( array( 'id' => 'borlabs', 'label' => 'Borlabs Cookie' ) ),
			$this->cmp_options( array( 'bridge' => true, 'borlabs_group' => 'medien' ) )
		);

		self::assertSame( 'medien', $config['borlabsGroup'] );

		$default = BridgeConfig::build(
			array( array( 'id' => 'borlabs', 'label' => 'Borlabs Cookie' ) ),
			$this->cmp_options( array( 'bridge' => true, 'borlabs_group' => '' ) )
		);

		self::assertSame( 'external-media', $default['borlabsGroup'] );
	}

	public function test_tcf_needs_its_own_flag(): void {
		$without = BridgeConfig::build(
			array( array( 'id' => 'complianz', 'label' => 'Complianz' ) ),
			$this->cmp_options( array( 'bridge' => true ) )
		);
		self::assertArrayNotHasKey( 'tcf', $without );

		$with = BridgeConfig::build(
			array( array( 'id' => 'complianz', 'label' => 'Complianz' ) ),
			$this->cmp_options( array( 'bridge' => true, 'tcf' => true ) )
		);
		self::assertSame( 755, $with['tcf']['vendors']['youtube'] );
		self::assertSame( 755, $with['tcf']['vendors']['google-maps'] );
	}

	public function test_tcf_alone_can_carry_the_bridge(): void {
		// An ad-monetised site may run a TCF CMP that is not on the plugin
		// list; the flag alone produces a config with no platform adapter.
		$config = BridgeConfig::build( array(), $this->cmp_options( array( 'bridge' => true, 'tcf' => true ) ) );

		self::assertNull( $config['adapter'] );
		self::assertSame( 755, $config['tcf']['vendors']['youtube'] );
	}
}
