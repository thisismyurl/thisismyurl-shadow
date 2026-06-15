<?php
/**
 * AJAX Handler: Save Setting
 *
 * Saves a single Shadow by Christopher Ross setting via AJAX for auto-save UI flows.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Admin/AJAX
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Admin\AJAX;

use ThisIsMyURL\Shadow\Core\AJAX_Handler_Base;
use ThisIsMyURL\Shadow\Core\Settings_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save Setting AJAX Handler
 *
 * Persists a single setting update from admin pages without a full page reload.
 *
 * @since 0.6095
 */
class Save_Setting_Handler extends AJAX_Handler_Base {

	/**
	 * Handle the save setting request.
	 *
	 * @since 0.6095
	 * @return void Dies after sending JSON response.
	 */
	public static function handle(): void {
		self::verify_request( 'thisismyurl_shadow_admin', 'manage_options' );

		$option = self::get_post_param( 'option', 'text', '', true );
		$value  = self::get_post_param( 'value', 'raw', '' );

		$option = sanitize_key( $option );
		if ( '' === $option || 0 !== strpos( $option, 'thisismyurl_shadow_' ) ) {
			self::send_error( __( 'That setting cannot be updated here.', 'thisismyurl-shadow' ) );
		}

		$registered = get_registered_settings();
		if ( ! isset( $registered[ $option ] ) ) {
			self::send_error( __( 'That setting is not available.', 'thisismyurl-shadow' ) );
		}

		$setting_schema    = $registered[ $option ];
		$type              = isset( $setting_schema['type'] ) ? $setting_schema['type'] : 'string';
		$sanitize_callback = isset( $setting_schema['sanitize_callback'] ) ? $setting_schema['sanitize_callback'] : null;

		if ( 'array' === $type && is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			}
		}

		if ( is_callable( $sanitize_callback ) ) {
			$value = call_user_func( $sanitize_callback, $value );
		} elseif ( is_array( $value ) ) {
			$value = array_map( 'sanitize_text_field', $value );
		} elseif ( 'boolean' === $type ) {
			$value = rest_sanitize_boolean( $value );
		} elseif ( 'integer' === $type ) {
			$value = absint( $value );
		} else {
			$value = sanitize_text_field( (string) $value );
		}

		Settings_Registry::set( $option, $value );

		self::send_success(
			array(
				'message' => __( 'Saved automatically.', 'thisismyurl-shadow' ),
			)
		);
	}
}

// Register AJAX handler.
add_action( 'wp_ajax_thisismyurl_shadow_save_setting', array( 'ThisIsMyURL\Shadow\Admin\AJAX\Save_Setting_Handler', 'handle' ) );
