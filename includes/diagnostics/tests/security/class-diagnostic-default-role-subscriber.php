<?php
/**
 * Default Role is Subscriber Diagnostic
 *
 * Checks whether the default role assigned to new user registrations is
 * "subscriber" — the least-privileged role — to minimise privilege escalation risk.
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
 * Default Role is Subscriber Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Default_Role_Subscriber extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'default-role-subscriber';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Default Role is Subscriber';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the default role assigned to newly registered users is set to Subscriber, minimizing capabilities granted automatically on registration.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Reads the default_role option via WP_Settings and flags when the role
	 * is anything other than subscriber, especially privileged roles.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when default role is privileged, null when healthy.
	 */
	public static function check() {
		$role = WP_Settings::get_default_user_role();

		if ( 'subscriber' === $role ) {
			return null;
		}

		$dangerous = in_array( $role, array( 'administrator', 'editor', 'author' ), true );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => $dangerous
				? __( 'The default user role for new registrations is set to a high-privilege role. Any visitor who registers will immediately have broad site access including editing posts or managing the site.', 'thisismyurl-shadow' )
				: __( 'The default user role for new registrations is not Subscriber. Confirm this is intentional and that the role grants only the minimum capabilities required for your use case.', 'thisismyurl-shadow' ),
			'severity'     => $dangerous ? 'high' : 'medium',
			'threat_level' => $dangerous ? 80 : 40,
			'details'      => array(
				'default_role'      => $role,
				'registration_open' => WP_Settings::is_registration_open(),
				'high_risk'         => $dangerous,
			),
		);
	}
}
