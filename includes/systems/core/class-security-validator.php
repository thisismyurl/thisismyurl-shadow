<?php
/**
 * Security Validator Utility
 *
 * Centralized security validation methods to reduce code duplication.
 * Consolidates nonce verification, capability checks, and permission errors.
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
 * Security Validator Class
 *
 * Provides reusable security validation methods for non-AJAX contexts.
 * For AJAX handlers, use AJAX_Handler_Base instead.
 *
 * @since 0.6095
 */
class Security_Validator {

	/**
	 * Verify user has required capability.
	 *
	 * Multisite-aware capability checking with standardized error handling.
	 *
	 * @since 0.6095
	 * @param  string $capability Required capability. Default 'manage_options'.
	 * @param  bool   $die        Whether to wp_die on failure. Default true.
	 * @return bool True if user has capability, false otherwise.
	 */
	public static function verify_capability( string $capability = 'manage_options', bool $die = true ): bool {
		// Check multisite network admin capability
		if ( is_multisite() && is_network_admin() ) {
			$capability = 'manage_network' === $capability ? 'manage_network_options' : $capability;
		}

		$has_capability = current_user_can( $capability );

		if ( ! $has_capability && $die ) {
			wp_die(
						esc_html( self::get_permission_error( $capability ) ),
				esc_html__( 'Permission Denied', 'thisismyurl-shadow' ),
				array( 'response' => 403 )
			);
		}

		return $has_capability;
	}

	/**
	 * Verify nonce for form submissions.
	 *
	 * @since 0.6095
	 * @param  string $action      Nonce action.
	 * @param  string $nonce_field Nonce field name. Default '_wpnonce'.
	 * @param  bool   $die         Whether to wp_die on failure. Default true.
	 * @return bool True if nonce is valid, false otherwise.
	 */
	public static function verify_nonce( string $action, string $nonce_field = '_wpnonce', bool $die = true ): bool {
		$nonce = isset( $_POST[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ) : '';

		$valid = wp_verify_nonce( $nonce, $action );

		if ( ! $valid && $die ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'thisismyurl-shadow' ),
				esc_html__( 'Security Error', 'thisismyurl-shadow' ),
				array( 'response' => 403 )
			);
		}

		return (bool) $valid;
	}

	/**
	 * Verify nonce and capability together.
	 *
	 * Common pattern for form submissions that require both checks.
	 *
	 * @since 0.6095
	 * @param  string $action      Nonce action.
	 * @param  string $capability  Required capability. Default 'manage_options'.
	 * @param  string $nonce_field Nonce field name. Default '_wpnonce'.
	 * @return bool True if both checks pass.
	 */
	public static function verify_request( string $action, string $capability = 'manage_options', string $nonce_field = '_wpnonce' ): bool {
		self::verify_nonce( $action, $nonce_field, true );
		self::verify_capability( $capability, true );
		return true;
	}

	/**
	 * Get standardized permission error message.
	 *
	 * @since 0.6095
	 * @param  string $capability Required capability that was missing.
	 * @return string Localized error message.
	 */
	public static function get_permission_error( string $capability = 'manage_options' ): string {
		$messages = array(
			'manage_options'         => __( 'You do not have permission to manage settings for this site.', 'thisismyurl-shadow' ),
			'manage_network_options' => __( 'You do not have permission to manage network settings.', 'thisismyurl-shadow' ),
			'edit_posts'             => __( 'You do not have permission to edit posts.', 'thisismyurl-shadow' ),
			'edit_pages'             => __( 'You do not have permission to edit pages.', 'thisismyurl-shadow' ),
			'edit_users'             => __( 'You do not have permission to edit users.', 'thisismyurl-shadow' ),
			'delete_users'           => __( 'You do not have permission to delete users.', 'thisismyurl-shadow' ),
		);

		return isset( $messages[ $capability ] )
			? $messages[ $capability ]
			: sprintf(
				/* translators: %s: capability name */
				__( 'You do not have the required permission: %s', 'thisismyurl-shadow' ),
				$capability
			);
	}

	/**
	 * Sanitize and validate email address.
	 *
	 * @since 0.6095
	 * @param  string $email Email to validate.
	 * @return string|false Sanitized email or false if invalid.
	 */
	public static function sanitize_email( string $email ) {
		$sanitized = sanitize_email( $email );
		return is_email( $sanitized ) ? $sanitized : false;
	}

}
