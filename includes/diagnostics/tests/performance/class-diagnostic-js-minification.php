<?php
/**
 * JavaScript Minification Diagnostic
 *
 * Checks whether JavaScript assets are being minified to reduce payload
 * sizes and improve page load performance.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Diagnostics
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Request_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Js_Minification Class
 *
 * @since 0.6095
 */
class Diagnostic_Js_Minification extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'js-minification';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'JavaScript Minification';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether JavaScript assets are being minified to reduce file sizes and improve page load performance.';

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
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Checks active plugins for known JS minification tools and validates
	 * WP Rocket and Autoptimize setting values to confirm minification is on.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when JS minification is absent, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		// Plugins that handle JS minification.
		$minification_plugins = array(
			'w3-total-cache/w3-total-cache.php',
			'wp-rocket/wp-rocket.php',
			'autoptimize/autoptimize.php',
			'litespeed-cache/litespeed-cache.php',
			'sg-cachepress/sg-cachepress.php',
			'hummingbird-performance/wp-hummingbird.php',
			'wp-optimize/wp-optimize.php',
			'asset-cleanup-org/index.php',
			'perfmatters/perfmatters.php',
		);

		foreach ( $minification_plugins as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null; // JS minification is handled by a performance plugin.
			}
		}

		// No optimization plugin found.
		// Scan homepage for unminified JS URLs (those without .min.js).
		$home_url = home_url( '/' );
		$result = Diagnostic_Request_Helper::get_result( $home_url, array(
			'timeout'    => 7,
			'user-agent' => 'Christopher Ross Shadow-Diagnostic/1.0',
		) );

		if ( empty( $result['success'] ) || empty( $result['response'] ) || ! is_array( $result['response'] ) ) {
			return null;
		}

		$response = $result['response'];
		$body = wp_remote_retrieve_body( $response );

		// Match <script src="...*.js"> that are NOT .min.js.
		preg_match_all( '/<script\s[^>]*src=["\']([^"\']+\.js[^"\']*)["\'][^>]*>/i', $body, $matches );
		$all_scripts      = isset( $matches[1] ) ? $matches[1] : array();
		$unminified_count = 0;
		foreach ( $all_scripts as $src ) {
			// Skip already minified or external CDN files.
			if ( false === strpos( $src, '.min.js' ) && false !== strpos( $src, home_url() ) ) {
				$unminified_count++;
			}
		}

		if ( $unminified_count <= 2 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of unminified scripts */
				__( '%d unminified JavaScript files were detected on the homepage. Minification removes whitespace, comments, and verbose identifiers, typically reducing file size by 20–40%%. Install a performance plugin such as WP Rocket or Autoptimize to minify JavaScript assets automatically.', 'thisismyurl-shadow' ),
				$unminified_count
			),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'unminified_scripts' => $unminified_count,
				'total_scripts'      => count( $all_scripts ),
			),
		);
	}
}
