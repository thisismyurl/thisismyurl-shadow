<?php
/**
 * Plugin Auto Updates Diagnostic
 *
 * Checks whether automatic plugin updates are enabled to ensure security
 * patches are applied promptly without requiring manual action.
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
 * Diagnostic_Plugin_Auto_Updates Class
 *
 * Verifies that WordPress automatic background updates for plugins are enabled,
 * flagging sites where auto-updates have been disabled or were never turned on.
 *
 * @since 0.6095
 */
class Diagnostic_Plugin_Auto_Updates extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'plugin-auto-updates';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Plugin Auto Updates';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether automatic plugin updates are enabled to ensure security patches are applied promptly without requiring manual action.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Delegates to the WP_Settings helper to check whether automatic plugin
	 * updates are enabled across the site, returning a low-severity finding
	 * when they are not.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when auto-updates are disabled, null when healthy.
	 */
	public static function check() {
		if ( WP_Settings::is_plugin_auto_update_enabled() ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Plugin auto-updates are not enabled. Sites relying on manual review cycles frequently fall behind on security patches. Enable plugin auto-updates or document and enforce a regular manual update schedule.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 30,
			'details'      => array(
				'plugin_auto_updates_enabled' => false,
				'theme_auto_updates_enabled'  => WP_Settings::is_theme_auto_update_enabled(),
			),
		);
	}
}
