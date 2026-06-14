<?php
/**
 * WP Cron Traffic Dependence Diagnostic
 *
 * Checks whether WP-Cron is firing inline during page loads (traffic-
 * dependent). This adds latency for visitors and causes scheduled tasks to be
 * missed on low-traffic sites. Flags medium severity for inline mode and low
 * severity for ALTERNATE_WP_CRON mode.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Cron_Traffic_Dependence Class
 *
 * Checks the DISABLE_WP_CRON and ALTERNATE_WP_CRON constants via the Server
 * environment helper. Returns null when DISABLE_WP_CRON is true. Returns a
 * medium or low finding otherwise depending on the cron mode.
 *
 * @since 0.6095
 */
class Diagnostic_Cron_Traffic_Dependence extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'cron-traffic-dependence';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'WP Cron Traffic Dependence';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WP-Cron fires inline during page loads (traffic-dependent), which adds latency for visitors and causes tasks to be missed on low-traffic sites.';

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
	 * Checks whether DISABLE_WP_CRON is set (indicating a real system cron is in
	 * use). Returns null when cron is offloaded. Otherwise checks for
	 * ALTERNATE_WP_CRON mode and returns a low finding for that, or a medium
	 * finding for standard inline WP-Cron.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when traffic-dependent cron is detected, null when healthy.
	 */
	public static function check() {
		// If cron is offloaded to a system scheduler, there is no traffic dependence.
		if ( Server_Env::is_wp_cron_disabled() ) {
			return null;
		}

		// ALTERNATE_WP_CRON fires cron via a redirect after the response is sent
		// — less disruptive but still requires a visitor to trigger it.
		$alternate = defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => $alternate
				? __( 'WP-Cron is running in alternate mode (ALTERNATE_WP_CRON is true). Scheduled tasks fire via a background HTTP redirect after the visitor response is sent, which reduces page-load impact but still requires visitor traffic to trigger execution. Replace with a real system cron job for reliable scheduling.', 'thisismyurl-shadow' )
				: __( 'WP-Cron is running inline: scheduled tasks execute during a visitor\'s page load request. This adds latency for that visitor and means tasks never run when there is no traffic. Replace with a real system cron job for reliable, traffic-independent scheduling.', 'thisismyurl-shadow' ),
			'severity'     => $alternate ? 'low' : 'medium',
			'threat_level' => $alternate ? 15 : 30,
			'details'      => array(
				'disable_wp_cron'   => false,
				'alternate_wp_cron' => $alternate,
				'fix'               => "Add define( 'DISABLE_WP_CRON', true ); to wp-config.php and add a system cron: */5 * * * * curl -s https://yoursite.com/wp-cron.php",
				'explanation_sections' => array(
					'summary' => $alternate
						? __( 'Shadow by Christopher Ross detected ALTERNATE_WP_CRON mode. This avoids some inline delays, but your scheduled tasks still depend on incoming traffic, so low-traffic periods can cause deferred maintenance and delayed automations.', 'thisismyurl-shadow' )
						: __( 'Shadow by Christopher Ross detected standard traffic-triggered WP-Cron execution. Scheduled tasks run during visitor requests, which can add latency and create missed execution windows whenever site traffic is inconsistent.', 'thisismyurl-shadow' ),
					'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross evaluated DISABLE_WP_CRON and ALTERNATE_WP_CRON flags using the runtime server environment. If DISABLE_WP_CRON is false, cron remains traffic-dependent and this check reports a finding with severity based on whether alternate mode is enabled.', 'thisismyurl-shadow' ),
					'why_it_matters' => __( 'Traffic-coupled scheduling is less predictable than a system scheduler. Maintenance, queue processing, and integration tasks can run late or in bursts, which increases operational variance and can produce confusing intermittent failures that are hard to reproduce.', 'thisismyurl-shadow' ),
					'how_to_fix_it' => __( 'Configure a real server cron job to call wp-cron.php on a fixed interval, then set DISABLE_WP_CRON to true so WordPress does not execute cron inline. Keep the interval aligned with your workload (often every 5 minutes), then run this check again to verify traffic dependence is removed.', 'thisismyurl-shadow' ),
				),
			),
		);
	}
}
