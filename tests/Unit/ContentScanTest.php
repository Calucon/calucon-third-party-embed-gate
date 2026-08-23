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
}
