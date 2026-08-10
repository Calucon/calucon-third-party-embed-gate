<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Providers\Builtin\Descriptors;
use ConsentGate\Support\Disclosure;
use PHPUnit\Framework\TestCase;

final class DisclosureTest extends TestCase {

	public function test_names_controller_and_policy_per_enabled_provider(): void {
		$draft = Disclosure::draft( Descriptors::all() );

		self::assertStringContainsString( 'YouTube — provided by Google Ireland Limited, Dublin, Ireland.', $draft );
		self::assertStringContainsString( 'https://policies.google.com/privacy', $draft );
		self::assertStringContainsString( 'only after you actively click', $draft );
	}

	public function test_disabled_providers_are_omitted(): void {
		$providers = Descriptors::all();
		foreach ( $providers as $i => $descriptor ) {
			if ( 'youtube' === $descriptor['id'] ) {
				$providers[ $i ]['enabled'] = false;
			}
		}

		self::assertStringNotContainsString( 'YouTube', Disclosure::draft( $providers ) );
	}

	public function test_no_compliance_claim_appears_anywhere(): void {
		// Invariant 10: the draft describes behaviour; it never asserts the
		// site's legal state.
		$draft = strtolower( Disclosure::draft( Descriptors::all() ) );

		self::assertStringNotContainsString( 'gdpr compliant', $draft );
		self::assertStringNotContainsString( 'dsgvo-konform', $draft );
		self::assertStringNotContainsString( 'compliant', $draft );
	}
}
