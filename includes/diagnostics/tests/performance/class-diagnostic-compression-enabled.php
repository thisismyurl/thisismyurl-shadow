<?php
/**
 * Compression Enabled Diagnostic
 *
 * Checks whether HTTP compression (gzip or brotli) is active by inspecting
 * the Content-Encoding header returned for the site homepage.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Request_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compression Enabled Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Compression_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'compression-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Compression Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'HTTP compression (gzip/brotli) does not appear to be active. Enabling compression can reduce page transfer sizes by up to 70%.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * Makes an HTTP request to the homepage with an Accept-Encoding header and
	 * inspects the Content-Encoding response header for gzip or br.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when compression is absent, null when healthy.
	 */
	public static function check() {
		$home_url = home_url( '/' );
		$result = Diagnostic_Request_Helper::get_result( $home_url, array(
			'timeout'    => 7,
			'user-agent' => 'Shadow by Christopher Ross-Diagnostic/1.0',
			'headers'    => array(
				'Accept-Encoding' => 'gzip, deflate, br',
			),
		) );

		if ( empty( $result['success'] ) || empty( $result['response'] ) || ! is_array( $result['response'] ) ) {
			return null; // Cannot test; skip to avoid false positives.
		}

		$response = $result['response'];
		$encoding = wp_remote_retrieve_header( $response, 'content-encoding' );

		if ( ! empty( $encoding ) ) {
			$lower = strtolower( $encoding );
			if ( false !== strpos( $lower, 'gzip' ) || false !== strpos( $lower, 'br' ) || false !== strpos( $lower, 'deflate' ) ) {
				return null; // Compression is active.
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'HTTP compression (gzip or Brotli) does not appear to be enabled on this server. Compression typically reduces HTML, CSS, and JavaScript transfer sizes by 60–80%, significantly improving page load times for visitors. Enable mod_deflate or mod_brotli on Apache, or the ngx_http_gzip_module on Nginx. Caching plugins such as W3 Total Cache and LiteSpeed Cache can also enable this.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array(
				'content_encoding' => $encoding ?: 'none',
				'checked_url'      => $home_url,
			),
		);
	}
}
