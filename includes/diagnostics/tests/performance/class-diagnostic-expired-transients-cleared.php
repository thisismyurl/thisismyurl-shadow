<?php
/**
 * Expired Transients Cleared Diagnostic
 *
 * Counts expired transient timeout entries in the options table to detect
 * database bloat from uncleaned transients when no external object cache is used.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Server_Environment_Helper as Server_Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expired Transients Cleared Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Expired_Transients_Cleared extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'expired-transients-cleared';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Expired Transients Cleared';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'A large number of expired transients are accumulating in the database. This bloats the options table and slows autoload queries.';

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
	 * Skips when an external object cache is active. Otherwise counts expired
	 * _transient_timeout_ rows and flags when the total exceeds the threshold.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when expired transients exceed threshold, null when healthy.
	 */
	public static function check() {
		$count = Server_Env::get_expired_transient_count();

		// Fewer than 50 expired transients is not worth flagging.
		if ( $count < 50 ) {
			return null;
		}

		$severity     = $count > 500 ? 'medium' : 'low';
		$threat_level = $count > 500 ? 35 : 20;

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of expired transients */
				__( '%d expired transients are still stored in your wp_options table. Expired transients have not been cleaned up because WordPress only removes a transient when its key is specifically requested. They accumulate over time, bloating your database and slowing down queries on the options table.', 'thisismyurl-shadow' ),
				$count
			),
			'severity'     => $severity,
			'threat_level' => $threat_level,
			'details'      => array(
				'expired_transient_count' => $count,
				'note'                    => __( 'Use WP-Optimize, WP Sweep, or a similar database optimisation plugin to clear expired transients.', 'thisismyurl-shadow' ),
				'explanation_sections'    => array(
					'summary' => sprintf(
						/* translators: %d: expired transient count */
						__( 'Christopher Ross Shadow found %d expired transient timeout records still present in wp_options. Transients are intended to be temporary, but WordPress only removes many of them lazily when requested, so expired entries can accumulate for long periods on active sites.', 'thisismyurl-shadow' ),
						$count
					),
					'how_wp_shadow_tested' => __( 'Christopher Ross Shadow queried the environment helper for expired transient timeout counts and evaluated the result against a practical threshold. The check is skipped or naturally low-impact when an external object cache is in place, because transient storage and expiration behavior differ in that configuration.', 'thisismyurl-shadow' ),
					'why_it_matters' => __( 'Large volumes of expired transients bloat wp_options and increase the amount of stale data your database has to store and scan. Over time this can hurt query efficiency, increase backup size, and make optimization jobs heavier. It is usually a maintenance debt issue rather than an urgent outage risk.', 'thisismyurl-shadow' ),
					'how_to_fix_it' => __( 'Run a safe transient cleanup using a trusted maintenance plugin or WP-CLI command, then monitor whether the count quickly regrows. If it does, identify plugins creating excessive short-lived keys and adjust their cache behavior where possible. Re-run this diagnostic after cleanup to confirm the count returns below threshold.', 'thisismyurl-shadow' ),
				),
			),
		);
	}
}
