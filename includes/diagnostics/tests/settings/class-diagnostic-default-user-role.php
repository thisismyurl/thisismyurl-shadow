<?php
/**
 * Default User Role Diagnostic
 *
 * Checks whether the default role assigned to new user registrations is a safe,
 * low-privilege role such as Subscriber rather than an elevated role.
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
 * Diagnostic_Default_User_Role Class
 *
 * Reads the default_role WordPress option and flags when the assigned role
 * grants more than subscriber-level capabilities to new registrations.
 *
 * @since 0.6095
 */
class Diagnostic_Default_User_Role extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'default-user-role';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Default User Role';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the default role assigned to new user registrations is a safe, low-privilege role such as Subscriber rather than an elevated role.';

	/**
	 * Gauge family/category.
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
	 * Uses WP_Settings::get_default_user_role() to read the default_role option.
	 * Returns null when the role is 'subscriber'. For administrator, editor, or
	 * author roles returns a high-severity finding; for any other non-subscriber
	 * role returns a medium-severity finding.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when role is elevated, null when healthy.
	 */
	public static function check() {
		$role = WP_Settings::get_default_user_role();

		// 'subscriber' is the safe default — minimal capabilities.
		if ( 'subscriber' === $role ) {
			return null;
		}

		$dangerous = in_array( $role, array( 'administrator', 'editor', 'author' ), true );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => $dangerous
				? __( 'The default user role for new registrations is set to a high-privilege role. Anyone who registers will immediately have broad site access. Change it to Subscriber unless you have a specific business reason.', 'thisismyurl-shadow' )
				: __( 'The default user role for new registrations is not the standard Subscriber role. Confirm this is intentional and that the assigned role grants only the minimum permissions required.', 'thisismyurl-shadow' ),
			'severity'     => $dangerous ? 'high' : 'medium',
			'threat_level' => $dangerous ? 80 : 40,
			'details'      => array(
				'default_role'    => $role,
				'registration_open' => WP_Settings::is_registration_open(),
			),
		);
	}
}
