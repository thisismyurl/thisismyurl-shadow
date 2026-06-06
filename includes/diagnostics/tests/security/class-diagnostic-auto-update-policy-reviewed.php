<?php
/**
 * Auto Update Policy Diagnostic
 *
 * Checks whether a WordPress core auto-update policy has been explicitly
 * configured rather than left to default behaviour that may skip security patches.
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
 * Diagnostic_Auto_Update_Policy_Reviewed Class
 *
 * @since 0.6095
 */
class Diagnostic_Auto_Update_Policy_Reviewed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'auto-update-policy-reviewed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Auto Update Policy';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a WordPress core auto-update policy has been explicitly configured rather than left to the default behavior, which may leave security patches unapplied.';

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
	 * Verifies the WP_AUTO_UPDATE_CORE constant or equivalent option is defined,
	 * indicating an intentional core auto-update policy is in place.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no update policy is defined, null when healthy.
	 */
	public static function check() {
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			return null;
		}
		if ( null !== get_option( 'auto_update_core_enabled', null ) ) {
			return null;
		}

		$policy = WP_Settings::get_auto_update_core();

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No explicit WordPress core auto-update policy is set. Your site uses the WordPress default (minor updates only) without a conscious configuration decision. Define WP_AUTO_UPDATE_CORE in wp-config.php to lock in your intended update strategy.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'current_policy'   => $policy,
				'constant_defined' => false,
				'option_set'       => false,
			),
		);
	}
}
