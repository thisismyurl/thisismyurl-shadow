<?php
/**
 * Two-Factor for Admin Enabled Diagnostic
 *
 * Checks whether a two-factor authentication plugin is active and verifies
 * that all admin accounts have enrolled in 2FA.
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
 * Two-Factor for Admin Enabled Diagnostic Class
 *
 * Detects known 2FA plugins by slug and, when the canonical Two Factor plugin
 * is active, checks each administrator's _two_factor_enabled_providers meta.
 *
 * @since 0.6095
 */
class Diagnostic_Two_Factor_Admin_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'two-factor-admin-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Two-Factor for Admin Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a two-factor authentication plugin is active to require a second verification step for administrator logins.';

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
	 * Checks for known 2FA plugin slugs; when the canonical Two Factor plugin
	 * is active, also verifies that each administrator has enrolled at least
	 * one provider via their user meta.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when 2FA is absent or unenrolled, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$has_two_factor     = in_array( 'two-factor/two-factor.php', $active_plugins, true );
		$has_wp2fa          = in_array( 'wp-2fa/wp-2fa.php', $active_plugins, true );
		$has_wordfence      = in_array( 'wordfence/wordfence.php', $active_plugins, true );
		$has_ithemes        = in_array( 'better-wp-security/better-wp-security.php', $active_plugins, true )
		                   || in_array( 'ithemes-security-pro/ithemes-security-pro.php', $active_plugins, true );
		$has_miniorange     = in_array( 'miniorange-2-factor-authentication/miniorange_2_factor_authentication.php', $active_plugins, true );

		$any_2fa_plugin = $has_two_factor || $has_wp2fa || $has_wordfence || $has_ithemes || $has_miniorange;

		if ( ! $any_2fa_plugin ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'No two-factor authentication (2FA) plugin is active. Administrator accounts without 2FA are extremely vulnerable to brute-force and credential-stuffing attacks. Install a 2FA plugin such as "Two Factor" and enforce enrollment for all admin-level users.', 'thisismyurl-shadow' ),
				'severity'     => 'high',
				'threat_level' => 80,
				'details'      => array( 'two_factor_plugin_active' => false ),
			);
		}

		if ( $has_two_factor ) {
			// Check whether all administrators have enrolled in 2FA.
			$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
			$unenrolled = array();
			foreach ( $admins as $user_id ) {
				$providers = get_user_meta( $user_id, '_two_factor_enabled_providers', true );
				if ( empty( $providers ) ) {
					$unenrolled[] = $user_id;
				}
			}

			if ( ! empty( $unenrolled ) ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => sprintf(
						/* translators: %d: number of admin users without 2FA */
						_n(
							'%d administrator account has not enrolled in two-factor authentication. All admin accounts should have 2FA active to protect the site from account takeover. Enforce enrollment via the Two Factor plugin settings.',
							'%d administrator accounts have not enrolled in two-factor authentication. All admin accounts should have 2FA active to protect the site from account takeover. Enforce enrollment via the Two Factor plugin settings.',
							count( $unenrolled ),
							'thisismyurl-shadow'
						),
						count( $unenrolled )
					),
					'severity'     => 'high',
					'threat_level' => 75,
					'details'      => array(
						'two_factor_plugin_active' => true,
						'unenrolled_admin_count'   => count( $unenrolled ),
					),
				);
			}
		}

		return null;
	}
}
