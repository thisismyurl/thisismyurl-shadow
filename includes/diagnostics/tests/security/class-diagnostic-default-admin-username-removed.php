<?php
/**
 * Default Admin Username Removed Diagnostic
 *
 * Checks whether a user account named "admin" still exists. This is the
 * most common target for brute-force credential stuffing attacks.
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
 * Diagnostic_Default_Admin_Username_Removed Class
 *
 * @since 0.6095
 */
class Diagnostic_Default_Admin_Username_Removed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'default-admin-username-removed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Default Admin Username Removed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a user account with the username "admin" exists, which is a high-value target for automated brute-force attacks.';

	/**
	 * Gauge family/category.
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
	 * Queries the users table for a login named "admin" and flags the site
	 * if such an account is found.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when "admin" user exists, null when healthy.
	 */
	public static function check() {
		if ( false === username_exists( 'admin' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'A user account with the login name "admin" exists on your site. This is the first username that automated attack tools try when brute-forcing WordPress logins. Rename or delete this account and use a unique, non-obvious username for your administrator.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 70,
			'details'      => array(
				'admin_login_exists' => true,
			),
		);
	}
}
