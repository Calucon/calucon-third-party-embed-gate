<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use ConsentGate\Providers\Builtin\Descriptors;
use ConsentGate\Providers\Registry;
use ConsentGate\Support\ContentScan;
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
}
