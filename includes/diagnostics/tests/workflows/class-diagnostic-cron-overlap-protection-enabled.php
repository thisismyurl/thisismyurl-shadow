<?php
/**
 * Cron Overlap Protection Enabled Diagnostic
 *
 * Checks for a stale WP-Cron lock (the doing_cron option) that indicates a
 * crashed cron process is blocking all future scheduled task execution. A lock
 * older than 10 minutes is treated as stale.
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
 * Diagnostic_Cron_Overlap_Protection_Enabled Class
 *
 * Reads the doing_cron option and computes its age. Returns null when no lock
 * exists or the lock is recent (≤ 600 s). Returns a high-severity finding when
 * the lock is stale, indicating a crashed cron run.
 *
 * @since 0.6095
 */
class Diagnostic_Cron_Overlap_Protection_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'cron-overlap-protection-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Cron Overlap Protection Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks for a stale WP-Cron lock that indicates a crashed cron process is blocking all future scheduled task execution.';

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
	 * Reads the doing_cron option (WordPress pseudo-mutex). Returns null when no
	 * lock exists, or when the lock is less than 600 seconds old (cron is still
	 * running). Returns a high-severity finding when the lock age exceeds 600
	 * seconds, indicating the previous cron run crashed without releasing it.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when a stale lock is detected, null when healthy.
	 */
	public static function check() {
		// WordPress uses a doing_cron option/transient as a lock to prevent concurrent execution.
		// A stale lock (older than 10 minutes) means a previous cron run crashed without releasing
		// the lock, which blocks all future cron execution until manually cleared.
		$lock = get_option( 'doing_cron', 0 );
		if ( empty( $lock ) ) {
			return null;
		}

		$age = microtime( true ) - (float) $lock;
		if ( $age <= 600 ) {
			// Lock is recent — cron is actively running or just finished. Pass.
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'The WP-Cron lock (doing_cron) is stale — it has been set for more than 10 minutes without being released. This usually means a previous cron execution crashed or timed out. While the lock persists, WordPress will not spawn new cron runs, causing all scheduled tasks to stall. The lock should be cleared and the failing cron job investigated.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 60,
			'details'      => array(
				'lock_age_seconds' => (int) $age,
				'lock_timestamp'   => $lock,
				'fix'              => __( 'Run: delete_option( \'doing_cron\' ); via WP-CLI or a plugin — then review PHP error logs for the failing job.', 'thisismyurl-shadow' ),
			),
		);
	}
}
