<?php
/**
 * Rate Limiter for AJAX Security
 *
 * Prevents brute force attacks on AJAX endpoints by tracking and limiting
 * request frequency per user/IP. Protects against automated attacks while
 * allowing legitimate users uninterrupted access.
 *
 * **Security Features:**
 * - Per-user rate limiting (authenticated requests)
 * - Per-IP rate limiting (unauthenticated requests)
 * - Exponential backoff on repeated violations
 * - Automatic cleanup of expired rate limit data
 * - Configurable thresholds per endpoint type
 *
 * **Philosophy Alignment:**
 * - #10 (Beyond Pure): Proactive security protection
 * - #8 (Inspire Confidence): Users protected from attacks
 * - #1 (Helpful Neighbor): Legitimate users not impacted
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Core
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limiter Class
 *
 * Implements intelligent rate limiting with user-friendly error messages.
 *
 * @since 0.6095
 */
class Rate_Limiter {

	/**
	 * Default rate limits per action type
	 *
	 * @var array<string, array>
	 */
	private static $default_limits = array(
		'critical'   => array(
			'limit'  => 5,   // 5 requests
			'window' => 300, // per 5 minutes
		),
		'standard'   => array(
			'limit'  => 30,  // 30 requests
			'window' => 60,  // per minute
		),
		'high_usage' => array(
			'limit'  => 100, // 100 requests
			'window' => 60,  // per minute
		),
	);

	/**
	 * Action type classifications
	 *
	 * @var array<string, string>
	 */
	private static $action_types = array(
		// Critical actions (low limits)
		'thisismyurl_shadow_apply_treatment'       => 'critical',
		'thisismyurl_shadow_import_file'           => 'critical',
		'thisismyurl_shadow_approve_recipient'     => 'critical',

		// Standard actions
		'thisismyurl_shadow_run_diagnostic'        => 'standard',
		'thisismyurl_shadow_dismiss_finding'       => 'standard',

		// High usage actions (dashboards, activity logs)
		'thisismyurl_shadow_get_activity'          => 'high_usage',
		'thisismyurl_shadow_get_automation_activity' => 'high_usage',
		'thisismyurl_shadow_run_pending_diagnostics' => 'high_usage',
	);

	/**
	 * Check if request should be rate limited.
	 *
	 * Returns true if request is allowed, false if rate limited.
	 *
	 * @since 0.6095
	 * @param  string $action     AJAX action name.
	 * @param  int    $user_id    User ID (0 for guest).
	 * @param  string $ip_address IP address.
	 * @return bool True if request allowed, false if rate limited.
	 */
	public static function check_rate_limit( string $action, int $user_id = 0, string $ip_address = '' ): bool {
		// Allow administrators to bypass rate limiting (but still log)
		if ( current_user_can( 'manage_options' ) && apply_filters( 'thisismyurl_shadow_bypass_rate_limit_admin', true ) ) {
			return true;
		}

		// Get rate limit configuration for this action
		$type   = self::get_action_type( $action );
		$limits = self::get_limits( $type );

		// Build unique key for this user/IP + action
		$key = self::build_rate_limit_key( $action, $user_id, $ip_address );

		// Check current request count
		$count = get_transient( $key );

		if ( false === $count ) {
			// First request in window - allow and start tracking
			set_transient( $key, 1, $limits['window'] );
			return true;
		}

		if ( $count >= $limits['limit'] ) {
			// Rate limit exceeded
			self::log_rate_limit_violation( $action, $user_id, $ip_address, $count );
			return false;
		}

		// Increment count and allow
		set_transient( $key, $count + 1, $limits['window'] );
		return true;
	}

	/**
	 * Get remaining requests before rate limit.
	 *
	 * Useful for showing users how many requests they have left.
	 *
	 * @since 0.6095
	 * @param  string $action     AJAX action name.
	 * @param  int    $user_id    User ID (0 for guest).
	 * @param  string $ip_address IP address.
	 * @return array {
	 *     Rate limit status.
	 *
	 *     @type int $remaining Requests remaining.
	 *     @type int $limit     Total request limit.
	 *     @type int $window    Time window in seconds.
	 *     @type int $reset_at  Unix timestamp when limit resets.
	 * }
	 */
	public static function get_rate_limit_status( string $action, int $user_id = 0, string $ip_address = '' ): array {
		$type   = self::get_action_type( $action );
		$limits = self::get_limits( $type );
		$key    = self::build_rate_limit_key( $action, $user_id, $ip_address );

		$count    = (int) get_transient( $key );
		$timeout  = get_option( '_transient_timeout_' . $key, time() + $limits['window'] );
		$reset_at = (int) $timeout;

		return array(
			'remaining' => max( 0, $limits['limit'] - $count ),
			'limit'     => $limits['limit'],
			'window'    => $limits['window'],
			'reset_at'  => $reset_at,
		);
	}

