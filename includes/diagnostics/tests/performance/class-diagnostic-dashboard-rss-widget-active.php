<?php
/**
 * Dashboard RSS Widget Active Diagnostic
 *
 * Checks whether the WordPress News RSS dashboard widgets are active.
 * WordPress registers these widgets by default and they fire an outbound
 * HTTP fetch to WordPress.org on first load and every 12 hours thereafter,
 * adding an external dependency to every dashboard page load.
 *
 * @package    Christopher Ross Shadow
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
 * Diagnostic_Dashboard_Rss_Widget_Active Class
 *
 * @since 0.6095
 */
class Diagnostic_Dashboard_Rss_Widget_Active extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'dashboard-rss-widget-active';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Dashboard RSS Widgets Active';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress News and Plugins dashboards RSS widgets are present. These widgets make outbound HTTP requests to WordPress.org to fetch RSS feeds, adding an external dependency to every dashboard page load.';

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
	 * WordPress core dashboard widget IDs that trigger RSS fetches.
	 *
	 * @var string[]
	 */
	private const RSS_WIDGET_IDS = array(
		'dashboard_primary',   // WordPress News feed (WordPress.org blog).
		'dashboard_secondary', // WordPress Events & News (old — removed in WP 5.3 for dashboard_primary).
	);

	/**
	 * Known plugins or must-use files that explicitly remove these widgets.
	 *
	 * @var string[]
	 */
	private const REMOVAL_PLUGINS = array(
		'disable-admin-notices/disable-admin-notices.php',
		'clean-my-wp/clean-my-wp.php',
		'wps-cleaner/wps-cleaner.php',
		'wp-dashboard-cleaner/wp-dashboard-cleaner.php',
		'disable-dashboard-widgets/disable-dashboard-widgets.php',
		'dashboard-widgets-suite/dashboard-widgets-suite.php',
	);

	/**
	 * Run the diagnostic check.
	 *
	 * WordPress registers dashboard_primary (WordPress News) by default via
	 * wp_dashboard_setup(). The widget calls fetch_feed() which is cached in a
	 * transient for HOUR_IN_SECONDS * 12. On cache miss it makes an outbound
	 * HTTP GET to https://wordpress.org/news/feed/. On hosting that restricts
	 * outbound connections, this can block or slow down every dashboard load.
	 * Even on unrestricted hosting it adds an external dependency to the backend.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when RSS widgets are active, null when removed.
	 */
	public static function check(): ?array {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		// Check for known dashboard-cleanup plugins.
		foreach ( self::REMOVAL_PLUGINS as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null;
			}
		}

		// WP Rocket: check if it removes dashboard widgets.
		$rocket = get_option( 'wp_rocket_settings', array() );
		if ( is_array( $rocket ) && ! empty( $rocket['dashboard_widget'] ) ) {
			return null;
		}

		// Perfmatters: check if admin dashboard cleanup is enabled.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_dashwidgets'] ) ) {
			return null;
		}

		// Check if the RSS feed transient exists (widget has been rendered recently).
		// The transient key WordPress uses is based on the feed URL MD5.
		// dashboard_primary defaults to: https://wordpress.org/news/feed/
		$feed_url    = 'https://wordpress.org/news/feed/';
		$cache_key   = 'feed_' . md5( $feed_url );
		$feed_cached = get_transient( $cache_key );

		// If the feed is currently cached, the widgets are definitely active.
		// If not cached, WordPress will attempt a fetch on next dashboard load.
		$active_widgets   = self::RSS_WIDGET_IDS;
		$feed_status      = false !== $feed_cached ? 'cached (12 h TTL)' : 'will fetch on next dashboard load';

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __(
				'The WordPress News RSS dashboard widget is registered and active. This widget fetches an RSS feed from WordPress.org on every cache miss (approximately every 12 hours per site). On hosting environments with restricted outbound connections, this can block dashboard loading. Even on unrestricted hosting, it adds an external dependency and DNS lookup to admin page rendering — silently, for every administrator who views the Dashboard.',
				'thisismyurl-shadow'
			),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'active_widget_ids' => $active_widgets,
				'feed_url'          => $feed_url,
				'feed_cache_status' => $feed_status,
				'note'              => __(
					'Christopher Ross Shadow can remove these widgets automatically. Alternatively, add remove_meta_box(\'dashboard_primary\', \'dashboard\', \'side\') in your theme\'s functions.php or a functionality plugin.',
					'thisismyurl-shadow'
				),
			),
		);
	}
}
