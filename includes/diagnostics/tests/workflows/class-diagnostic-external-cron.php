<?php
/**
 * External Cron Configured Diagnostic
 *
 * Checks whether WordPress scheduled events are running on time, detecting
 * signs that WP-Cron may be unreliable without a real external cron setup.
 * Flags when DISABLE_WP_CRON is false and events are more than 15 minutes
 * overdue.
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
 * Diagnostic_External_Cron Class
 *
 * Returns null when DISABLE_WP_CRON is true or when no cron events are
 * overdue. Iterates the WordPress cron array and returns a medium-severity
 * finding listing overdue hook names when any event is more than 15 min late.
 *
 * @since 0.6095
 */
class Diagnostic_External_Cron extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'external-cron';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'External Cron Configured';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress scheduled events are running on time, detecting signs that WP-Cron may be unreliable without a real external cron setup.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'workflows';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Returns null immediately when DISABLE_WP_CRON is true (system cron is in
	 * use). Iterates _get_cron_array(), building a list of hooks that are more
	 * than 15 minutes overdue. Returns a medium-severity finding with the overdue
	 * count and a sample of hook names, or null when all events are on time.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when overdue events are detected, null when healthy.
	 */
	public static function check() {
		// If DISABLE_WP_CRON is true a server/system cron is handling execution — pass.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return null;
		}

		// Check for significantly overdue scheduled events (> 15 min late).
		// This reveals whether WP-Cron is actually firing reliably under traffic.
		$cron_events = _get_cron_array();
		if ( empty( $cron_events ) || ! is_array( $cron_events ) ) {
			return null;
		}

		$now          = time();
		$overdue_jobs = array();

		foreach ( $cron_events as $timestamp => $hooks ) {
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}
			$late_by = $now - (int) $timestamp;
			if ( $late_by > 900 ) { // 15 minutes
				foreach ( array_keys( $hooks ) as $hook ) {
					$overdue_jobs[] = array(
						'hook'    => $hook,
						'late_by' => $late_by,
					);
				}
			}
		}

		if ( empty( $overdue_jobs ) ) {
			return null;
		}

		$count = count( $overdue_jobs );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of overdue cron events */
				_n(
					'%d scheduled event is overdue by more than 15 minutes. WP-Cron fires only when a page is loaded, so low-traffic sites may have tasks that never run on time. Configure a system cron job to call wp-cron.php on a fixed schedule.',
					'%d scheduled events are overdue by more than 15 minutes. WP-Cron fires only when a page is loaded, so low-traffic sites may have tasks that never run on time. Configure a system cron job to call wp-cron.php on a fixed schedule.',
					$count,
					'thisismyurl-shadow'
				),
				$count
			),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'overdue_count' => $count,
				'overdue_jobs'  => array_slice( $overdue_jobs, 0, 10 ),
				'explanation_sections' => array(
					'summary' => sprintf(
						/* translators: %d: overdue events count */
						__( 'Shadow by Christopher Ross detected %d cron events that are more than 15 minutes late while WordPress is still relying on traffic-triggered cron execution. This pattern usually appears on low-traffic sites, but it can also happen on busy sites when background task execution stalls or repeatedly times out.', 'thisismyurl-shadow' ),
						$count
					),
					'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross first checked whether DISABLE_WP_CRON is enabled. If not, it scanned the runtime cron schedule and measured delay for each event against current server time, flagging entries delayed by more than 15 minutes. It also captured sample overdue hook names to help identify the components involved.', 'thisismyurl-shadow' ),
					'why_it_matters' => __( 'Traffic-dependent cron is opportunistic rather than guaranteed. Jobs can run late or bunch together, which affects reliability of emails, subscription renewals, order workflows, cache warmups, and routine maintenance tasks. The longer events stay overdue, the more likely downstream systems drift out of sync.', 'thisismyurl-shadow' ),
					'how_to_fix_it' => __( 'Move scheduling to a real server cron that calls wp-cron.php at a fixed interval (for example every 5 minutes), then set DISABLE_WP_CRON to true in wp-config.php. Keep ALTERNATE_WP_CRON disabled unless required by hosting constraints. After deployment, re-run this check and confirm overdue counts remain near zero.', 'thisismyurl-shadow' ),
				),
			),
		);
	}
}
