<?php
/**
 * Page Cache Enabled Diagnostic
 *
 * Checks if page caching is enabled and working properly.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page Cache Enabled Diagnostic Class
 *
 * Verifies page caching is active. Page caching is the single most
 * impactful performance optimization (50-90% reduction).
 *
 * @since 0.6095
 */
class Diagnostic_Page_Cache_Enabled extends Diagnostic_Base {

	/**
	 * The diagnostic slug
	 *
	 * @var string
	 */
	protected static $slug = 'page-cache-enabled';

	/**
	 * The diagnostic title
	 *
	 * @var string
	 */
	protected static $title = 'Page Cache Enabled';

	/**
	 * The diagnostic description
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a full-page caching solution is active on the site. Page caching stores pre-built HTML responses and serves them without executing PHP or querying the database on each visit, typically reducing page generation time by 50–90% and enabling the site to handle traffic spikes gracefully.';

	/**
	 * The family this diagnostic belongs to
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
	 * Checks for common cache plugins and cache headers.
	 *
	 * @since 0.6095
	 * @return array|null Finding array if issue found, null otherwise.
	 */
	public static function check() {
		// List of known cache plugins
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
			'swift-performance-lite/performance.php',
			'hummingbird-performance/wp-hummingbird.php',
			'sg-cachepress/sg-cachepress.php',
		);

		// Get active plugins
		$active_plugins = get_option( 'active_plugins', array() );

		// Check if any cache plugin is active
		$cache_plugin_active = false;
		$active_cache_plugin = '';

		foreach ( $cache_plugins as $plugin ) {
			if ( in_array( $plugin, $active_plugins, true ) ) {
				$cache_plugin_active = true;
				$active_cache_plugin = $plugin;
				break;
			}
		}

		// Check for server-level caching
		$server_cache_detected = false;

		// Check for common cache constants
		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			$cache_plugin_active = true;
		}

		// Check headers for cache indicators (if not in admin)
		if ( ! is_admin() && ! defined( 'DOING_AJAX' ) ) {
			// Check for X-Cache, X-Cache-Status, X-Proxy-Cache headers
			// These would be set by server-level caching
			$headers = headers_list();
			foreach ( $headers as $header ) {
				if ( stripos( $header, 'X-Cache' ) !== false ||
				     stripos( $header, 'X-Proxy-Cache' ) !== false ||
				     stripos( $header, 'CF-Cache-Status' ) !== false ) {
					$server_cache_detected = true;
					break;
				}
			}
		}

		// If no caching detected, return finding
		if ( ! $cache_plugin_active && ! $server_cache_detected ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'Page caching is not enabled. Page caching is the single most impactful performance optimization, typically reducing server load by 50-90% and improving response times significantly. Install and configure a caching plugin like WP Super Cache, W3 Total Cache, or WP Rocket.', 'thisismyurl-shadow' ),
				'severity'     => 'critical',
				'threat_level' => 95,
				'details'      => array(
					'explanation_sections' => array(
						'summary' => __( 'Shadow by Christopher Ross could not detect full-page caching from active plugin signatures, runtime constants, or cache response headers. Without full-page caching, WordPress has to execute PHP and database queries for most requests, which raises response times and reduces capacity under traffic spikes.', 'thisismyurl-shadow' ),
						'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross inspected active plugin identifiers for common caching engines, checked the WP_CACHE constant, and looked for standard cache headers such as X-Cache and CF-Cache-Status during front-end execution contexts. The result is considered high confidence when all indicators are absent.', 'thisismyurl-shadow' ),
						'why_it_matters' => __( 'Page caching is typically the highest-impact performance control on WordPress. Without it, your server repeats expensive page generation work for every anonymous visitor, increasing CPU load, response latency, and the risk of slowdown during marketing campaigns, crawler bursts, or normal growth.', 'thisismyurl-shadow' ),
						'how_to_fix_it' => __( 'Enable a reputable page cache plugin or host-level cache layer, then verify cache bypass rules for logged-in users and dynamic checkout/account pages. Warm cache for key routes after deployment. Re-run this diagnostic and confirm at least one cache signal is now detected consistently.', 'thisismyurl-shadow' ),
					),
				),
				'meta'         => array(
					'cache_plugin_active'  => false,
					'server_cache_detected' => false,
					'wp_cache_constant'    => defined( 'WP_CACHE' ) && WP_CACHE,
					'active_cache_plugin'  => $active_cache_plugin,
					'performance_impact'   => '50-90% improvement possible',
					'recommended_plugins'  => array(
						'WP Super Cache',
						'W3 Total Cache',
						'WP Rocket',
						'LiteSpeed Cache',
					),
				),
			);
		}

		// Caching appears to be enabled
		return null;
	}
}
