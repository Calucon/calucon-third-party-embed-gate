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

	/**
	 * Every cache plugin this screen can detect must have somewhere to send
	 * the owner.
	 *
	 * Knowing which files to exclude is useless without knowing where the
	 * exclusion list lives, and each of these plugins hides it somewhere
	 * different. Adding a ninth plugin to the detection list is easy and
	 * forgetting the advice for it is easier, and the failure is quiet: the
	 * owner is told their optimiser combines JavaScript and then left to find
	 * the setting themselves.
	 *
	 * Read from the source rather than called, because exclusion_location()
	 * goes through __() and the fixture suite runs without WordPress. Two
	 * plugins answer "nothing to exclude" on purpose — WP Super Cache does not
	 * touch JavaScript, and Cloudflare's Rocket Loader is switched off per
	 * script instead of by a list — so this checks that a case exists, not
	 * that it names a menu path.
	 */
	public function test_every_detectable_cache_plugin_has_exclusion_advice(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/Compatibility.php' );

		self::assertSame(
			1,
			preg_match( '/\$cache_plugins\s*=\s*array\((.*?)\);/su', $source, $block ),
			'the cache-plugin detection list moved; this test can no longer find it'
		);
		self::assertSame(
			1,
			preg_match( '/private static function exclusion_location.*?^\t\}/sum', $source, $method ),
			'exclusion_location() moved or was renamed'
		);

		preg_match_all( "/'([^']+)'\s*=>/", $block[1], $names );
		self::assertNotEmpty( $names[1], 'no cache plugins found in the detection list' );

		$missing = array();
		foreach ( $names[1] as $name ) {
			if ( false === strpos( $method[0], "case '" . $name . "':" ) ) {
				$missing[] = $name;
			}
		}

		self::assertSame(
			array(),
			$missing,
			"Compatibility::detect() can report these cache plugins, but exclusion_location()\n"
				. "has no case for them, so the Status screen tells the owner to exclude files\n"
				. "without saying where:\n  " . implode( "\n  ", $missing )
		);
	}


	/**
	 * The exclusion paths must name files that exist, and the same files the
	 * plugin actually enqueues on the front end.
	 *
	 * Compatibility::exclusion_paths() carries its own hardcoded copy of the
	 * asset list — a second source of truth beside Integration/Assets.php —
	 * and plugins_url() performs no existence check. So a rename that updates
	 * the enqueue and forgets this file produces advice pointing at a file
	 * that is not there, and the site owner pastes it into ANOTHER plugin's
	 * configuration, where nothing can tell them it is wrong.
	 *
	 * The WordPress test that reads this block off the screen cannot catch it:
	 * it asserts the same literal the stale code emits, so both move together
	 * and stay green. Hence a source-level comparison instead.
	 */
	public function test_the_exclusion_paths_are_real_files_and_match_the_enqueued_set(): void {
		$root = dirname( __DIR__, 2 );

		$source = (string) file_get_contents( $root . '/src/Admin/Compatibility.php' );
		self::assertSame(
			1,
			preg_match( '/public static function exclusion_paths.*?^\t\}/sum', $source, $method ),
			'exclusion_paths() moved or was renamed'
		);
		preg_match_all( "#'(assets/[A-Za-z0-9/._-]+)'#", $method[0], $advertised );

		$assets = (string) file_get_contents( $root . '/src/Integration/Assets.php' );
		preg_match_all( "#'(assets/[A-Za-z0-9/._-]+)'#", $assets, $enqueued );

		self::assertNotEmpty( $advertised[1], 'no asset paths found in exclusion_paths()' );

		$missing = array();
		foreach ( $advertised[1] as $asset ) {
			if ( ! is_file( $root . '/' . $asset ) ) {
				$missing[] = $asset;
			}
		}
		self::assertSame(
			array(),
			$missing,
			"The Status screen tells the owner to exclude files that do not exist:\n  "
				. implode( "\n  ", $missing )
		);

		$advertised_set = array_unique( $advertised[1] );
		$enqueued_set   = array_unique( $enqueued[1] );
		sort( $advertised_set );
		sort( $enqueued_set );
		self::assertSame(
			$enqueued_set,
			$advertised_set,
			"Compatibility::exclusion_paths() and Integration/Assets.php disagree about which\n"
				. "assets this plugin ships. Whichever is right, the owner is being given the\n"
				. 'other one.'
		);
	}

}
