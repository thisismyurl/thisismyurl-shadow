<?php
/**
 * Admin Session Expiration Hardened Diagnostic
 *
 * Checks whether the authentication cookie lifetime for admin sessions has
 * been reduced from WordPress defaults to lower the risk of session hijack.
 *
 * @package    Shadow by Christopher Ross
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
 * Admin Session Expiration Hardened Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Session_Expiration_Hardened extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-session-expiration-hardened';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Admin Session Expiration Hardened';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress admin authentication cookie lifetime has been reduced from the default 14-day period to limit the window for session hijacking.';

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
	 * Applies the auth_cookie_expiration filter to inspect the effective session
	 * lifetime for admin accounts and flags values exceeding 7 days.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when session lifetime is too long, null when healthy.
	 */
	public static function check() {
		// WordPress default: 14 days with "Remember Me" (1209600 seconds), 2 days without (172800).
		// Hardened sites reduce this for privileged sessions.
		// We simulate what WP will return for an admin session expiry.
		$sample_expiry = apply_filters( 'auth_cookie_expiration', 1209600, 0, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reading a WordPress core filter's value for diagnostics, not defining a plugin hook.

		// 1209600 = 14 days. Anything above 7 days (604800) for admin sessions is too long.
		$seven_days = 7 * DAY_IN_SECONDS;

		if ( (int) $sample_expiry <= $seven_days ) {
			return null; // Session expiry has been hardened.
		}

		// Also check for security plugins that manage session expiry.
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$session_plugins = array(
			'better-wp-security/better-wp-security.php',
			'ithemes-security-pro/ithemes-security-pro.php',
			'wordfence/wordfence.php',
			'shield-security/icwp-wpsf.php',
			'user-session-management/user-session-management.php',
		);

		foreach ( $session_plugins as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null; // Plugin likely manages session lifetimes.
			}
		}

		$days = round( $sample_expiry / DAY_IN_SECONDS, 1 );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: session duration in days */
				__( 'WordPress admin sessions expire after %s days by default. Long-lived authenticated sessions increase the risk of session hijacking and leave unattended admin devices vulnerable. Hook the auth_cookie_expiration filter or install a security plugin to reduce admin session lifetime to 24 hours or less.', 'thisismyurl-shadow' ),
				$days
			),
			'severity'     => 'low',
			'threat_level' => 25,
			'details'      => array(
				'session_expiry_seconds' => (int) $sample_expiry,
				'session_expiry_days'    => $days,
			),
		);
	}
}
