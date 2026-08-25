<?php
/**
 * The Compatibility screen's optimizer verdict.
 *
 * The reading of each plugin's own options lives in the caller; this is the
 * part that decides what the owner is told, and the part where getting it
 * wrong is quiet. A false "nothing risky is on" would make the owner stop
 * looking at the plugin that is actually breaking their embeds.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Admin\Compatibility;
use PHPUnit\Framework\TestCase;

final class CompatibilityStateTest extends TestCase {

	/**
	 * The distinction the whole row depends on: settings that were read and
	 * are harmless, versus settings that could not be read at all. These must
	 * never collapse into one state.
	 */
	public function test_unreadable_settings_are_unknown_not_off(): void {
		self::assertSame( 'unknown', Compatibility::optimizer_state( array() ) );
		self::assertSame( 'off', Compatibility::optimizer_state( array( 'delay' => false, 'combine' => false ) ) );
	}

	/**
	 * Delay outranks combine: it is the only setting that costs the visitor a
	 * click, so it is the one worth naming when both are on.
	 */
	public function test_delay_outranks_combine(): void {
		self::assertSame( 'delay', Compatibility::optimizer_state( array( 'delay' => true, 'combine' => true ) ) );
		self::assertSame( 'delay', Compatibility::optimizer_state( array( 'delay' => true, 'combine' => false ) ) );
		self::assertSame( 'combine', Compatibility::optimizer_state( array( 'delay' => false, 'combine' => true ) ) );
	}

	/**
	 * A partial read — one flag present, the other missing — is still a read,
	 * and must be reported on what it found rather than dismissed as unknown.
	 */
	public function test_a_partial_read_is_still_a_read(): void {
		self::assertSame( 'combine', Compatibility::optimizer_state( array( 'combine' => true ) ) );
		self::assertSame( 'off', Compatibility::optimizer_state( array( 'combine' => false ) ) );
	}

	/**
	 * Every state the evaluator can return needs a message on the screen, or
	 * the row renders as an empty cell and says nothing at all.
	 */
	public function test_every_state_has_a_message_on_the_settings_screen(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/SettingsPage.php' );

		foreach ( array( 'delay', 'combine', 'off', 'unknown' ) as $state ) {
			self::assertMatchesRegularExpression(
				"/'" . $state . "'\s*=>/",
				$source,
				"no \$optimizer_messages entry for the '$state' state"
			);
		}
	}
}
