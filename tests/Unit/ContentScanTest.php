<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\HostMatcher;
use CaluconEmbedGate\Detection\HtmlScanner;
use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Support\ContentScan;
use PHPUnit\Framework\TestCase;

final class ContentScanTest extends TestCase {

	private function scanner( array $flags = array() ): ContentScan {
		return new ContentScan(
			new HtmlScanner(),
			new HostMatcher( array( 'example.test' ) ),
			new Registry( Descriptors::all() ),
			array_merge(
				array(
					'iframes' => true,
					'scripts' => true,
					'images'  => false,
				),
				$flags
			)
		);
	}

	public function test_aggregate_groups_by_tag_host_status_and_keeps_first_source(): void {
		$scanned = array(
			array(
				'source' => 'First post',
				'rows'   => $this->scanner()->scan(
					'<iframe src="https://www.youtube.com/embed/a"></iframe>'
					. '<iframe src="https://www.youtube.com/embed/b"></iframe>'
				),
			),
			array(
				'source' => 'Second post',
				'rows'   => $this->scanner()->scan(
					'<iframe src="https://www.youtube.com/embed/c"></iframe>'
					. '<iframe src="https://player.vimeo.com/video/1"></iframe>'
				),
			),
		);

		$rows = ContentScan::aggregate( $scanned );

		self::assertCount( 2, $rows );
		$youtube = $rows[0];
		self::assertSame( 'www.youtube.com', $youtube['host'] );
		self::assertSame( 3, $youtube['count'] );
		self::assertSame( 'First post', $youtube['first_seen'] );
		self::assertSame( 'https://www.youtube.com/embed/a', $youtube['url'] );
		self::assertSame( ContentScan::GATED, $youtube['status'] );
		self::assertSame( 'player.vimeo.com', $rows[1]['host'] );
		self::assertSame( 'Second post', $rows[1]['first_seen'] );
	}

	public function test_reports_gated_and_not_gated_rows(): void {
		$html = '<iframe src="https://www.youtube.com/embed/y_pjE_p1HwE"></iframe>'
			. '<img src="https://i.ytimg.com/vi/x/hq.jpg" alt="">'
			. '<script src="https://cdn.widget.example/w.js"></script>';

		$rows = $this->scanner()->scan( $html );

		$by_tag = array();
		foreach ( $rows as $row ) {
			$by_tag[ $row['tag'] ] = $row;
		}

		self::assertSame( ContentScan::GATED, $by_tag['iframe']['status'] );
		self::assertSame( 'YouTube', $by_tag['iframe']['label'] );
		self::assertSame( ContentScan::GATED, $by_tag['script']['status'] );
		// The image rule is off by default: the scan must SAY so rather
		// than hide the host — that visibility is the point of the screen.
		self::assertSame( ContentScan::RULE_DISABLED, $by_tag['img']['status'] );
	}

	public function test_own_host_iframe_is_reported_as_own(): void {
		$rows = $this->scanner()->scan( '<iframe src="https://example.test/map"></iframe>' );

		self::assertCount( 1, $rows );
		self::assertSame( ContentScan::OWN_HOST, $rows[0]['status'] );
	}

	public function test_rule_toggles_change_the_verdict(): void {
		$rows = $this->scanner( array( 'iframes' => false ) )->scan(
			'<iframe src="https://www.youtube.com/embed/y_pjE_p1HwE"></iframe>'
		);

		self::assertSame( ContentScan::RULE_DISABLED, $rows[0]['status'] );
	}

	/**
	 * The admin needs to tell an unknown third party from a named provider so
	 * it can lead with the safe action ("name this host") rather than the one
	 * that switches gating off. The label cannot answer that: the generic
	 * fallback uses the host name AS the label.
	 */
	public function test_rows_carry_the_resolved_provider_id(): void {
		$scan = new ContentScan(
			new HtmlScanner(),
			new HostMatcher( array( 'example.test' ) ),
			new Registry( Descriptors::all() ),
			array( 'iframes' => true, 'scripts' => true, 'images' => true )
		);

		$rows = $scan->scan(
			'<iframe src="https://www.youtube.com/embed/y_pjE_p1HwE" title="Y"></iframe>'
			. '<iframe src="https://widgets.example-partner.com/embed/9" title="W"></iframe>'
			. '<script src="https://cdn.unknown-sdk.example/w.js"></script>'
			. '<iframe src="https://example.test/local" title="Own"></iframe>'
		);

		$by_host = array();
		foreach ( $rows as $row ) {
			$by_host[ $row['host'] ] = $row;
		}

		self::assertSame( 'youtube', $by_host['www.youtube.com']['provider'] );
		self::assertSame( 'generic', $by_host['widgets.example-partner.com']['provider'], 'an undescribed iframe host' );
		self::assertSame( 'generic-script', $by_host['cdn.unknown-sdk.example']['provider'], 'an undescribed script host' );
		self::assertSame( '', $by_host['example.test']['provider'], 'own host: no provider resolved' );
		// The label alone could not have told these apart.
		self::assertSame( 'widgets.example-partner.com', $by_host['widgets.example-partner.com']['label'] );
	}

