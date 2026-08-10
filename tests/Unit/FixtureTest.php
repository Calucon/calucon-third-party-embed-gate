<?php
/**
 * The fixture corpus (PLAN.md §10.1) — the highest-value asset in the repo.
 *
 * Pass-through cases assert byte-identity: that is what catches a scanner
 * that "works" but reformats. Every case additionally asserts idempotency —
 * gated output re-fed through the rule must come back unchanged.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Tests\Support\PipelineFactory;
use PHPUnit\Framework\TestCase;

final class FixtureTest extends TestCase {

	/**
	 * @return iterable<string,array{string}>
	 */
	public function fixture_provider(): iterable {
		$root = dirname( __DIR__ ) . '/Fixtures';
		foreach ( scandir( $root ) as $entry ) {
			if ( '.' === $entry[0] || ! is_dir( $root . '/' . $entry ) ) {
				continue;
			}
			yield $entry => array( $root . '/' . $entry );
		}
	}

	/**
	 * @dataProvider fixture_provider
	 */
	public function test_fixture( string $dir ): void {
		$input    = file_get_contents( $dir . '/input.html' );
		$expected = file_get_contents( $dir . '/expected.html' );

		self::assertNotFalse( $input, 'missing input.html' );
		self::assertNotFalse( $expected, "missing expected.html in $dir — generate it with tests/bin/generate-fixtures.php and review the output" );

		$actual = PipelineFactory::rule()->apply( $input, array( 'integration' => 'test' ) );

		self::assertSame( $expected, $actual, basename( $dir ) );
	}

	/**
	 * Re-feeding output through the rule must be a no-op for every fixture
	 * (PLAN.md §9.1, §10.1 "already-gated content re-fed through the filter").
	 *
	 * @dataProvider fixture_provider
	 */
	public function test_fixture_is_idempotent( string $dir ): void {
		$rule = PipelineFactory::rule();
		$once = $rule->apply( (string) file_get_contents( $dir . '/input.html' ), array() );

		self::assertSame( $once, $rule->apply( $once, array() ), basename( $dir ) . ' (second pass)' );
	}
}
