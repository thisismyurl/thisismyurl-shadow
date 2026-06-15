<?php
/**
 * Auth Keys And Salts Set Diagnostic
 *
 * Checks whether all WordPress authentication keys and salts are set to unique,
 * non-default values in wp-config.php to protect session security.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Server_Environment_Helper as Server_Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Auth_Keys_And_Salts_Set Class
 *
 * @since 0.6095
 */
class Diagnostic_Auth_Keys_And_Salts_Set extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'auth-keys-and-salts-set';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Auth Keys And Salts Set';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether all WordPress authentication keys and salts are set to unique, non-default values in wp-config.php to protect session security.';

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
	 * Calls Server_Env::get_auth_key_issues() to verify each AUTH_KEY, SECURE_AUTH_KEY,
	 * and related constant is defined and not left as a placeholder value.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when keys are missing or default, null when healthy.
	 */
	public static function check() {
		$issues = Server_Env::get_auth_key_issues();

		if ( empty( $issues ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'One or more WordPress authentication keys or salts are missing, empty, or still set to the placeholder value from wp-config-sample.php. These values cryptographically sign cookies and sessions. Weak or unconfigured keys allow session forgery attacks.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 75,
			'details'      => array(
				'problematic_keys' => $issues,
				'key_count'        => count( $issues ),
			),
		);
	}
}
