<?php
/**
 * AJAX: Save Scan Configuration
 *
 * Saves a single key within the Shadow by Christopher Ross scan frequency / scan config option
 * and reschedules the diagnostic cron event when necessary.
 *
 * @package ThisIsMyURL\Shadow\Admin
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Admin;

use ThisIsMyURL\Shadow\Core\AJAX_Handler_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save Scan Config AJAX Handler
 */
class AJAX_Save_Scan_Config extends AJAX_Handler_Base {

	/**
	 * Allowed key/type pairs for the scan config option.
	 *
	 * @var array<string, string>
	 */
	private static $allowed_keys = array(
		'frequency'             => 'string',
		'scan_time'             => 'string',
		'run_diagnostics'       => 'bool',
		'run_treatments'        => 'bool',
		'scan_on_plugin_update' => 'bool',
		'scan_on_theme_update'  => 'bool',
	);

	/**
	 * Handle the request.
	 *
	 * @return void
	 */
	public static function handle(): void {
		self::verify_request( 'thisismyurl_shadow_admin', 'manage_options' );

		$key   = self::get_post_param( 'key', 'text', '', true );
		$value = self::get_post_param( 'value', 'text', '' );

		$key = sanitize_key( $key );

		if ( ! isset( self::$allowed_keys[ $key ] ) ) {
			self::send_error( __( 'Invalid scan config key.', 'thisismyurl-shadow' ) );
			return;
		}

		$type = self::$allowed_keys[ $key ];
		if ( 'bool' === $type ) {
			$value = rest_sanitize_boolean( $value );
		} elseif ( 'string' === $type ) {
			$value = sanitize_text_field( (string) $value );
		} else {
			$value = sanitize_text_field( (string) $value );
		}

		// Validate frequency value.
		if ( 'frequency' === $key ) {
			$allowed_freqs = array( 'manual', 'hourly', 'daily', 'weekly' );
			if ( ! in_array( $value, $allowed_freqs, true ) ) {
				self::send_error( __( 'Invalid frequency value.', 'thisismyurl-shadow' ) );
				return;
			}
		}

		// Validate scan_time value (HH:MM).
		if ( 'scan_time' === $key ) {
			if ( ! preg_match( '/^\d{2}:\d{2}$/', (string) $value ) ) {
				self::send_error( __( 'Invalid time format.', 'thisismyurl-shadow' ) );
				return;
			}
		}

		$scan_frequency_manager = '\ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager';
		$updated                = false;

		if ( class_exists( $scan_frequency_manager ) ) {
			$updated = $scan_frequency_manager::update_setting( $key, $value );
		} else {
			// Fallback: persist directly without cron reschedule.
			$option_key = 'thisismyurl_shadow_scan_frequency_settings';
			$config     = get_option( $option_key, array() );
			if ( ! is_array( $config ) ) {
				$config = array();
			}
			$config[ $key ] = $value;
			$updated        = update_option( $option_key, $config );
		}

		if ( false === $updated ) {
			// update_option returns false when value is unchanged — treat as success.
		}

		self::send_success( array( 'key' => $key, 'value' => $value ) );
	}
}

add_action(
	'wp_ajax_thisismyurl_shadow_save_scan_config',
	array( '\ThisIsMyURL\Shadow\Admin\AJAX_Save_Scan_Config', 'handle' )
);