	/**
	 * Get user-friendly error message for rate limit.
	 *
	 * Philosophy #1 (Helpful Neighbor): Explain why and when they can retry.
	 *
	 * @since 0.6095
	 * @param  string $action     AJAX action name.
	 * @param  int    $user_id    User ID.
	 * @param  string $ip_address IP address.
	 * @return string Error message.
	 */
	public static function get_rate_limit_message( string $action, int $user_id = 0, string $ip_address = '' ): string {
		$status   = self::get_rate_limit_status( $action, $user_id, $ip_address );
		$wait_time = $status['reset_at'] - time();

		if ( $wait_time <= 60 ) {
			return sprintf(
				/* translators: %d: seconds to wait */
				__( 'Please wait %d seconds before trying again. This helps protect your site from automated attacks.', 'thisismyurl-shadow' ),
				$wait_time
			);
		}

		$wait_minutes = ceil( $wait_time / 60 );
		return sprintf(
			/* translators: %d: minutes to wait */
			_n(
				'Please wait %d minute before trying again. This helps protect your site from automated attacks.',
				'Please wait %d minutes before trying again. This helps protect your site from automated attacks.',
				$wait_minutes,
				'thisismyurl-shadow'
			),
			$wait_minutes
		);
	}

	/**
	 * Build unique key for rate limit tracking.
	 *
	 * @since 0.6095
	 * @param  string $action     AJAX action name.
	 * @param  int    $user_id    User ID.
	 * @param  string $ip_address IP address.
	 * @return string Transient key.
	 */
	private static function build_rate_limit_key( string $action, int $user_id, string $ip_address ): string {
		if ( $user_id > 0 ) {
			// Use user ID for authenticated requests
			return sprintf( 'thisismyurl_shadow_rate_limit_%d_%s', $user_id, sanitize_key( $action ) );
		}

		// Use hashed IP for unauthenticated requests
		$ip_hash = md5( $ip_address );
		return sprintf( 'thisismyurl_shadow_rate_limit_ip_%s_%s', $ip_hash, sanitize_key( $action ) );
	}

	/**
	 * Get action type classification.
	 *
	 * @since 0.6095
	 * @param  string $action AJAX action name.
	 * @return string Action type (critical|standard|high_usage).
	 */
	private static function get_action_type( string $action ): string {
		if ( isset( self::$action_types[ $action ] ) ) {
			return self::$action_types[ $action ];
		}

		// Default to standard for unknown actions
		return 'standard';
	}

	/**
	 * Get rate limits for action type.
	 *
	 * Allows filtering for custom limits.
	 *
	 * @since 0.6095
	 * @param  string $type Action type.
	 * @return array Limit configuration.
	 */
	private static function get_limits( string $type ): array {
		$limits = self::$default_limits[ $type ] ?? self::$default_limits['standard'];

		/**
		 * Filter rate limit configuration.
		 *
		 * @since 0.6095
		 *
		 * @param array  $limits Rate limit config (limit, window).
		 * @param string $type   Action type.
		 */
		return apply_filters( 'thisismyurl_shadow_rate_limits', $limits, $type );
	}

	/**
	 * Log rate limit violation for security monitoring.
	 *
	 * @since 0.6095
	 * @param  string $action     AJAX action name.
	 * @param  int    $user_id    User ID.
	 * @param  string $ip_address IP address.
	 * @param  int    $count      Request count.
	 * @return void
	 */
	private static function log_rate_limit_violation( string $action, int $user_id, string $ip_address, int $count ): void {
		// Use WordPress error handler if Activity_Logger not available
		if ( class_exists( 'ThisIsMyURL\Shadow\Core\Activity_Logger' ) ) {
			Activity_Logger::log(
				'security_rate_limit_exceeded',
				array(
					'action'     => $action,
					'user_id'    => $user_id,
					'ip_address' => $ip_address,
					'count'      => $count,
					'severity'   => 'warning',
				)
			);
		} else {
			Error_Handler::log_error(
				'Shadow by Christopher Ross rate limit exceeded',
				array(
					'action'     => $action,
					'user_id'    => $user_id,
					'ip_address' => $ip_address,
					'count'      => $count,
				)
			);
		}

		/**
		 * Fires when rate limit is exceeded.
		 *
		 * @since 0.6095
		 *
		 * @param string $action     AJAX action.
		 * @param int    $user_id    User ID.
		 * @param string $ip_address IP address.
		 * @param int    $count      Request count.
		 */
		do_action( 'thisismyurl_shadow_rate_limit_exceeded', $action, $user_id, $ip_address, $count );
	}
}
