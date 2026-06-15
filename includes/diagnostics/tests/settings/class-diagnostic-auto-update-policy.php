<?php
/**
 * Auto-Update Policy Configured Diagnostic
 *
 * Checks whether WordPress core automatic updates have been completely disabled,
 * leaving the site without background security patching.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Auto_Update_Policy Class
 *
 * Reads WP_Settings::get_auto_update_core() and flags sites where core
 * automatic updates have been fully disabled.
 *
 * @since 0.6095
 */
class Diagnostic_Auto_Update_Policy extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'auto-update-policy';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Auto-Update Policy';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress core automatic updates have been completely disabled, leaving the site without background security patching.';

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
	 * Reads WP_Settings::get_auto_update_core() to determine whether core
	 * automatic updates are disabled. Returns null when any level of auto-update
	 * is active. When 'disabled' is returned, checks whether WP_AUTO_UPDATE_CORE
	 * constant is the cause and includes that context in the finding details.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when core auto-updates are disabled, null when healthy.
	 */
	public static function check() {
		$core_policy = WP_Settings::get_auto_update_core();

		// If core auto-updates are completely disabled, that is a risk.
		if ( 'disabled' !== $core_policy ) {
			return null;
		}

		$note = defined( 'WP_AUTO_UPDATE_CORE' ) && false === WP_AUTO_UPDATE_CORE
			? __( 'Core auto-updates are disabled via the WP_AUTO_UPDATE_CORE constant in wp-config.php.', 'thisismyurl-shadow' )
			: __( 'Core auto-updates are disabled via a WordPress option.', 'thisismyurl-shadow' );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress core automatic updates are fully disabled. Minor version updates often contain critical security patches. Consider enabling at least minor auto-updates to keep your site protected between manual update cycles.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 45,
			'details'      => array(
				'note'        => $note,
				'core_policy' => $core_policy,
			),
		);
	}
}
