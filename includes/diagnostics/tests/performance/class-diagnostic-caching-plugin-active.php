<?php
/**
 * Caching Plugin Active Diagnostic
 *
 * Checks whether a recognised page-caching plugin is active or the WP_CACHE
 * constant has been set, ensuring pages are served from cache.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caching Plugin Active Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Caching_Plugin_Active extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'caching-plugin-active';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Caching Plugin Active';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'No page-caching plugin detected. Caching dramatically reduces server load and improves response times for all visitors.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Detects WP_CACHE constant or a known page-caching plugin.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no caching is detected, null when healthy.
	 */
	public static function check() {
		// Check for the WP_CACHE constant (set by page caching plugins in wp-config.php).
		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			return null;
		}

		// Check for active page-caching plugin files.
		$cache_plugins = array(
			'wp-super-cache/wp-cache.php',
			'w3-total-cache/w3-total-cache.php',
			'wp-fastest-cache/wpFastestCache.php',
			'wp-rocket/wp-rocket.php',
			'cache-enabler/cache-enabler.php',
			'litespeed-cache/litespeed-cache.php',
			'comet-cache/comet-cache.php',
			'wp-optimize/wp-optimize.php',
			'breeze/breeze.php',
			'hummingbird-performance/wp-hummingbird.php',
			'sg-cachepress/sg-cachepress.php',
			'swift-performance-lite/performance.php',
			'autoptimize/autoptimize.php',
			'nitropack/nitropack.php',
			'siteground-optimizer/index.php',
		);

		$active_plugins = (array) get_option( 'active_plugins', array() );

		foreach ( $cache_plugins as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null;
			}
		}

		// Check for host-provided caching classes (e.g. Kinsta, Flywheel, WPEngine, SiteGround).
		$cache_classes = array(
			'WpeCommon',         // WP Engine
			'Kinsta\\Cache',      // Kinsta
			'KinstaCache',
			'Breeze',
			'LiteSpeed_Cache',
		);

		foreach ( $cache_classes as $class ) {
			if ( class_exists( $class, false ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No page caching solution was detected. Page caching is the single most impactful WordPress performance optimisation, typically reducing server load by 50–90% and cutting response times from hundreds of milliseconds to tens. Install a caching plugin such as WP Super Cache, LiteSpeed Cache, or WP Rocket.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 70,
			'details'      => array(
				'wp_cache_constant' => defined( 'WP_CACHE' ) ? WP_CACHE : false,
				'note'              => __( 'Recommended: WP Super Cache (free), LiteSpeed Cache (free), or WP Rocket (paid).', 'thisismyurl-shadow' ),
			),
		);
	}
}
