<?php
/**
 * Login Throttling Active Diagnostic
 *
 * Checks whether a login throttling or brute-force protection plugin is
 * active to limit repeated failed login attempts on the WordPress login page.
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
 * Login Throttling Active Diagnostic Class
 *
 * Detects known brute-force-protection plugins via option and class signatures,
 * flagging sites that have no recognised login-throttling mechanism.
 *
 * @since 0.6095
 */
class Diagnostic_Login_Throttling_Active extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'login-throttling-active';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Login Throttling Active';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a login throttling or brute-force protection plugin is active to limit repeated failed login attempts on the WordPress login page.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * Inspects well-known option keys and loaded classes from popular login-
	 * throttling plugins; returns a high-severity finding when none are detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no throttling is active, null when healthy.
	 */
	public static function check() {
		// Check Option signatures from known login-throttling plugins.
		$option_indicators = array(
			'wps-limit-login-whitelist',   // WPS Limit Login
			'limit_login_options',          // Limit Login Attempts Reloaded
			'loginlockdown_settings',        // Login LockDown
			'cerber-main',                   // WP Cerber Security
			'wordfence_loginSecurity',        // Wordfence (login security)
			'sfs_options',                   // Login Attempts
		);

		foreach ( $option_indicators as $option ) {
			if ( false !== get_option( $option, false ) ) {
				return null;
			}
		}

		// Class-based check for actively loaded throttling solutions.
		$class_indicators = array(
			'WPS_Limit_Login',
			'Cerber_Main',
			'limitLoginAttempts',
		);

		foreach ( $class_indicators as $class ) {
			if ( class_exists( $class, false ) ) {
				return null;
			}
		}

		// Wordfence: check if login security feature bit is enabled.
		$wf_config = get_option( 'wordfence_entries', false );
		if ( is_array( $wf_config ) && ! empty( $wf_config['loginSecurity'] ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No login throttling or brute-force protection was detected. Without it, attackers can make unlimited password attempts against any WordPress account. Install a plugin that limits failed login attempts and temporarily locks out offending IP addresses.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 70,
			'details'      => array(
				'note' => __( 'Install Wordfence, WPS Limit Login, Limit Login Attempts Reloaded, or WP Cerber to protect your login page from brute-force attacks.', 'thisismyurl-shadow' ),
			),
		);
	}
}
