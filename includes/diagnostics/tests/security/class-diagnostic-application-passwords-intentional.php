<?php
/**
 * Application Passwords Policy Diagnostic
 *
 * Since WordPress 5.6, Application Passwords are enabled by default on any
 * HTTPS site. They allow authenticated users to generate long-lived tokens
 * for programmatic REST API access without entering their main password. Most
 * site owners are unaware this feature is active. On sites that do not use
 * headless frameworks, mobile apps, or REST API automation, Application
 * Passwords represent an additional authentication surface that warrants an
 * intentional decision rather than silent default enablement.
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
 * Diagnostic_Application_Passwords_Intentional Class
 *
 * Checks whether Application Passwords are available on this site and, if
 * so, whether any REST API integration plugin is active that would justify
 * the feature being on. Returns null when unavailable or when a known
 * integration is detected.
 *
 * @since 0.6095
 */
class Diagnostic_Application_Passwords_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'application-passwords-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Application Passwords Policy';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress Application Passwords are enabled and, if so, whether a REST API integration plugin is active that would justify the feature being on by default.';

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
	protected static $confidence = 'low';

	/**
	 * Plugins that legitimately use Application Passwords via the REST API.
	 * Presence of any of these is treated as intentional justification.
	 *
	 * @var string[]
	 */
	private const INTEGRATION_PLUGINS = array(
		'wp-graphql/wp-graphql.php',
		'jwt-authentication-for-wp-rest-api/jwt-auth.php',
		'rest-api-oauth1/plugin.php',
		'wordpress-rest-api-authentication/rest-api-authentication.php',
		'two-factor/two-factor.php',
		'woocommerce/woocommerce.php',
		'jetpack/jetpack.php',
		'wp-mobile-pack/wp-mobile-pack.php',
	);

	/**
	 * Run the diagnostic check.
	 *
	 * Returns null (healthy) when Application Passwords are unavailable or
	 * when a known REST API integration plugin is active. Returns a low-
	 * severity informational finding when Application Passwords are enabled
	 * with no detected integration, prompting a deliberate decision.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when on without justification, null when intentional or unavailable.
	 */
	public static function check() {
		// Feature is unavailable (non-HTTPS site or explicitly disabled).
		if ( ! function_exists( 'wp_is_application_passwords_available' ) || ! wp_is_application_passwords_available() ) {
			return null;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );

		foreach ( self::INTEGRATION_PLUGINS as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null; // Feature is in active use by a known integration.
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress Application Passwords are enabled. This feature allows any user to generate a long-lived REST API token without using their main password. Most site owners are unaware it is active by default on HTTPS sites. No REST API integration plugin was detected that would justify this feature being on. If you are not using a headless front-end, mobile app, or REST API automation, consider disabling Application Passwords to remove an unnecessary authentication surface.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 25,
			'details'      => array(
				'fix' => __( 'To disable Application Passwords site-wide, add to a must-use plugin or functions.php: add_filter( \'wp_is_application_passwords_available\', \'__return_false\' ); — Only disable this if you have no headless integrations, mobile apps, or REST API automation that relies on it. Individual user tokens can also be revoked from each user\'s profile page.', 'thisismyurl-shadow' ),
			),
		);
	}
}
