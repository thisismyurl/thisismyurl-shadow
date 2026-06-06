<?php
/**
 * Treatment: Disable Jetpack Stats Admin Bar Widget
 *
 * When Jetpack's Stats module is active, it displays a real-time visitor
 * sparkline chart in the WordPress admin bar on every admin page. Rendering
 * the chart requires an outbound proxy request from the server back into the
 * Jetpack CDN on every admin page load, adding measurable latency regardless
 * of whether the admin user cares about the live stats at that moment.
 *
 * This treatment directly updates the Jetpack `stats_options` option to set
 * `admin_bar` to `false`, disabling the sparkline widget. The full Stats
 * dashboard (Jetpack → Stats) remains fully functional; only the admin bar
 * overlay is hidden.
 *
 * No bootstrap flag is required — the change is written directly to the
 * database and Jetpack honours it immediately on the next admin page load.
 *
 * Risk level: safe — single option key updated, Jetpack-reversible.
 *
 * Undo: removes the `admin_bar` key from `stats_options`, restoring Jetpack's
 * own default (visible).
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hides the Jetpack Stats sparkline from the WordPress admin bar.
 */
class Treatment_Jetpack_Stats_Admin_Bar extends Treatment_Base {

	/** @var string */
	protected static $slug = 'jetpack-stats-admin-bar';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Set stats_options.admin_bar to false so Jetpack skips rendering the widget.
	 *
	 * @return array
	 */
	public static function apply(): array {
		if ( ! self::is_jetpack_stats_active() ) {
			return array(
				'success' => false,
				'message' => __( 'Jetpack Stats module is not currently active. No change was made.', 'thisismyurl-shadow' ),
			);
		}

		$options              = self::get_stats_options();
		$options['admin_bar'] = false;
		update_option( 'stats_options', $options, false );

		return array(
			'success' => true,
			'message' => __( 'Jetpack Stats admin bar sparkline disabled. The full Stats dashboard (Jetpack → Stats) remains fully functional. Takes effect on the next admin page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the admin_bar override, restoring Jetpack's default (visible).
	 *
	 * @return array
	 */
	public static function undo(): array {
		$options = self::get_stats_options();

		if ( ! isset( $options['admin_bar'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'Jetpack Stats admin bar setting was not overridden by Christopher Ross Shadow. Nothing to restore.', 'thisismyurl-shadow' ),
			);
		}

		unset( $options['admin_bar'] );
		update_option( 'stats_options', $options, false );

		return array(
			'success' => true,
			'message' => __( 'Jetpack Stats admin bar sparkline restored to default (visible). Takes effect on the next admin page load.', 'thisismyurl-shadow' ),
		);
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Return true when Jetpack is active and the stats module is loaded.
	 *
	 * @return bool
	 */
	private static function is_jetpack_stats_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'jetpack/jetpack.php' ) ) {
			return false;
		}

		$active_modules = get_option( 'jetpack_active_modules', array() );

		return is_array( $active_modules ) && in_array( 'stats', $active_modules, true );
	}

	/**
	 * Fetch the current stats_options array, normalised to an array.
	 *
	 * @return array
	 */
	private static function get_stats_options(): array {
		$options = get_option( 'stats_options', array() );

		return is_array( $options ) ? $options : array();
	}
}
