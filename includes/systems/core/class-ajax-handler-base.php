<?php
/**
 * Base AJAX Handler
 *
 * Abstract base class for AJAX handlers to eliminate security check duplication.
 *
 * @package ThisIsMyURL\Shadow
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provide the shared contract for Christopher Ross Shadow AJAX and admin-post handlers.
 *
 * Most handlers in the plugin are intentionally thin. They should express the
 * business action being performed, while this base class owns the repetitive
 * infrastructure concerns: nonce verification, capability checks, request
 * sanitization, pagination helpers, and error-shaping conventions.
 */
abstract class AJAX_Handler_Base {
	/**
	 * Verify request with the standard manage_options capability contract.
	 *
	 * @param string $nonce_action Nonce action name.
	 * @param string $nonce_field  Nonce field name.
	 * @return void
	 */
	protected static function verify_manage_options_request( string $nonce_action, string $nonce_field = 'nonce' ): void {
		self::verify_request( $nonce_action, 'manage_options', $nonce_field );
	}

	/**
	 * Retrieve a Christopher Ross Shadow option expected to be an array.
	 *
	 * @param string $option   Option name.
	 * @param array  $fallback Default array value.
	 * @return array
	 */
	protected static function get_array_option( string $option, array $fallback = array() ): array {
		return Options_Manager::get_array( $option, $fallback );
	}

	/**
	 * Toggle a class name in a disabled-list option and persist the result.
	 *
	 * @since 0.6095
	 * @param  string $option_key Option storing disabled class names.
	 * @param  string $class_name Class to add or remove.
	 * @param  bool   $enable     True to enable/remove from disabled list.
	 * @return array<int, string> Updated disabled class names.
	 */
	protected static function toggle_class_in_disabled_list( string $option_key, string $class_name, bool $enable ): array {
		$disabled   = self::get_array_option( $option_key, array() );
		$disabled   = array_values( array_unique( array_map( 'strval', $disabled ) ) );
		$class_name = trim( $class_name );

		if ( '' === $class_name ) {
			return $disabled;
		}

		if ( $enable ) {
			$disabled = array_values(
				array_filter(
					$disabled,
					static function ( string $candidate ) use ( $class_name ): bool {
						return $candidate !== $class_name;
					}
				)
			);
		} elseif ( ! in_array( $class_name, $disabled, true ) ) {
			$disabled[] = $class_name;
		}

		update_option( $option_key, $disabled );

		return $disabled;
	}

