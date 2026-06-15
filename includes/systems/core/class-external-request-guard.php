<?php
/**
 * External Request Guard
 *
 * Centralized permission checks for outbound HTTP requests.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Core
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

/**
 * External_Request_Guard Class
 *
 * Enforces site request policy before optional outbound requests.
 *
 * @since 0.6095
 */
class External_Request_Guard {

	/**
	 * Option used to allow non-essential outbound requests.
	 */
	private const OPTION_ALLOW_EXTERNAL_REQUESTS = 'thisismyurl_shadow_allow_external_requests';

	/**
	 * Filter used to override request policy.
	 */
	private const FILTER_ALLOW_EXTERNAL_REQUEST = 'thisismyurl_shadow_allow_external_request';

	/**
	 * Filter used to allow trusted hosts without a global opt-in.
	 */
	private const FILTER_ALLOWED_HOSTS = 'thisismyurl_shadow_allowed_external_request_hosts';

	/**
	 * Check if an outbound request is allowed.
	 *
	 * @since  0.6095
	 * @param  string   $purpose Optional. Purpose key for the request.
	 * @param  int|null $user_id Optional. User context. Defaults to current user.
	 * @param  string   $url     Optional. Absolute URL being requested.
	 * @return bool True when request is allowed.
	 */
	public static function is_allowed( string $purpose = 'general', ?int $user_id = null, string $url = '' ): bool {
		$user_id = null === $user_id ? get_current_user_id() : $user_id;
		$url     = trim( $url );

		if ( '' !== $url ) {
			if ( self::is_same_site_url( $url ) || self::is_trusted_host( $url ) ) {
				return true;
			}
		}

		$allowed = (bool) get_option( self::OPTION_ALLOW_EXTERNAL_REQUESTS, false );

		return (bool) apply_filters(
			self::FILTER_ALLOW_EXTERNAL_REQUEST,
			$allowed,
			sanitize_key( $purpose ),
			(int) $user_id,
			$url
		);
	}

	/**
	 * Get required-permission message for blocked requests.
	 *
	 * @since  0.6095
	 * @param  string $context Optional. Human-readable request context.
	 * @return string Message to show in UI/API responses.
	 */
	public static function get_denied_message( string $context = '' ): string {
		if ( '' !== $context ) {
			return sprintf(
				/* translators: %s: feature/request context */
				__( '%s needs outbound requests enabled by site policy to continue.', 'thisismyurl-shadow' ),
				$context
			);
		}

		return __( 'This action needs outbound requests enabled by site policy to continue.', 'thisismyurl-shadow' );
	}

	/**
	 * Determine whether a URL points back to the current WordPress site.
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	private static function is_same_site_url( string $url ): bool {
		$request_host = self::normalize_host( wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $request_host ) {
			return true;
		}

		$site_hosts = array_filter(
			array_unique(
				array(
					self::normalize_host( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ),
					self::normalize_host( wp_parse_url( site_url( '/' ), PHP_URL_HOST ) ),
				)
			)
		);

		return in_array( $request_host, $site_hosts, true );
	}

	/**
	 * Determine whether a URL belongs to a trusted host.
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	private static function is_trusted_host( string $url ): bool {
		$request_host = self::normalize_host( wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $request_host ) {
			return false;
		}

		$allowed_hosts = (array) apply_filters(
			self::FILTER_ALLOWED_HOSTS,
			array(
				'api.wordpress.org',
			)
		);

		$allowed_hosts = array_map( array( __CLASS__, 'normalize_host' ), $allowed_hosts );

		return in_array( $request_host, $allowed_hosts, true );
	}

	/**
	 * Normalize a host name for policy comparison.
	 *
	 * @param string|null $host Host name.
	 * @return string
	 */
	private static function normalize_host( ?string $host ): string {
		if ( ! is_string( $host ) ) {
			return '';
		}

		$host = strtolower( trim( $host ) );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

}
