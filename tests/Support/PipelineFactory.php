<?php
/**
 * Builds the WordPress-free pipeline exactly as the plugin wires it,
 * minus the WordPress bridges. Shared by unit tests, fixture generation
 * and the E2E test server.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Support;

use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use ConsentGate\Detection\IframeRule;
use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;

final class PipelineFactory {

	/**
	 * The fixture corpus treats example.test as the site's own host.
	 *
	 * @param string[] $own_hosts Own hosts.
	 * @return IframeRule
	 */
	public static function rule( array $own_hosts = array( 'example.test' ) ): IframeRule {
		return new IframeRule(
			new HtmlScanner(),
			new HostMatcher( $own_hosts ),
			new Registry(),
			new PlaceholderRenderer()
		);
	}
}
