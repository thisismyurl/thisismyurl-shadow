<?php
/**
 * Treatment: Login URL Hardening
 *
 * Generates a cryptographically random secret token and stores it in the
 * `thisismyurl_shadow_login_url_token` option.
 *
 * The enforcement logic runs via Treatment_Hooks::init():
 *  - The `login_url` filter appends `?wpstoken=TOKEN` to every URL returned
 *    by wp_login_url() so internal WordPress links always work.
 *  - The `login_init` action redirects any request to wp-login.php that does
 *    NOT supply the correct token, sending bots and scanners to the homepage
 *    with a 302 response.
 *
 * This ensures automated scans and brute-force bots cannot reach the login
 * page, while authorised users who navigate via the WordPress admin menu —
 * which calls wp_login_url() — are seamlessly redirected with the token
 * already included.
 *
 * Safety mechanisms:
 *  - If the stored option is ever empty, the gate is bypassed completely —
 *    you can never be permanently locked out.
 *  - undo() deletes the token option, immediately disabling the gate on the
 *    next page load.
 *  - The treatment stores the current admin's login URL with the token in the
 *    result message for reference.
 *
 * Risk level: medium — login flow changes.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Protects wp-login.php with a secret query token.
 */
class Treatment_Login_Url_Hardening extends Treatment_Base {

	/** @var string */
	protected static $slug = 'login-url-hardening';

	const OPTION_KEY   = 'thisismyurl_shadow_login_url_token';
	const TOKEN_LENGTH = 16; // characters

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	public static function get_finding_id(): string {
		return self::$slug;
	}

	public static function get_risk_level(): string {
		return 'medium';
	}

	/**
	 * Generate a new random token and store it.
	 *
	 * If a token already exists (e.g. re-applying after a partial undo), a
	 * fresh token is generated to rotate the secret.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function apply(): array {
		$token = self::generate_token();
		update_option( self::OPTION_KEY, $token, false );

		$login_url = add_query_arg( 'wpstoken', $token, wp_login_url() );

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: %s: the new protected login URL */
				__(
					'Login URL protection enabled. Direct access to wp-login.php without the secret token will be redirected to the homepage. Bookmark your new login URL: %s — This token is also appended automatically whenever WordPress generates a login link internally.',
					'thisismyurl-shadow'
				),
				esc_url( $login_url )
			),
		];
	}

	/**
	 * Remove the secret token, immediately disabling the login gate.
	 *
	 * After undo, wp-login.php is accessible again without any query token.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function undo(): array {
		delete_option( self::OPTION_KEY );

		return [
			'success' => true,
			'message' => __( 'Login URL hardening disabled. wp-login.php is now accessible directly again. The gate is removed immediately on the next page load.', 'thisismyurl-shadow' ),
		];
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Generate a URL-safe random alphanumeric token.
	 *
	 * @return string TOKEN_LENGTH-character random string.
	 */
	private static function generate_token(): string {
		if ( function_exists( 'random_bytes' ) ) {
			$bytes = random_bytes( self::TOKEN_LENGTH );
			// Convert to base64 and strip non-URL-safe chars.
			$b64 = base64_encode( $bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes random bytes into a URL-safe login-slug token.
			return substr( preg_replace( '/[^a-zA-Z0-9]/', '', $b64 ), 0, self::TOKEN_LENGTH );
		}

		// Fallback (PHP < 7): use wp_generate_password without special chars.
		return wp_generate_password( self::TOKEN_LENGTH, false, false );
	}
}
