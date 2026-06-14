<?php
/**
 * Application Health Checks Registered Diagnostic
 *
 * Checks whether custom Site Health tests are registered via the
 * site_status_tests filter or a dedicated monitoring plugin is active.
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
 * Diagnostic_Application_Health_Checks_Registered Class
 *
 * @since 0.6095
 */
class Diagnostic_Application_Health_Checks_Registered extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'application-health-checks-registered';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Application Health Checks Registered';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether custom Site Health tests are registered or a monitoring plugin is active, ensuring application-specific issues surface in the WordPress Site Health screen.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'monitoring';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Applies the site_status_tests filter to count registered custom tests.
	 * Falls back to checking whether known monitoring plugins are active.
	 *
	 * @since  0.6095
	 * @return array|null Return finding array when issue exists, null when healthy.
	 */
	public static function check() {
		// WordPress Site Health lets plugins register custom tests via the site_status_tests filter.
		// A site with no custom tests registered may be missing application-specific health monitoring.
		$tests = apply_filters( 'site_status_tests', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reading a WordPress core filter's value for diagnostics, not defining a plugin hook.

		$custom_direct = isset( $tests['direct'] ) ? count( $tests['direct'] ) : 0;
		$custom_async  = isset( $tests['async'] ) ? count( $tests['async'] ) : 0;
		$total_custom  = $custom_direct + $custom_async;

		// WordPress core itself registers a set of built-in tests.
		// If only core tests exist (the filter was never added to by plugins/theme) we can't know
		// without a baseline count. Instead, check that at least one plugin or theme is registering
		// custom health checks, which indicates monitoring awareness.
		// The base WordPress install registers ~12-15 core tests. If total < 5 it likely means
		// the filter has never been augmented.
		if ( $total_custom >= 5 ) {
			return null;
		}

		// Check if any monitoring or APM plugin is active.
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$monitoring_plugins = array(
			'query-monitor/query-monitor.php',
			'new-relic-rpm/newrelic.php',
			'wp-sentry-integration/wp-sentry.php',
			'heartbeat-control/heartbeat-control.php',
			'wp-crontrol/wp-crontrol.php',
			'health-check/health-check.php',
		);

		foreach ( $monitoring_plugins as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No custom Site Health checks have been registered via the site_status_tests filter and no dedicated monitoring plugin is active. Custom health checks allow plugins, themes, and custom code to surface application-specific problems in the WordPress Site Health screen. Register custom checks for business-critical integrations, or install a monitoring plugin such as Query Monitor or Health Check & Troubleshooting.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'custom_direct_tests' => $custom_direct,
				'custom_async_tests'  => $custom_async,
			),
		);
	}
}