	public function test_aggregate_keeps_the_provider_id(): void {
		$aggregated = ContentScan::aggregate(
			array(
				array(
					'source' => 'A post',
					'rows'   => array(
						array( 'tag' => 'iframe', 'url' => 'https://a.example/1', 'host' => 'a.example', 'status' => ContentScan::GATED, 'label' => 'a.example', 'provider' => 'generic' ),
						array( 'tag' => 'iframe', 'url' => 'https://a.example/2', 'host' => 'a.example', 'status' => ContentScan::GATED, 'label' => 'a.example', 'provider' => 'generic' ),
					),
				),
			)
		);

		self::assertCount( 1, $aggregated );
		self::assertSame( 'generic', $aggregated[0]['provider'] );
		self::assertSame( 2, $aggregated[0]['count'] );
	}

	/**
	 * An exempted own-asset path is reported as NOT gated, and says why.
	 *
	 * This screen's whole reason to exist is reporting "what it would NOT gate
	 * and why, which is the part a site owner cannot see from the front end".
	 * A script on a /wp-content/ path is left alone whatever host serves it —
	 * and the scan reported it as "Gated", the opposite of the truth, on the
	 * one row where the owner might want to reach for the always-gate list.
	 */
	public function test_an_exempted_own_asset_path_is_not_reported_as_gated(): void {
		$rows = $this->scanner()->scan(
			'<script src="https://cdn.example.net/wp-content/themes/x/app.js"></script>'
			. '<script src="https://cdn.example.net/sdk/widget.js"></script>'
		);
		$by_url = array();
		foreach ( $rows as $row ) {
			$by_url[ $row['url'] ] = $row['status'];
		}

		self::assertSame(
			ContentScan::OWN_ASSET_PATH,
			$by_url['https://cdn.example.net/wp-content/themes/x/app.js'],
			'the scan claims to gate a script the rules leave alone'
		);
		// Same host, ordinary path: genuinely gated, and still reported so.
		self::assertSame(
			ContentScan::GATED,
			$by_url['https://cdn.example.net/sdk/widget.js']
		);
	}

	/**
	 * A known provider cannot hide behind the path here either.
	 *
	 * ScriptRule refuses the exemption for a host that already belongs to a
	 * provider. The first version of the scan's own-asset branch omitted that
	 * condition, so the screen said "NOT gated — treated as your own file"
	 * about a script the pipeline gates. That is the original defect pointing
	 * the other way, and the more dangerous direction: the owner is told a
	 * tracker is being let through when it is not, and reaches for a setting
	 * they do not need.
	 */
	public function test_a_provider_host_on_a_wp_path_is_still_reported_as_gated(): void {
		$rows = $this->scanner()->scan( '<script src="https://platform.twitter.com/wp-content/plugins/widgets.js"></script>' );
		self::assertSame( ContentScan::GATED, $rows[0]['status'] );
	}

	/**
	 * The owner's explicit list still outranks the heuristic, here as well as
	 * on the render path — otherwise the screen would tell them the host they
	 * just added to always-gate is still being let through.
	 */
	public function test_always_gate_returns_an_exempted_path_to_gated(): void {
		$scan = new ContentScan(
			new HtmlScanner(),
			new HostMatcher( array( 'example.test' ), true, null, array( 'cdn.example.net' ) ),
			new Registry( Descriptors::all() ),
			array( 'iframes' => true, 'scripts' => true, 'images' => false )
		);
		$rows = $scan->scan( '<script src="https://cdn.example.net/wp-content/themes/x/app.js"></script>' );
		self::assertSame( ContentScan::GATED, $rows[0]['status'] );
	}

}
