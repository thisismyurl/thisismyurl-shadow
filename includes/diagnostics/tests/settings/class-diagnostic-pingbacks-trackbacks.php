<?php
/**
 * Pingbacks and Trackbacks Configured Diagnostic
 *
 * Checks whether pingbacks and trackbacks are enabled by default on new posts.
 * These features expose the site to link-spam and DDoS amplification abuse and
 * should be disabled unless intentionally required.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_WP_Settings_Helper as WP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Pingbacks_Trackbacks Class
 *
 * Uses the WP_Settings helper to check whether default_ping_status and the
 * default_pingback_flag options have pings open. Returns a low-severity finding
 * when pings are still enabled by default.
 *
 * @since 0.6095
 */
class Diagnostic_Pingbacks_Trackbacks extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'pingbacks-trackbacks';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Pingbacks and Trackbacks';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether pingbacks and trackbacks are enabled by default on new posts, which exposes the site to link spam and DDoS amplification abuse.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Calls the WP_Settings helper to determine whether pings are open by
	 * default. Returns null when pings are disabled. Returns a low-severity
	 * finding including the raw option values when pings are still open.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when pings are open by default, null when healthy.
	 */
	public static function check() {
		if ( ! WP_Settings::are_pings_open_by_default() ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Pingbacks and trackbacks are enabled by default for new posts. These features are rarely needed on modern sites and are frequently abused for link spam and DDoS amplification attacks. Disable them unless you have a specific use case.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'default_ping_status' => get_option( 'default_ping_status', 'open' ),
				'default_pingback_flag' => (bool) get_option( 'default_pingback_flag', 1 ),
			),
		);
	}
}
