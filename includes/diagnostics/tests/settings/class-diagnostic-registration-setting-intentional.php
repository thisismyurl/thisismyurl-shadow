<?php
/**
 * Registration Setting Diagnostic
 *
 * Checks whether open user registration is enabled and, if so, whether the
 * default role is low-privilege. Flags when registration is open with a
 * high-privilege default role, or as a medium finding when it is open with a
 * safe role but potentially unintentional.
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
 * Diagnostic_Registration_Setting_Intentional Class
 *
 * Uses the WP_Settings helper to read the users_can_register option and the
 * default_role option. Flags open registration with an elevated default role as
 * high severity, and open registration with a subscriber-level role as medium.
 *
 * @since 0.6095
 */
class Diagnostic_Registration_Setting_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'registration-setting-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Registration Setting';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether open user registration is intentional and restricted to a safe default role to prevent unauthorized account creation.';

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
	 * Reads the users_can_register and default_role WordPress options via the
	 * WP_Settings helper. Returns null when registration is closed. When open,
	 * returns high severity if the default role is elevated beyond subscriber or
	 * customer, otherwise returns medium severity.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when registration is open, null when healthy.
	 */
	public static function check() {
		if ( ! WP_Settings::is_registration_open() ) {
			return null;
		}

		$default_role = WP_Settings::get_default_user_role();
		$high_risk    = ! in_array( $default_role, array( 'subscriber', 'customer' ), true );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Open user registration is enabled on your site. Anyone can create an account. If this is intentional (e.g. membership site) ensure spam registrations are handled and the default role grants minimal privileges.', 'thisismyurl-shadow' ),
			'severity'     => $high_risk ? 'high' : 'medium',
			'threat_level' => $high_risk ? 70 : 40,
			'details'      => array(
				'registration_open' => true,
				'default_role'      => $default_role,
				'high_risk_role'    => $high_risk,
			),
		);
	}
}
