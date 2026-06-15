<?php
/**
 * Login URL Hardening Diagnostic
 *
 * Checks whether the default wp-login.php URL is protected by a login
 * hardening plugin or whether it is directly accessible to automated attacks.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Diagnostics
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Login_Url_Hardening Class
 *
 * @since 0.6095
 */
class Diagnostic_Login_Url_Hardening extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'login-url-hardening';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Login URL Hardening';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a login hardening plugin is active or whether the default wp-login.php URL remains publicly accessible without rate-limiting, making it vulnerable to brute-force attacks.';

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
	 * First looks for active login hardening plugins. If none are found, makes
	 * a HEAD request to site_url('wp-login.php') and flags if the page returns
	 * HTTP 200 or redirects to the login form (302).
	 *
	 * @since  0.6095
	 * @return array|null Finding array if issue exists, null if healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$login_hardening_plugins = array(
			'wps-hide-login/wps-hide-login.php'            => 'WPS Hide Login',
			'rename-wp-login/rename-wp-login.php'          => 'Rename wp-login.php',
			'wordfence/wordfence.php'                      => 'Wordfence (login rate-limiting)',
			'better-wp-security/better-wp-security.php'   => 'iThemes Security',
			'ithemes-security-pro/ithemes-security-pro.php' => 'iThemes Security Pro',
			'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => 'Limit Login Attempts Reloaded',
			'loginizer/loginizer.php'                      => 'Loginizer',
			'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security',
			'shield-security/icwp-wpsf.php'               => 'Shield Security',
		);

		foreach ( $login_hardening_plugins as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null; // Login hardening is handled.
			}
		}

		// No hardening plugin found. Check if default login URL is accessible.
		$login_url = site_url( 'wp-login.php' );
		$response  = wp_remote_head( $login_url, array(
			'timeout'     => 5,
			'user-agent'  => 'Shadow by Christopher Ross-Diagnostic/1.0',
			'sslverify'   => false,
			'redirection' => 0,
		) );

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( in_array( $code, array( 200, 302 ), true ) || 0 === $code ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'The default WordPress login URL (wp-login.php) is publicly accessible with no rate-limiting or URL-hiding in place. This makes the login page a target for automated brute-force attacks. Install a login hardening plugin such as Limit Login Attempts Reloaded or WPS Hide Login to mitigate attack surface.', 'thisismyurl-shadow' ),
				'severity'     => 'medium',
				'threat_level' => 50,
				'details'      => array(
					'default_login_accessible' => true,
					'http_code'                => $code,
				),
			);
		}

		return null;
	}
}
