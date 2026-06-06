<?php
/**
 * Treatment: Harden Admin Session Expiration
 *
 * Stores a Christopher Ross Shadow option that instructs the plugin bootstrap to hook
 * auth_cookie_expiration and return DAY_IN_SECONDS (24 hours) for
 * administrator-level users instead of WordPress's default 14-day lifetime.
 *
 * This approach is entirely option-based — no file edits are required —
 * and is fully reversible by deleting the stored option.
 *
 * Risk level: safe — option toggle only. The filter runs on every login so
 * existing long-lived sessions remain valid until they expire naturally;
 * only new sessions will use the shorter lifetime immediately.
 *
 * Bootstrap responsibilities (applied when option is true):
 *  add_filter( 'auth_cookie_expiration', function( $length, $user_id ) {
 *      if ( user_can( $user_id, 'manage_options' ) ) {
 *          return DAY_IN_SECONDS; // 24 hours
 *      }
 *      return $length;
 *  }, 10, 2 );
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reduces admin session cookie lifetime to 24 hours.
 */
class Treatment_Admin_Session_Expiration_Hardened extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'admin-session-expiration-hardened';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Set the session-hardening toggle.
	 *
	 * @return array
	 */
	public static function apply() {
		update_option( 'thisismyurl_shadow_harden_admin_session_expiry', true, false );

		return array(
			'success' => true,
			'message' => __( 'Admin session lifetime reduced to 24 hours. Existing sessions retain their current expiry; the shorter lifetime applies to all new admin logins immediately.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the session-hardening toggle, restoring WordPress defaults.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_harden_admin_session_expiry' );

		return array(
			'success' => true,
			'message' => __( 'Admin session hardening removed. WordPress will use its default session lifetime for new logins.', 'thisismyurl-shadow' ),
		);
	}
}
