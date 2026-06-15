<?php
/**
 * Cron Health Diagnostic
 *
 * Inspects the WordPress cron array to detect events that are significantly
 * overdue (more than 30 minutes late), and flags when the total number of
 * registered scheduled events exceeds a healthy threshold (cron bloat).
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
 * Diagnostic_Cron_Health Class
 *
 * Iterates the _get_cron_array() output to count overdue events (> 30 min)
 * and total registered hooks. Returns a medium finding for overdue events or
 * a low finding for excessive queue size, or null when healthy.
 *
 * @since 0.6095
 */
class Diagnostic_Cron_Health extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'cron-health';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Cron Health';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress scheduled events are running on time and that the cron queue has not grown excessively large due to scheduling failures or plugin cron bloat.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'workflows';

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
	 * Reads the WordPress cron array, counts total registered hooks and how many
	 * are more than 30 minutes overdue. Returns a medium-severity finding for
	 * overdue events, a low-severity finding for cron queue bloat (> 100 hooks),
	 * or null when the cron queue is healthy.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when cron issues are detected, null when healthy.
	 */
	public static function check() {
		$cron_events = _get_cron_array();
		if ( empty( $cron_events ) || ! is_array( $cron_events ) ) {
			return null;
		}

		$now          = time();
		$total_hooks  = 0;
		$overdue_30m  = 0;

		foreach ( $cron_events as $timestamp => $hooks ) {
			if ( ! is_numeric( $timestamp ) || ! is_array( $hooks ) ) {
				continue;
			}
			$total_hooks += count( $hooks );
			if ( ( $now - (int) $timestamp ) > 1800 ) {
				$overdue_30m += count( $hooks );
			}
		}

		// Flag if there are events more than 30 minutes overdue (threshold higher
		// than external-cron which uses 15 min, to surface only persistently missed jobs).
		if ( $overdue_30m > 0 ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %d: number of overdue events */
					_n(
						'%d scheduled event is more than 30 minutes overdue. This indicates WP-Cron is not running reliably — either because the site receives insufficient traffic to trigger it, or because a previous cron run crashed leaving a stale lock.',
						'%d scheduled events are more than 30 minutes overdue. This indicates WP-Cron is not running reliably — either because the site receives insufficient traffic to trigger it, or because a previous cron run crashed leaving a stale lock.',
						$overdue_30m,
						'thisismyurl-shadow'
					),
					$overdue_30m
				),
				'severity'     => 'medium',
				'threat_level' => 40,
				'details'      => array(
					'total_scheduled' => $total_hooks,
					'overdue_30m'     => $overdue_30m,
					'explanation_sections' => array(
						'summary' => sprintf(
							/* translators: 1: overdue events count, 2: total scheduled events count */
							__( 'Shadow by Christopher Ross found %1$d overdue scheduled events out of %2$d total registered cron hooks. This means background jobs are not executing on schedule, which can delay order processing, email queues, backups, and cleanup tasks that your site depends on to stay healthy.', 'thisismyurl-shadow' ),
							$overdue_30m,
							$total_hooks
						),
						'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross inspected the WordPress cron array in memory and compared each scheduled timestamp against current server time. Any hooks delayed by more than 30 minutes were classified as overdue. This threshold intentionally ignores minor drift and highlights persistent scheduling failures.', 'thisismyurl-shadow' ),
						'why_it_matters' => __( 'When cron jobs miss schedule windows, small maintenance tasks compound into larger reliability problems. Expired data is left behind, queued jobs pile up, and business workflows become inconsistent. On busy sites this can create operational instability; on low-traffic sites it can silently prevent critical tasks from running at all.', 'thisismyurl-shadow' ),
						'how_to_fix_it' => __( 'Confirm wp-cron.php is reachable and not blocked by firewall, basic auth, or caching rules. If your site does not have predictable traffic, offload cron to a real server scheduler and set DISABLE_WP_CRON to true. After changes, run this diagnostic multiple times over the next hour to confirm overdue counts stop growing.', 'thisismyurl-shadow' ),
					),
				),
			);
		}

		// Flag if there is an unusually large number of scheduled events (cron bloat).
		if ( $total_hooks > 100 ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %d: number of scheduled events */
					__( '%d scheduled events are registered. An excessive number of cron events indicates a plugin is creating recurring jobs without cleaning up, which slows the site because the full cron array is loaded on every request.', 'thisismyurl-shadow' ),
					$total_hooks
				),
				'severity'     => 'low',
				'threat_level' => 20,
				'details'      => array(
					'total_scheduled' => $total_hooks,
					'overdue_30m'     => $overdue_30m,
					'explanation_sections' => array(
						'summary' => sprintf(
							/* translators: %d: total scheduled events */
							__( 'Shadow by Christopher Ross counted %d scheduled cron hooks, which is higher than a healthy baseline for most WordPress sites. A large cron queue usually points to plugins repeatedly scheduling jobs without adequate cleanup, or long-running jobs that are not completing normally.', 'thisismyurl-shadow' ),
							$total_hooks
						),
						'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross enumerated the active cron array and counted each registered hook instance. This is a direct runtime measurement of queue size, not an estimate. The check surfaces queue bloat even when tasks are not currently overdue, so you can correct scheduler pressure before it becomes a reliability incident.', 'thisismyurl-shadow' ),
						'why_it_matters' => __( 'An oversized cron queue increases processing overhead and can make scheduled maintenance more bursty and unpredictable. Over time, this can contribute to lock contention, duplicate task execution, and slower request handling when cron is triggered during page loads.', 'thisismyurl-shadow' ),
						'how_to_fix_it' => __( 'Identify plugins with aggressive scheduling patterns, reduce unnecessary intervals, and ensure deactivation hooks unschedule recurring events. Remove abandoned plugin data where appropriate. Keep WP-Cron execution reliable with a real system cron, then re-run this check and confirm the total event count trends downward.', 'thisismyurl-shadow' ),
					),
				),
			);
		}

		return null;
	}
}
