<?php
/**
 * Jetpack Stats Admin Bar Diagnostic
 *
 * Detects whether the Jetpack Stats admin bar widget is active. This widget
 * renders a live traffic sparkline by fetching an image from WordPress.com
 * on every single admin page load, adding an outbound HTTP request to every
 * wp-admin visit.
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
 * Diagnostic_Jetpack_Stats_Admin_Bar Class
 *
 * @since 0.6095
 */
class Diagnostic_Jetpack_Stats_Admin_Bar extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'jetpack-stats-admin-bar';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Jetpack Stats Admin Bar Widget';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the Jetpack Stats admin bar widget is active. This widget loads a live traffic chart image from WordPress.com on every admin page load, adding an outbound HTTP dependency to every wp-admin request.';

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
	 * The Jetpack Stats bar sparkline fires when:
	 *   1. Jetpack is active.
	 *   2. The 'stats' module is enabled in Jetpack's module list.
	 *   3. The admin bar stats widget has not been disabled in Jetpack settings.
	 *
	 * The result of this outbound request is visible in browser dev-tools as a
	 * live-proxy request to /wp-admin/admin.php?page=stats&noheader&proxy&chart=
	 * admin-bar-hours-scale — present in the user's HTML sample.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when the stats bar widget is active, null when healthy.
	 */
	public static function check(): ?array {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		// Jetpack must be active.
		if ( ! in_array( 'jetpack/jetpack.php', $active_plugins, true ) ) {
			return null;
		}

		// The 'stats' module must be active.
		$jetpack_modules = (array) get_option( 'jetpack_active_modules', array() );
		if ( ! in_array( 'stats', $jetpack_modules, true ) ) {
			return null;
		}

		// Check whether the admin bar stats widget has been explicitly disabled.
		// Jetpack stores stats options under the 'stats_options' blog option.
		$stats_options = get_option( 'stats_options', array() );
		if ( is_array( $stats_options ) && isset( $stats_options['admin_bar'] ) ) {
			if ( ! $stats_options['admin_bar'] ) {
				return null; // User has already turned it off.
			}
		}

		// Default: admin bar widget is enabled when stats module is active.
		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __(
				'The Jetpack Stats admin bar widget is active. This widget fetches a live traffic sparkline image from WordPress.com on every wp-admin page load by making an outbound HTTP call through your server as a proxy. Every admin page visit — for every logged-in user — triggers this external request, adding latency to the page and consuming a PHP worker until the upstream response arrives. Consider disabling the admin bar stats widget in Jetpack → Settings → Traffic if real-time traffic data in the toolbar is not essential.',
				'thisismyurl-shadow'
			),
			'severity'     => 'low',
			'threat_level' => 18,
			'details'      => array(
				'jetpack_stats_module' => 'active',
				'proxy_pattern'        => 'admin.php?page=stats&noheader&proxy&chart=admin-bar-hours-scale',
				'note'                 => __(
					'Disable via Jetpack → Settings → Traffic → "Show stats in the toolbar" toggle, or via Jetpack → Debug → disable stats module if you use a separate analytics solution.',
					'thisismyurl-shadow'
				),
			),
		);
	}
}
