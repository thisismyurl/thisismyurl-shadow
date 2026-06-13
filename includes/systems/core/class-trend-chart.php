<?php
/**
 * Trend Chart Component
 *
 * Displays 30-day health score trends and KPI improvements
 * using lightweight SVG-based visualization.
 *
 * @package ThisIsMyURL\Shadow
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generates trend charts for KPI visualization
 */
class Trend_Chart {

	/**
	 * Get score history for the past 30 days
	 *
	 * @return array Historical data points.
	 */
	public static function get_score_history() {
		$history = get_option( 'thisismyurl_shadow_score_history', array() );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Get last 30 days of data
		$cutoff_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$history     = array_filter(
			$history,
			function ( $item ) use ( $cutoff_date ) {
				return isset( $item['date'] ) && $item['date'] >= $cutoff_date;
			}
		);

		// Ensure today's data is included
		$today = gmdate( 'Y-m-d' );
		if ( ! self::history_has_date( $history, $today ) ) {
			$current_health = get_option( 'thisismyurl_shadow_health_status', array() );
			$score          = isset( $current_health['score'] ) ? (int) $current_health['score'] : 0;
			$history[]      = array(
				'date'  => $today,
				'score' => $score,
			);
		}

		return array_values( $history ); // Re-index
	}

	/**
	 * Check if history contains data for a specific date
	 *
	 * @param array  $history Historical data.
	 * @param string $date Date to check (Y-m-d format).
	 * @return bool
	 */
	private static function history_has_date( $history, $date ) {
		foreach ( $history as $item ) {
			if ( isset( $item['date'] ) && $item['date'] === $date ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Record a finding resolution (Phase 3: KPI Wiring)
	 *
	 * @param string $finding_id Finding identifier.
	 * @param string $status Status of resolution (fixed, ignored, delegated).
	 * @return void
	 */
	public static function record_finding_resolved( $finding_id, $status = 'fixed' ) {
		$resolutions = get_option( 'thisismyurl_shadow_finding_resolutions', array() );

		if ( ! is_array( $resolutions ) ) {
			$resolutions = array();
		}

		$resolutions[] = array(
			'finding_id' => $finding_id,
			'status'     => $status,
			'date'       => gmdate( 'Y-m-d H:i:s' ),
			'user_id'    => get_current_user_id(),
		);

		// Keep only the last 90 days to limit stored history.
		$cutoff_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
		$resolutions = array_filter(
			$resolutions,
			function ( $item ) use ( $cutoff_date ) {
				return isset( $item['date'] ) && substr( $item['date'], 0, 10 ) >= $cutoff_date;
			}
		);

		update_option( 'thisismyurl_shadow_finding_resolutions', $resolutions );
	}
}
