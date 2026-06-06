<?php
/**
 * Treatment: Auth Keys and Salts Set
 *
 * Fetches fresh authentication keys and salts from the WordPress.org secret-key
 * API and inserts them into wp-config.php using marker-wrapped blocks. Because
 * the block is inserted directly after `<?php`, the new constants are defined
 * first; any existing placeholder definitions lower in the file are silently
 * ignored (PHP does not allow redefining a constant).
 *
 * File written: wp-config.php
 * Risk level:   high (file write + session invalidation)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;
use ThisIsMyURL\Shadow\Core\External_Request_Guard;
use ThisIsMyURL\Shadow\Admin\File_Write_Registry;

// Load the shared file-write helpers trait.
require_once __DIR__ . '/trait-file-write-helpers.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces placeholder auth keys/salts with fresh values from the WP API.
 */
class Treatment_Auth_Keys_And_Salts_Set extends Treatment_Base {

	use File_Write_Helpers;

	/** @var string */
	protected static $slug = 'auth-keys-and-salts-set';

	const MARKER_SLUG = 'auth-keys-and-salts-set';

	const SALT_API_URL = 'https://api.wordpress.org/secret-key/1.1/salt/';

	const SALT_CONSTANTS = [
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	];

	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	public static function get_finding_id(): string {
		return self::$slug;
	}

	public static function get_risk_level(): string {
		return 'high';
	}

	/**
	 * Fetch fresh salts from the WordPress.org API and write to wp-config.php.
	 *
	 * All logged-in sessions will be invalidated because the session tokens are
	 * derived from the auth salts. The current admin session will also be
	 * invalidated — note this in the change summary.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function apply(): array {
		if ( ! External_Request_Guard::is_allowed( 'salt_rotation', get_current_user_id(), self::SALT_API_URL ) ) {
			return [
				'success' => false,
				'message' => External_Request_Guard::get_denied_message( __( 'Salt rotation', 'thisismyurl-shadow' ) ),
			];
		}

		$response = wp_remote_get( self::SALT_API_URL, [
			'timeout'   => 15,
			'sslverify' => true,
			'user-agent' => 'Christopher Ross Shadow/' . THISISMYURL_SHADOW_VERSION . '; ' . home_url( '/' ),
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Could not fetch salts from WordPress.org API: %s', 'thisismyurl-shadow' ),
					$response->get_error_message()
				),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP response code */
					__( 'WordPress.org salt API returned HTTP %d. Unable to fetch new keys.', 'thisismyurl-shadow' ),
					$code
				),
			];
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return [
				'success' => false,
				'message' => __( 'WordPress.org salt API returned an empty response.', 'thisismyurl-shadow' ),
			];
		}

		// Sanitise: strip any leading PHP open tag, trim whitespace.
		$salts_block = trim( str_replace( '<?php', '', $body ) );

		// Verify the block contains what we expect.
		if ( false === strpos( $salts_block, "define('AUTH_KEY'" ) ) {
			return [
				'success' => false,
				'message' => __( 'Salt API response did not contain expected define() lines. Aborting to avoid corrupting wp-config.php.', 'thisismyurl-shadow' ),
			];
		}

		return self::write_wp_config_define(
			self::get_target_file(),
			self::MARKER_SLUG,
			$salts_block
		);
	}

	public static function undo(): array {
		return self::remove_wp_config_block( self::get_target_file(), self::MARKER_SLUG );
	}

	// =========================================================================
	// File_Write_Registry interface
	// =========================================================================

	public static function get_target_file(): string {
		return ABSPATH . 'wp-config.php';
	}

	public static function get_file_label(): string {
		return 'wp-config.php';
	}

	public static function get_proposed_change_summary(): string {
		return __( 'Insert fresh authentication keys and salts into wp-config.php (fetched from WordPress.org API)', 'thisismyurl-shadow' );
	}

	public static function get_proposed_snippet(): string {
		return implode( "\n", [
			"// thisismyurl_shadow_MARKER_START: auth-keys-and-salts-set",
			"define('AUTH_KEY',         '** fetched from api.wordpress.org **');",
			"define('SECURE_AUTH_KEY',  '** fetched from api.wordpress.org **');",
			"define('LOGGED_IN_KEY',    '** fetched from api.wordpress.org **');",
			"define('NONCE_KEY',        '** fetched from api.wordpress.org **');",
			"define('AUTH_SALT',        '** fetched from api.wordpress.org **');",
			"define('SECURE_AUTH_SALT', '** fetched from api.wordpress.org **');",
			"define('LOGGED_IN_SALT',   '** fetched from api.wordpress.org **');",
			"define('NONCE_SALT',       '** fetched from api.wordpress.org **');",
			"// thisismyurl_shadow_MARKER_END: auth-keys-and-salts-set",
		] );
	}

	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode( "\n", [
			"Connect to your server via SFTP or cPanel File Manager.",
			"Navigate to: {$file}",
			"Open the file in a text editor.",
			"Find and delete the block between these two marker lines (inclusive):",
			"  // thisismyurl_shadow_MARKER_START: auth-keys-and-salts-set",
			"  ... (eight define() lines) ...",
			"  // thisismyurl_shadow_MARKER_END: auth-keys-and-salts-set",
			"Save the file.",
			"Note: Removing these markers restores the original (placeholder) keys.",
			"All active sessions will be invalidated again when the old keys take effect.",
		] );
	}
}

Treatment_Auth_Keys_And_Salts_Set::boot();
