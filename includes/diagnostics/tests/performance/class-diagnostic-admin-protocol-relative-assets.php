<?php
/**
 * Admin Protocol-Relative Assets Diagnostic
 *
 * Scans the captured admin page HTML for protocol-relative URLs (// instead
 * of https://) in script src and link href attributes. Protocol-relative
 * URLs were a legacy technique for mixed HTTP/HTTPS sites but are now
 * considered anti-patterns on HTTPS-only sites.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Admin_Page_HTML_Helper as Admin_HTML;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Admin_Protocol_Relative_Assets Class
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Protocol_Relative_Assets extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-protocol-relative-assets';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Protocol-Relative Asset URLs in Admin';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Scans admin page HTML for protocol-relative asset URLs (src="//..." or href="//..."). These URLs block HTTP/2 preconnect hinting, reduce CDN origin detection accuracy, and can cause mixed-content warnings if the server is ever accessed over HTTP.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Run the diagnostic check.
	 *
	 * Retrieves the admin page HTML and searches all script src and link href
	 * attributes for protocol-relative URLs. Absolute https:// URLs are healthy;
	 * relative /wp-content/... paths are healthy; only //<host>/... forms flag.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when protocol-relative assets are detected, null when healthy.
	 */
	public static function check(): ?array {
		$html = Admin_HTML::get_html();

		if ( null === $html ) {
			return null; // HTML not yet captured; skip gracefully.
		}

		$proto_relative = array();

		// <script src="//...">
		if ( preg_match_all( '/<script\b[^>]+\bsrc\s*=\s*["\'](\\/\\/[^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$proto_relative[] = $url;
			}
		}

		// <link href="//...">
		if ( preg_match_all( '/<link\b[^>]+\bhref\s*=\s*["\'](\\/\\/[^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$proto_relative[] = $url;
			}
		}

		// <img src="//..."> (less critical but still flags plugin quality).
		if ( preg_match_all( '/<img\b[^>]+\bsrc\s*=\s*["\'](\\/\\/[^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$proto_relative[] = $url;
			}
		}

		$proto_relative = array_unique( $proto_relative );
		$count          = count( $proto_relative );

		if ( 0 === $count ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of protocol-relative URLs */
				_n(
					'%d admin asset uses a protocol-relative URL (//). Protocol-relative URLs were designed for sites serving both HTTP and HTTPS but are now obsolete on HTTPS-only sites. They prevent browsers from using HTTP/2 preconnect hinting optimally and can cause mixed-content security warnings if the site is ever accessed over plain HTTP.',
					'%d admin assets use protocol-relative URLs (//). Protocol-relative URLs were designed for sites serving both HTTP and HTTPS but are now obsolete on HTTPS-only sites. They prevent browsers from using HTTP/2 preconnect hinting optimally and can cause mixed-content security warnings if the site is ever accessed over plain HTTP.',
					$count,
					'thisismyurl-shadow'
				),
				$count
			),
			'severity'     => 'low',
			'threat_level' => min( 30, 10 + ( $count * 3 ) ),
			'details'      => array(
				'protocol_relative_count' => $count,
				'urls'                    => array_slice( $proto_relative, 0, 10 ),
				'note'                    => __(
					'These plugin assets use // URLs. On a fully HTTPS site, all asset URLs should use https://. This is a plugin configuration issue — report it to the respective plugin authors or use a URL normalisation plugin if needed.',
					'thisismyurl-shadow'
				),
			),
		);
	}
}