	/**
	 * Sanitize standard pagination parameters.
	 *
	 * @param int $default_per_page Default per-page size.
	 * @param int $max_per_page     Maximum per-page size.
	 * @return array{page:int, per_page:int, start:int}
	 */
	protected static function get_pagination_params( int $default_per_page = 25, int $max_per_page = 100 ): array {
		$page     = max( 1, absint( self::get_post_param( 'page', 'int', 1 ) ) );
		$per_page = min( $max_per_page, max( 1, absint( self::get_post_param( 'per_page', 'int', $default_per_page ) ) ) );
		$start    = ( $page - 1 ) * $per_page;

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'start'    => $start,
		);
	}

	/**
	 * Slice items for the current page.
	 *
	 * @param array $items    Full item list.
	 * @param int   $start    Start offset.
	 * @param int   $per_page Page size.
	 * @return array
	 */
	protected static function paginate_items( array $items, int $start, int $per_page ): array {
		return array_slice( $items, $start, $per_page );
	}

	/**
	 * Verify an AJAX request against Christopher Ross Shadow's standard security contract.
	 *
	 * The base contract for privileged handlers is:
	 * - optional rate limiting,
	 * - nonce verification,
	 * - capability verification.
	 *
	 * Centralizing that sequence prevents individual handlers from drifting into
	 * inconsistent security behavior.
	 *
	 * @since 0.6095 Added rate limiting.
	 * @param string $nonce_action The nonce action to verify.
	 * @param string $capability   The capability required (default: manage_options).
	 * @param string $nonce_field  The nonce field name (default: nonce).
	 * @return void Dies on failure, returns on success.
	 */
	protected static function verify_request( $nonce_action, $capability = 'manage_options', $nonce_field = 'nonce' ) {
		// Rate limit check (if enabled).
		if ( class_exists( 'ThisIsMyURL\Shadow\Core\Rate_Limiter' ) ) {
			$user_id    = get_current_user_id();
			$ip_address = self::get_client_ip();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Helper is called only from verified AJAX handlers.
			$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

			if ( ! Rate_Limiter::check_rate_limit( $action, $user_id, $ip_address ) ) {
				wp_send_json_error(
					array(
						'message'      => Rate_Limiter::get_rate_limit_message( $action, $user_id, $ip_address ),
						'rate_limited' => true,
					),
					429 // HTTP 429 Too Many Requests.
				);
			}
		}

		// Verify nonce.
		check_ajax_referer( $nonce_action, $nonce_field );

		// Verify capability.
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'thisismyurl-shadow' ),
				)
			);
		}
	}

	/**
	 * Verify admin request (GET-based) with nonce and capability check.
	 *
	 * Uses check_admin_referer instead of check_ajax_referer for GET requests.
	 * Dies with wp_die on failure (not JSON response).
	 *
	 * @param string $nonce_action The nonce action to verify.
	 * @param string $capability   The capability required (default: manage_options).
	 * @param string $nonce_field  The nonce field name (default: _wpnonce).
	 * @return void Dies on failure, returns on success.
	 */
	protected static function verify_admin_request( $nonce_action, $capability = 'manage_options', $nonce_field = '_wpnonce' ) {
		// Verify nonce (admin referer for GET requests).
		check_admin_referer( $nonce_action, $nonce_field );

		// Verify capability.
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'thisismyurl-shadow' ), 403 );
		}
	}

	/**
	 * Read and sanitize a single scalar POST value.
	 *
	 * Handlers use this helper instead of touching superglobals directly so all
	 * request parsing follows the same unslashing and sanitization rules.
	 *
	 * @param string $key          The POST parameter key.
	 * @param string $type         The sanitization type (text, email, key, textarea, int, bool).
	 * @param mixed  $fallback     Default value if parameter is missing.
	 * @param bool   $required     Whether this parameter is required.
	 * @return mixed Sanitized value or error sent.
	 */
	protected static function get_post_param( $key, $type = 'text', $fallback = '', $required = false ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Centralized helper for verified handlers.
		if ( ! isset( $_POST[ $key ] ) ) {
			if ( $required ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
						/* translators: %s: parameter name */
							__( 'Required parameter "%s" is missing.', 'thisismyurl-shadow' ),
							$key
						),
					)
				);
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return $fallback;
		}

		$value = self::sanitize_param_value( wp_unslash( $_POST[ $key ] ), $type, $fallback ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Centralized helper for verified handlers.
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return $value;
	}

	/**
	 * Read and sanitize an array-shaped POST value.
	 *
	 * This exists because several Christopher Ross Shadow admin screens submit structured data
	 * such as preference maps or grouped treatment inputs. The helper ensures the
	 * array contract is validated before downstream business logic runs.
	 *
	 * @param string $key          The POST parameter key.
	 * @param string $item_type    The sanitization type for each scalar item.
	 * @param array  $fallback     Default value if parameter is missing or invalid.
	 * @param bool   $required     Whether this parameter is required.
	 * @return array Sanitized array value or error sent.
	 */
	protected static function get_post_array_param( $key, $item_type = 'text', array $fallback = array(), bool $required = false ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Centralized helper for verified handlers.
		if ( ! isset( $_POST[ $key ] ) ) {
			if ( $required ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
						/* translators: %s: parameter name */
							__( 'Required parameter "%s" is missing.', 'thisismyurl-shadow' ),
							$key
						),
					)
				);
			}

			// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return $fallback;
		}

		$raw_value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Centralized helper for verified handlers.
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_array( $raw_value ) ) {
			if ( $required ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
						/* translators: %s: parameter name */
							__( 'Parameter "%s" must be an array.', 'thisismyurl-shadow' ),
							$key
						),
					)
				);
			}

			return $fallback;
		}

		return self::sanitize_array_param_value( $raw_value, $item_type );
	}

	/**
	 * Sanitize and validate a request parameter from $_REQUEST.
	 *
	 * Intended for verified admin-post flows where the request method may vary.
	 *
	 * @param string $key          The request parameter key.
	 * @param string $type         The sanitization type.
	 * @param mixed  $fallback     Default value if parameter is missing.
	 * @param bool   $required     Whether this parameter is required.
	 * @return mixed Sanitized value or dies if required and missing.
	 */
	protected static function get_request_param( $key, $type = 'text', $fallback = '', $required = false ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Centralized helper for verified admin-post flows.
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			if ( $required ) {
				wp_die(
					sprintf(
						/* translators: %s: parameter name */
						esc_html__( 'Required parameter "%s" is missing.', 'thisismyurl-shadow' ),
						esc_html( $key )
					),
					400
				);
			}

			// phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return $fallback;
		}

		$value = self::sanitize_param_value( wp_unslash( $_REQUEST[ $key ] ), $type, $fallback ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Centralized helper for verified admin-post flows.
		// phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return $value;
	}

	/**
	 * Sanitize a parameter value for a supported type.
	 *
	 * @param mixed  $value   Raw request value.
	 * @param string $type    Expected sanitization type.
	 * @param mixed  $fallback Default value for unsupported or invalid data.
	 * @return mixed
	 */
	protected static function sanitize_param_value( $value, $type = 'text', $fallback = '' ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );
			case 'file':
				return sanitize_file_name( (string) $value );
			case 'key':
				return sanitize_key( $value );
			case 'raw':
				return $value;
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'int':
				return intval( $value );
			case 'bool':
				return rest_sanitize_boolean( $value );
			case 'url':
				return esc_url_raw( $value );
			case 'json':
				$decoded = json_decode( (string) $value, true );
				return is_array( $decoded ) ? $decoded : $fallback;
			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Recursively sanitize an array parameter.
	 *
	 * @param array  $value     Raw array value.
	 * @param string $item_type Expected sanitization type for scalar items.
	 * @return array
	 */
	protected static function sanitize_array_param_value( array $value, string $item_type = 'text' ): array {
		$sanitized = array();

		foreach ( $value as $array_key => $array_value ) {
			$normalized_key = is_string( $array_key ) ? sanitize_key( $array_key ) : $array_key;

			if ( is_array( $array_value ) ) {
				$sanitized[ $normalized_key ] = self::sanitize_array_param_value( $array_value, $item_type );
				continue;
			}

			$sanitized[ $normalized_key ] = self::sanitize_param_value( $array_value, $item_type, '' );
		}

		return $sanitized;
	}

	/**
	 * Ensure a managed file path stays within the WordPress install boundary.
	 *
	 * Allows files inside ABSPATH and the canonical parent wp-config.php path.
	 * Rejects symlink targets so file review endpoints cannot be redirected to
	 * arbitrary locations by a tampered registry entry or filesystem state.
	 *
	 * @param string $file_path Absolute file path.
	 * @return string Normalized file path.
	 */
	protected static function assert_allowed_managed_file_path( string $file_path ): string {
		$file_path = trim( $file_path );

		if ( '' === $file_path ) {
			self::send_error( __( 'Invalid target file.', 'thisismyurl-shadow' ) );
		}

		$normalized_path = wp_normalize_path( $file_path );
		$wp_root         = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$wp_parent       = wp_normalize_path( untrailingslashit( dirname( $wp_root ) ) );
		$wp_config_path  = wp_normalize_path( $wp_parent . '/wp-config.php' );

		$path_in_root = $normalized_path === $wp_root || 0 === strpos( $normalized_path, $wp_root . '/' );
		$is_wp_config = $normalized_path === $wp_config_path;

		if ( ! $path_in_root && ! $is_wp_config ) {
			self::send_error( __( 'The requested file is outside the allowed WordPress paths.', 'thisismyurl-shadow' ) );
		}

		if ( file_exists( $file_path ) && is_link( $file_path ) ) {
			self::send_error( __( 'Symlinked files cannot be modified from this workflow.', 'thisismyurl-shadow' ) );
		}

		$parent_dir  = dirname( $file_path );
		$real_parent = realpath( $parent_dir );

		if ( false === $real_parent ) {
			self::send_error( __( 'The target directory could not be validated.', 'thisismyurl-shadow' ) );
		}

		$normalized_parent = wp_normalize_path( $real_parent );
		$parent_in_root    = $normalized_parent === $wp_root || 0 === strpos( $normalized_parent, $wp_root . '/' );
		$parent_is_config  = $is_wp_config && $normalized_parent === $wp_parent;

		if ( ! $parent_in_root && ! $parent_is_config ) {
			self::send_error( __( 'The target directory is outside the allowed WordPress paths.', 'thisismyurl-shadow' ) );
		}

		if ( is_link( $parent_dir ) ) {
			self::send_error( __( 'Symlinked directories cannot be modified from this workflow.', 'thisismyurl-shadow' ) );
		}

		return $normalized_path;
	}

	/**
	 * Bootstrap the WordPress filesystem API for managed file operations.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	protected static function get_wp_filesystem() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;

		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return $wp_filesystem;
		}

		if ( ! function_exists( 'WP_Filesystem' ) || ! WP_Filesystem() ) {
			return null;
		}

		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}

	/**
	 * Read a managed file using the WordPress filesystem API.
	 *
	 * @param string $file_path Absolute file path.
	 * @return string|false
	 */
	protected static function read_wp_filesystem_file( string $file_path ) {
		$filesystem = self::get_wp_filesystem();
		if ( ! $filesystem ) {
			return false;
		}

		return $filesystem->get_contents( $file_path );
	}

	/**
	 * Write a managed file using the WordPress filesystem API.
	 *
	 * @param string $file_path Absolute file path.
	 * @param string $content   File contents.
	 * @return bool
	 */
	protected static function write_wp_filesystem_file( string $file_path, string $content ): bool {
		$filesystem = self::get_wp_filesystem();
		if ( ! $filesystem ) {
			return false;
		}

		return (bool) $filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE );
	}

	/**
	 * Send standardized success response.
	 *
	 * @param array $data Additional data to include in response.
	 * @return void Dies after sending response.
	 */
	protected static function send_success( $data = array() ) {
		wp_send_json_success( $data );
	}

	/**
	 * Send standardized error response.
	 *
	 * Ensures user-facing messages are friendly and do not expose technical
	 * details. Technical errors are logged server-side for debugging.
	 *
	 * @since 0.6095
	 * @param string|\WP_Error $message Error message or WP_Error instance.
	 * @param array            $data    Additional data to include in response.
	 * @return void Dies after sending response.
	 */
	protected static function send_error( $message, $data = array() ) {
		$raw_message      = $message;
		$friendly_message = self::format_error_message( $message );

		if ( $friendly_message !== $raw_message && class_exists( 'ThisIsMyURL\Shadow\Core\Error_Handler' ) ) {
			$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Error logging only inspects the verified AJAX action.
			Error_Handler::log_error(
				'AJAX error message sanitized',
				array(
					'action'  => $action,
					'message' => is_wp_error( $raw_message ) ? $raw_message->get_error_message() : (string) $raw_message,
				)
			);
		}

		$data['message'] = $friendly_message;
		wp_send_json_error( $data );
	}

	/**
	 * Format error message for user-friendly display.
	 *
	 * Converts technical errors into plain-language guidance.
	 *
	 * @since 0.6095
	 * @param  string|\WP_Error $message Error message or WP_Error instance.
	 * @return string User-friendly message.
	 */
	protected static function format_error_message( $message ): string {
		if ( is_wp_error( $message ) ) {
			$message = $message->get_error_message();
		}

		$message = wp_strip_all_tags( (string) $message );
		$message = trim( $message );

		if ( '' === $message ) {
			return __( 'We couldn\'t complete that request right now. Please try again in a moment.', 'thisismyurl-shadow' );
		}

		$technical_patterns = array(
			'/\bSQL\b/i',
			'/\bwpdb\b/i',
			'/\bmysqli?\b/i',
			'/\bFatal error\b/i',
			'/\bWarning\b/i',
			'/\bNotice\b/i',
			'/\bException\b/i',
			'/\bStack trace\b/i',
			'/\bon line\b/i',
			'/\.php\b/i',
			'/\bUndefined\b/i',
			'/\bCall to\b/i',
			'/\bbacktrace\b/i',
			'/\bREST\b/i',
			'/\bcURL\b/i',
			'/\bHTTP \d{3}\b/i',
			'/\bpermission denied\b/i',
			'/\bfile not found\b/i',
		);

		foreach ( $technical_patterns as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				return __( 'We couldn\'t complete that request right now. Please try again in a moment.', 'thisismyurl-shadow' );
			}
		}

		// Keep friendly short messages as-is.
		if ( strlen( $message ) > 180 ) {
			return __( 'We couldn\'t complete that request right now. Please try again in a moment.', 'thisismyurl-shadow' );
		}

		return $message;
	}

	/**
	 * Get client IP address.
	 *
	 * Handles proxies and CloudFlare properly.
	 *
	 * @since 0.6095
	 * @return string IP address.
	 */
	protected static function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // CloudFlare.
			'HTTP_X_FORWARDED_FOR',  // Proxy.
			'HTTP_X_REAL_IP',        // Nginx proxy.
			'REMOTE_ADDR',           // Direct connection.
		);

		foreach ( $ip_keys as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw server lookup before sanitization below.
			if ( ! empty( $_SERVER[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is sanitized immediately.
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// X-Forwarded-For can contain multiple IPs.
				if ( false !== strpos( $ip, ',' ) ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}

				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0'; // Fallback.
	}

	/**
	 * Get the WordPress AJAX action name from the class name.
	 *
	 * Convention: Dismiss_Finding_Handler -> thisismyurl_shadow_dismiss_finding
	 *
	 * @since 0.6095
	 * @return string AJAX action name.
	 */
	protected static function get_action() {
		$class_name = get_called_class();

		// Get just the class name (remove namespace).
		$parts      = explode( '\\', $class_name );
		$short_name = end( $parts );

		// Remove _Handler suffix if present.
		$short_name = preg_replace( '/_Handler$/i', '', $short_name );

		// Convert from PascalCase/Snake_Case to snake_case.
		$action = strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $short_name ) );

		// Add thisismyurl_shadow_ prefix.
		return 'thisismyurl_shadow_' . $action;
	}

	/**
	 * Auto-register this handler with WordPress AJAX hooks.
	 *
	 * Uses convention-based naming to derive the action from the class name.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function register() {
		$action = static::get_action();
		add_action( 'wp_ajax_' . $action, array( get_called_class(), 'handle' ) );
	}
}
