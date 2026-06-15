<?php

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Registry - Centralized WordPress Settings API Registration
 *
 * Registers all Shadow by Christopher Ross settings using proper WordPress Settings API.
 * Provides sanitization, validation, defaults, and proper WordPress integration.
 *
 * Philosophy Alignment:
 * - Commandment #8: Inspire Confidence - Uses native WordPress patterns
 * - Commandment #10: Beyond Pure - Conservative defaults for site settings
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 */
class Settings_Registry {
	/**
	 * Get a Shadow by Christopher Ross setting value.
	 *
	 * @since 0.6095
	 * @param  string $key     Setting key (with or without thisismyurl_shadow_ prefix).
	 * @param  mixed  $default Default value if not set.
	 * @return mixed Setting value.
	 */
	public static function get( string $key, $default = '' ) {
		return get_option( self::normalize_option_key( $key ), $default );
	}

	/**
	 * Get a boolean setting value.
	 *
	 * @since 0.6095
	 * @param  string $key     Setting key.
	 * @param  bool   $default Default fallback value.
	 * @return bool Setting value.
	 */
	public static function get_bool( string $key, bool $default = false ): bool {
		return (bool) self::get( $key, $default );
	}

	/**
	 * Get an integer setting value.
	 *
	 * @since 0.6095
	 * @param  string $key     Setting key.
	 * @param  int    $default Default fallback value.
	 * @return int Setting value.
	 */
	public static function get_int( string $key, int $default = 0 ): int {
		return (int) self::get( $key, $default );
	}

	/**
	 * Normalize an option key to the `thisismyurl_shadow_` namespace.
	 *
	 * @since 0.6095
	 * @param  string $key Setting key with or without prefix.
	 * @return string Normalized option key.
	 */
	public static function normalize_option_key( string $key ): string {
		return 0 === strpos( $key, 'thisismyurl_shadow_' ) ? $key : 'thisismyurl_shadow_' . $key;
	}

	/**
	 * Set a Shadow by Christopher Ross setting value.
	 *
	 * @since 0.6095
	 * @param  string $key   Setting key (with or without thisismyurl_shadow_ prefix).
	 * @param  mixed  $value Value to store.
	 * @return bool Whether the value was updated.
	 */
	public static function set( string $key, $value ): bool {
		return update_option( self::normalize_option_key( $key ), $value );
	}


	/**
	 * Register hooks
	 */
	public static function register(): void
	{
		add_action( 'init', array( __CLASS__, 'ensure_default_settings' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'register_all_settings' ) );
		add_action( 'update_option', array( __CLASS__, 'on_setting_updated' ), 10, 3 );
		add_action( 'add_option', array( __CLASS__, 'on_setting_added' ), 10, 2 );
	}

	/**
	 * Seed default settings for options that should be enabled out of the box.
	 *
	 * Uses add_option() so existing user choices are never overridden.
	 *
	 * @since 0.7055
	 * @return void
	 */
	public static function ensure_default_settings(): void {
		add_option( 'thisismyurl_shadow_cache_enabled', true );
		add_option( 'thisismyurl_shadow_cache_duration', 86400 );
	}

	/**
	 * Hook called when a setting is updated.
	 *
	 * @param string $option    Name of the updated option.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	public static function on_setting_updated( $option, $old_value, $value ): void {
		// Only fire for Shadow by Christopher Ross settings (check for prefix).
		// Use str_starts_with for PHP 8.0+, fallback to strpos for earlier versions.
		if ( function_exists( 'str_starts_with' ) ) {
			if ( ! str_starts_with( $option, 'thisismyurl_shadow_' ) ) {
				return;
			}
		} else {
			if ( 0 !== strpos( $option, 'thisismyurl_shadow_' ) ) {
				return;
			}
		}

		if ( $old_value === $value ) {
			return;
		}

		/**
		 * Fires when a Shadow by Christopher Ross setting is updated.
		 *
		 * @param string $option    Setting name.
		 * @param mixed  $old_value Previous value.
		 * @param mixed  $value     New value.
		 */
		do_action( 'thisismyurl_shadow_setting_updated', $option, $old_value, $value );

		/**
		 * Fires when a specific Shadow by Christopher Ross setting is updated.
		 *
		 * The dynamic portion of the hook name, $option, refers to the setting name.
		 * For example: thisismyurl_shadow_setting_updated_thisismyurl_debug_mode
		 *
		 * @param mixed $old_value Previous value.
		 * @param mixed $value     New value.
		 */
		do_action( "thisismyurl_shadow_setting_updated_{$option}", $old_value, $value );

		if ( ! self::is_settings_request() ) {
			return;
		}

		$label = self::get_setting_label( $option );
		$new_value = self::format_setting_value( $value );
		$details   = sprintf(
			/* translators: 1: setting label, 2: new value */
			__( 'Updated setting: %1$s is now %2$s.', 'thisismyurl-shadow' ),
			$label,
			$new_value
		);
		$metadata = array(
			'setting' => $option,
		);

		if ( self::can_log_value( $option ) ) {
			$metadata['value']     = $new_value;
			$metadata['old_value'] = self::format_setting_value( $old_value );
		}

		Activity_Logger::log( 'settings_changed', $details, 'settings', $metadata );
	}

	/**
	 * Hook called when a setting is added.
	 *
	 * @param string $option Name of the added option.
	 * @param mixed  $value  Value of the added option.
	 */
	public static function on_setting_added( $option, $value ): void {
		// Only fire for Shadow by Christopher Ross settings (check for prefix).
		// Use str_starts_with for PHP 8.0+, fallback to strpos for earlier versions.
		if ( function_exists( 'str_starts_with' ) ) {
			if ( ! str_starts_with( $option, 'thisismyurl_shadow_' ) ) {
				return;
			}
		} else {
			if ( 0 !== strpos( $option, 'thisismyurl_shadow_' ) ) {
				return;
			}
		}

		/**
		 * Fires when a Shadow by Christopher Ross setting is added.
		 *
		 * @param string $option Setting name.
		 * @param mixed  $value  Setting value.
		 */
		do_action( 'thisismyurl_shadow_setting_added', $option, $value );

		/**
		 * Fires when a specific Shadow by Christopher Ross setting is added.
		 *
		 * The dynamic portion of the hook name, $option, refers to the setting name.
		 *
		 * @param mixed $value Setting value.
		 */
		do_action( "thisismyurl_shadow_setting_added_{$option}", $value );

		if ( ! self::is_settings_request() ) {
			return;
		}

		$label = self::get_setting_label( $option );
		$new_value = self::format_setting_value( $value );
		$details   = sprintf(
			/* translators: 1: setting label, 2: new value */
			__( 'Updated setting: %1$s is now %2$s.', 'thisismyurl-shadow' ),
			$label,
			$new_value
		);
		$metadata = array(
			'setting' => $option,
		);

		if ( self::can_log_value( $option ) ) {
			$metadata['value'] = $new_value;
		}

		Activity_Logger::log( 'settings_changed', $details, 'settings', $metadata );
	}

	/**
	 * Check whether the update came from the General Settings page.
	 *
	 * @since 0.6095
	 * @return bool True when the referer matches the general settings page.
	 */
	private static function is_settings_request(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$referer = wp_get_referer();
		if ( empty( $referer ) ) {
			return false;
		}

		$referer_parts = wp_parse_url( $referer );
		if ( empty( $referer_parts['query'] ) ) {
			return false;
		}

		parse_str( $referer_parts['query'], $query_args );
		if ( empty( $query_args['page'] ) || 'thisismyurl-shadow' !== $query_args['page'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Build a human-friendly label for a setting.
	 *
	 * @since 0.6095
	 * @param string $option Option name.
	 * @return string Label for display.
	 */
	private static function get_setting_label( string $option ): string {
		$label = preg_replace( '/^thisismyurl_shadow_/', '', $option );
		$label = str_replace( array( '-', '_' ), ' ', (string) $label );
		$label = ucwords( $label );

		return (string) apply_filters( 'thisismyurl_shadow_setting_label', $label, $option );
	}

	/**
	 * Determine whether a setting value is safe to log.
	 *
	 * @since 0.6095
	 * @param string $option Option name.
	 * @return bool True when logging the value is allowed.
	 */
	private static function can_log_value( string $option ): bool {
		$blocked_fragments = array( 'api_key', 'secret', 'token', 'license' );
		$option_lower = strtolower( $option );

		foreach ( $blocked_fragments as $fragment ) {
			if ( false !== strpos( $option_lower, $fragment ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize setting values for logging.
	 *
	 * @since 0.6095
	 * @param mixed $value Setting value.
	 * @return string Normalized value.
	 */
	private static function format_setting_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Enabled', 'thisismyurl-shadow' ) : __( 'Disabled', 'thisismyurl-shadow' );
		}

		if ( is_array( $value ) ) {
			return __( 'Updated', 'thisismyurl-shadow' );
		}

		return (string) $value;
	}

	/**
	 * Register all Shadow by Christopher Ross settings with WordPress Settings API
	 *
	 * Each setting gets:
	 * - Type validation
	 * - Sanitization callback
	 * - Default value
	 * - REST API exposure control
	 */
	public static function register_all_settings(): void {

		// =================================================================
		// WORKFLOW SETTINGS GROUP
		// =================================================================

		register_setting(
			'thisismyurl_shadow_workflow_settings',
			'thisismyurl_shadow_email_verification_tokens',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_verification_tokens' ),
				'show_in_rest'      => false, // Security - tokens
				'description'       => __( 'Pending email verification tokens', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// GENERAL SETTINGS GROUP
		// =================================================================

		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_cache_enabled',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Enable diagnostic results caching', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_cache_duration',
			array(
				'type'              => 'integer',
				'default'           => 86400, // 24 hours
				'sanitize_callback' => 'absint',
				'show_in_rest'      => false,
				'description'       => __( 'Cache duration in seconds', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_debug_mode',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Enable debug logging', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_enable_theme_file_editor',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Allow access to the WordPress Theme File Editor', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_enable_plugin_file_editor',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Allow access to the WordPress Plugin File Editor', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_magic_link_expiry_notifications',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Send email notifications when magic links expire', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// DIAGNOSTIC & TREATMENT TOGGLES
		// =================================================================

		// Disabled diagnostic classes (fully-qualified class names)
		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_disabled_diagnostic_classes',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_class_list' ),
				'show_in_rest'      => false,
				'description'       => __( 'List of diagnostic classes disabled by admin', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// Per-diagnostic frequency overrides (class name => frequency string)
		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_diagnostic_frequency_overrides',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_frequency_overrides' ),
				'show_in_rest'      => false,
				'description'       => __( 'Per-diagnostic scan frequency overrides set by admin', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// Disabled treatment classes (fully-qualified class names)
		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_disabled_treatment_classes',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_class_list' ),
				'show_in_rest'      => false,
				'description'       => __( 'List of treatment classes disabled by admin', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// PERFORMANCE SETTINGS
		// =================================================================

		register_setting(
			'thisismyurl_shadow_settings',
			'thisismyurl_shadow_heartbeat_settings',
			array(
				'type'              => 'array',
				'default'           => array(
					'dashboard' => 60,
					'editor'    => 15,
					'frontend'  => 'default',
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize_heartbeat_settings' ),
				'show_in_rest'      => false,
				'description'       => __( 'WordPress Heartbeat API optimization settings', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// ACCESSIBILITY SETTINGS (Philosophy: Pillar 🌍 Accessibility First)
		// =================================================================

		register_setting(
			'thisismyurl_shadow_accessibility_settings',
			'thisismyurl_shadow_keyboard_nav_hints',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Show keyboard navigation hints and shortcuts (helps users who navigate without a mouse)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_accessibility_settings',
			'thisismyurl_shadow_screen_reader_optimization',
			array(
				'type'              => 'boolean',
				'default'           => false, // Auto-detect preferred
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Optimize interface for screen readers with enhanced labels (for blind and low-vision users)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_accessibility_settings',
			'thisismyurl_shadow_high_contrast_mode',
			array(
				'type'              => 'boolean',
				'default'           => false, // Respect OS preference
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Force high contrast colors for better visibility (WCAG AAA compliance)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_accessibility_settings',
			'thisismyurl_shadow_reduce_motion',
			array(
				'type'              => 'boolean',
				'default'           => false, // Respect prefers-reduced-motion
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Disable animations and transitions (helps with motion sensitivity and focus)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_accessibility_settings',
			'thisismyurl_shadow_admin_font_family',
			array(
				'type'              => 'string',
				'default'           => 'default',
				'sanitize_callback' => array( __CLASS__, 'sanitize_admin_font_family' ),
				'show_in_rest'      => false,
				'description'       => __( 'Readable admin font choice, including an optional focus-friendly stack inspired by fonts some ADHD users prefer', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_accessibility_settings',
			'thisismyurl_shadow_font_size_multiplier',
			array(
				'type'              => 'number',
				'default'           => 1.0,
				'sanitize_callback' => array( __CLASS__, 'sanitize_font_multiplier' ),
				'show_in_rest'      => false,
				'description'       => __( 'Text size adjustment (0.8 to 2.0, where 1.0 is standard size)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_accessibility_settings',
			'thisismyurl_shadow_simplified_ui',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Use simplified interface with fewer options (helps with cognitive load and focus)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_accessibility_settings',
			'thisismyurl_shadow_focus_indicators',
			array(
				'type'              => 'string',
				'default'           => 'standard',
				'sanitize_callback' => array( __CLASS__, 'sanitize_focus_style' ),
				'show_in_rest'      => false,
				'description'       => __( 'Focus indicator visibility (standard/enhanced/maximum)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// DEVELOPER SETTINGS (Philosophy: Commandment #12 Expandable)
		// =================================================================

		register_setting(
			'thisismyurl_shadow_developer_settings',
			'thisismyurl_shadow_developer_mode',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Enable developer mode (shows extension points, hooks, and API documentation)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_developer_settings',
			'thisismyurl_shadow_show_hooks',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Display available hooks and filters in admin interface', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_developer_settings',
			'thisismyurl_shadow_api_documentation_inline',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Show inline API documentation for extension development', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_developer_settings',
			'thisismyurl_shadow_extension_sandbox',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Enable extension testing sandbox (safe testing environment)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// CULTURAL SETTINGS (Philosophy: Pillar 🌐 Culturally Respectful)
		// =================================================================

		register_setting(
			'thisismyurl_shadow_cultural_settings',
			'thisismyurl_shadow_date_format_preference',
			array(
				'type'              => 'string',
				'default'           => 'wordpress', // Use WordPress site setting
				'sanitize_callback' => array( __CLASS__, 'sanitize_date_format' ),
				'show_in_rest'      => false,
				'description'       => __( 'Date format preference (wordpress/iso8601/us/eu/custom)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_cultural_settings',
			'thisismyurl_shadow_time_format_preference',
			array(
				'type'              => 'string',
				'default'           => 'wordpress', // Use WordPress site setting
				'sanitize_callback' => array( __CLASS__, 'sanitize_time_format' ),
				'show_in_rest'      => false,
				'description'       => __( 'Time format preference (12h/24h/wordpress)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_cultural_settings',
			'thisismyurl_shadow_number_format_preference',
			array(
				'type'              => 'string',
				'default'           => 'locale', // Auto-detect from locale
				'sanitize_callback' => array( __CLASS__, 'sanitize_number_format' ),
				'show_in_rest'      => false,
				'description'       => __( 'Number format (1,000.50 vs1.0,50)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_cultural_settings',
			'thisismyurl_shadow_rtl_interface',
			array(
				'type'              => 'string',
				'default'           => 'auto', // Auto-detect from language
				'sanitize_callback' => array( __CLASS__, 'sanitize_rtl_preference' ),
				'show_in_rest'      => false,
				'description'       => __( 'RTL interface direction (auto/force_ltr/force_rtl)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_cultural_settings',
			'thisismyurl_shadow_avoid_idioms',
			array(
				'type'              => 'boolean',
				'default'           => true, // Default to simple language
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Avoid idioms and culturally-specific phrases', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// LEARNING SETTINGS (Philosophy: Pillar 🎓 Learning Inclusive)
		// =================================================================

		register_setting(
			'thisismyurl_shadow_learning_settings',
			'thisismyurl_shadow_preferred_learning_style',
			array(
				'type'              => 'string',
				'default'           => 'mixed', // Offer all formats
				'sanitize_callback' => array( __CLASS__, 'sanitize_learning_style' ),
				'show_in_rest'      => false,
				'description'       => __( 'Preferred learning format (text/video/interactive/mixed)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_learning_settings',
			'thisismyurl_shadow_step_by_step_mode',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Break complex operations into step-by-step guides', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_learning_settings',
			'thisismyurl_shadow_show_examples',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Show real-world examples with explanations', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_learning_settings',
			'thisismyurl_shadow_adhd_friendly_mode',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'ADHD-friendly UI (clear priorities, progress bars, auto-save)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_learning_settings',
			'thisismyurl_shadow_dyslexia_friendly_font',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Use a dyslexia-friendly font stack with clearer letterforms and spacing', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// KPI TRACKING SETTINGS (Philosophy: Commandment #9 Everything Has KPI)
		// =================================================================

		register_setting(
			'thisismyurl_shadow_kpi_settings',
			'thisismyurl_shadow_track_feature_usage',
			array(
				'type'              => 'boolean',
				'default'           => true, // Default to tracking (anonymized)
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Track which features help you most (anonymous, helps us improve)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_kpi_settings',
			'thisismyurl_shadow_show_impact_metrics',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Show impact metrics (time saved, performance gains)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_kpi_settings',
			'thisismyurl_shadow_enable_value_tracking',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Track value delivered (money saved, issues prevented)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		// =================================================================
		// DEFENSIVE ENGINEERING SETTINGS (Philosophy: Pillar ⚙️ Murphy\'s Law)
		// =================================================================

		register_setting(
			'thisismyurl_shadow_defensive_settings',
			'thisismyurl_shadow_autosave_frequency',
			array(
				'type'              => 'integer',
				'default'           => 30, // 30 seconds
				'sanitize_callback' => array( __CLASS__, 'sanitize_autosave_frequency' ),
				'show_in_rest'      => false,
				'description'       => __( 'Auto-save frequency in seconds (10-300)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_defensive_settings',
			'thisismyurl_shadow_retry_failed_operations',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Automatically retry failed operations (network, database)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_defensive_settings',
			'thisismyurl_shadow_use_stale_cache',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Use stale cache when fresh data unavailable (graceful degradation)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_defensive_settings',
			'thisismyurl_shadow_enable_offline_mode',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Work offline when network unavailable (queue operations)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_defensive_settings',
			'thisismyurl_shadow_graceful_error_display',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
				'description'       => __( 'Show user-friendly errors (hide technical details)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);

		register_setting(
			'thisismyurl_shadow_defensive_settings',
			'thisismyurl_shadow_operation_timeout',
			array(
				'type'              => 'integer',
				'default'           => 30, // 30 seconds
				'sanitize_callback' => array( __CLASS__, 'sanitize_operation_timeout' ),
				'show_in_rest'      => false,
				'description'       => __( 'Operation timeout in seconds (5-300)', 'thisismyurl-shadow' ),
				'autoload'          => false,
			)
		);
	}

	/**
	 * Sanitize frequency value
	 *
	 * @param mixed $value Input value
	 * @return string Sanitized frequency
	 */
	public static function sanitize_frequency( $value ): string {
		$valid = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
		return in_array( $value, $valid, true ) ? $value : 'hourly';
	}

	/**
	 * Sanitize max treatments value
	 *
	 * @param mixed $value Input value
	 * @return int Sanitized value (1-20)
	 */
	public static function sanitize_max_treatments( $value ): int {
		$int = absint( $value );
		return min( max( $int, 1 ), 20 ); // Clamp between 1-20
	}

	/**
	 * Sanitize treatment whitelist
	 *
	 * @param mixed $value Input value
	 * @return array Sanitized treatment IDs
	 */
	public static function sanitize_treatment_whitelist( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		// Only allow alphanumeric, underscore, dash
		return array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
	}

	/**
	 * Sanitize verification tokens
	 *
	 * @param mixed $value Input value
	 * @return array Sanitized tokens
	 */
	public static function sanitize_verification_tokens( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		$now       = current_time( 'timestamp' );

		foreach ( $value as $token => $data ) {
			// Remove expired tokens during sanitization
			if ( isset( $data['expires'] ) && $data['expires'] < $now ) {
				continue;
			}

			$sanitized[ sanitize_key( $token ) ] = array(
				'email'   => sanitize_email( $data['email'] ?? '' ),
				'created' => absint( $data['created'] ?? 0 ),
				'expires' => absint( $data['expires'] ?? 0 ),
			);
		}
		return $sanitized;
	}

	/**
	 * Sanitize heartbeat settings
	 *
	 * @param mixed $value Input value
	 * @return array Sanitized settings
	 */
	public static function sanitize_heartbeat_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array(
				'dashboard' => 60,
				'editor'    => 15,
				'frontend'  => 'default',
			);
		}

		$defaults = array(
			'dashboard' => 60,
			'editor'    => 15,
			'frontend'  => 'default',
		);

		$sanitized = array();
		foreach ( $defaults as $key => $default ) {
			if ( 'frontend' === $key ) {
				// Frontend can be 'default', 'disabled', or integer
				$sanitized[ $key ] = 'disabled' === $value[ $key ] ? 'disabled' : ( is_numeric( $value[ $key ] ) ? absint( $value[ $key ] ) : 'default' );
			} else {
				// Dashboard and editor should be integers (15-120)
				// Developer confession: I have a constant fear of integers. It's just a variable mood.
				$int               = absint( $value[ $key ] ?? $default );
				$sanitized[ $key ] = min( max( $int, 15 ), 120 );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize followup days value
	 *
	 * @param mixed $value Input value
	 * @return int Sanitized value (1-90 days)
	 */
	public static function sanitize_followup_days( $value ): int {
		$int = absint( $value );
		return min( max( $int, 1 ), 90 ); // Clamp between 1-90 days
	}

	/**
	 * Sanitize a list of class identifiers (fully-qualified class names)
	 *
	 * Allows only letters, numbers, underscores, and namespace separators (\\).
	 * Discards any invalid entries.
	 *
	 * @param mixed $value Input value
	 * @return array Sanitized class names
	 */
	public static function sanitize_class_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $class ) {
			$raw = is_string( $class ) ? $class : '';
			// Allow namespace separators and typical class characters
			if ( preg_match( '/^[A-Za-z0-9_\\\\]+$/', $raw ) ) {
				$sanitized[] = $raw;
			}
		}

		// De-duplicate
		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Sanitize per-diagnostic frequency overrides map (class_name => frequency_string).
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value.
	 * @return array<string, string> Sanitized map.
	 */
	public static function sanitize_frequency_overrides( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid_frequencies = array( 'always', 'on-change', 'daily', 'weekly', 'monthly' );
		$sanitized         = array();

		foreach ( $value as $class_name => $frequency ) {
			$class_name = is_string( $class_name ) ? $class_name : '';
			$frequency  = is_string( $frequency )  ? $frequency  : '';

			// Validate class name contains only namespace-safe characters.
			if ( ! preg_match( '/^[A-Za-z0-9_\\\\]+$/', $class_name ) ) {
				continue;
			}

			if ( ! in_array( $frequency, $valid_frequencies, true ) ) {
				continue;
			}

			$sanitized[ $class_name ] = $frequency;
		}

		return $sanitized;
	}

	/**
	 * Sanitize admin font choice (Accessibility)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value.
	 * @return string Sanitized font identifier.
	 */
	public static function sanitize_admin_font_family( $value ): string {
		$valid = array( 'default', 'readable', 'lexend' );
		$value = sanitize_key( (string) $value );
		return in_array( $value, $valid, true ) ? $value : 'default';
	}

	/**
	 * Sanitize font size multiplier (Accessibility)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return float Sanitized value (0.8-2.0)
	 */
	public static function sanitize_font_multiplier( $value ): float {
		$float = (float) $value;
		return min( max( $float, 0.8 ), 2.0 ); // Clamp between 0.8-2.0
	}

	/**
	 * Sanitize focus indicator style (Accessibility)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return string Sanitized style
	 */
	public static function sanitize_focus_style( $value ): string {
		$valid = array( 'standard', 'enhanced', 'maximum' );
		$value = sanitize_key( (string) $value );
		return in_array( $value, $valid, true ) ? $value : 'standard';
	}

	/**
	 * Sanitize date format preference (Cultural)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return string Sanitized format
	 */
	public static function sanitize_date_format( $value ): string {
		$valid = array( 'wordpress', 'iso8601', 'us', 'eu', 'custom' );
		$value = sanitize_key( (string) $value );
		return in_array( $value, $valid, true ) ? $value : 'wordpress';
	}

	/**
	 * Sanitize time format preference (Cultural)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return string Sanitized format
	 */
	public static function sanitize_time_format( $value ): string {
		$valid = array( 'wordpress', '12h', '24h' );
		$value = sanitize_key( (string) $value );
		return in_array( $value, $valid, true ) ? $value : 'wordpress';
	}

	/**
	 * Sanitize notification severity threshold.
	 *
	 * @since 0.6095
	 * @param mixed $value Input value.
	 * @return string
	 */
	public static function sanitize_notification_severity( $value ): string {
		$valid = array( 'critical', 'high', 'medium', 'all' );
		$value = sanitize_key( (string) $value );

		return in_array( $value, $valid, true ) ? $value : 'critical';
	}

	/**
	 * Sanitize notification digest time (HH:MM).
	 *
	 * @since 0.6095
	 * @param mixed $value Input value.
	 * @return string
	 */
	public static function sanitize_notification_time( $value ): string {
		$value = sanitize_text_field( (string) $value );

		if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $value ) ) {
			return $value;
		}

		return '09:00';
	}

	/**
	 * Sanitize number format preference (Cultural)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return string Sanitized format
	 */
	public static function sanitize_number_format( $value ): string {
		$valid = array( 'locale', 'us', 'eu', 'custom' );
		$value = sanitize_key( (string) $value );
		return in_array( $value, $valid, true ) ? $value : 'locale';
	}

	/**
	 * Sanitize RTL preference (Cultural)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return string Sanitized preference
	 */
	public static function sanitize_rtl_preference( $value ): string {
		$valid = array( 'auto', 'force_ltr', 'force_rtl' );
		$value = sanitize_key( (string) $value );
		return in_array( $value, $valid, true ) ? $value : 'auto';
	}

	/**
	 * Sanitize learning style preference (Learning)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return string Sanitized style
	 */
	public static function sanitize_learning_style( $value ): string {
		$valid = array( 'text', 'video', 'interactive', 'mixed' );
		$value = sanitize_key( (string) $value );
		return in_array( $value, $valid, true ) ? $value : 'mixed';
	}

	/**
	 * Sanitize auto-save frequency (Defensive)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return int Sanitized frequency in seconds
	 */
	public static function sanitize_autosave_frequency( $value ): int {
		$value = absint( $value );
		return max( 10, min( 300, $value ) ); // Clamp between 10s and 5min
	}

	/**
	 * Sanitize operation timeout (Defensive)
	 *
	 * @since 0.6095
	 * @param  mixed $value Input value
	 * @return int Sanitized timeout in seconds
	 */
	public static function sanitize_operation_timeout( $value ): int {
		$value = absint( $value );
		return max( 5, min( 300, $value ) ); // Clamp between 5s and 5min
	}

	// =================================================================
	// CULTURAL SETTINGS HELPERS (Philosophy: Pillar 🌐)
	// =================================================================

	/**
	 * Get date format preference
	 *
	 * @since 0.6095
	 * @return string Date format preference
	 */
	public static function get_date_format_preference(): string {
		return (string) get_option( 'thisismyurl_shadow_date_format_preference', 'wordpress' );
	}

	/**
	 * Get time format preference
	 *
	 * @since 0.6095
	 * @return string Time format preference
	 */
	public static function get_time_format_preference(): string {
		return (string) get_option( 'thisismyurl_shadow_time_format_preference', 'wordpress' );
	}

	/**
	 * Get number format preference
	 *
	 * @since 0.6095
	 * @return string Number format preference
	 */
	public static function get_number_format_preference(): string {
		return (string) get_option( 'thisismyurl_shadow_number_format_preference', 'locale' );
	}

	/**
	 * Get RTL interface preference
	 *
	 * @since 0.6095
	 * @return string RTL preference (auto/force_ltr/force_rtl)
	 */
	public static function get_rtl_preference(): string {
		return (string) get_option( 'thisismyurl_shadow_rtl_interface', 'auto' );
	}

	/**
	 * Check if interface should avoid idioms
	 *
	 * @since 0.6095
	 * @return bool True if should avoid cultural idioms
	 */
	public static function should_avoid_idioms(): bool {
		return (bool) get_option( 'thisismyurl_shadow_avoid_idioms', true );
	}

	// =================================================================
	// LEARNING SETTINGS HELPERS (Philosophy: Pillar 🎓)
	// =================================================================

	/**
	 * Get preferred learning style
	 *
	 * @since 0.6095
	 * @return string Learning style (text/video/interactive/mixed)
	 */
	public static function get_learning_style(): string {
		return (string) get_option( 'thisismyurl_shadow_preferred_learning_style', 'mixed' );
	}

	/**
	 * Check if step-by-step mode is enabled
	 *
	 * @since 0.6095
	 * @return bool True if step-by-step mode enabled
	 */
	public static function is_step_by_step_mode(): bool {
		return (bool) get_option( 'thisismyurl_shadow_step_by_step_mode', false );
	}

	/**
	 * Check if examples should be shown
	 *
	 * @since 0.6095
	 * @return bool True if examples enabled
	 */
	public static function show_examples(): bool {
		return (bool) get_option( 'thisismyurl_shadow_show_examples', true );
	}

	/**
	 * Check if ADHD-friendly mode is enabled
	 *
	 * @since 0.6095
	 * @return bool True if ADHD support enabled
	 */
	public static function is_adhd_friendly_mode(): bool {
		return (bool) get_option( 'thisismyurl_shadow_adhd_friendly_mode', false );
	}

	/**
	 * Check if dyslexia-friendly font is enabled
	 *
	 * @since 0.6095
	 * @return bool True if dyslexia font enabled
	 */
	public static function use_dyslexia_font(): bool {
		return (bool) get_option( 'thisismyurl_shadow_dyslexia_friendly_font', false );
	}

	// =================================================================
	// KPI TRACKING SETTINGS HELPERS (Philosophy: Commandment #9)
	// =================================================================

	/**
	 * Check if feature usage tracking is enabled
	 *
	 * @since 0.6095
	 * @return bool True if tracking enabled
	 */
	public static function track_feature_usage(): bool {
		return (bool) get_option( 'thisismyurl_shadow_track_feature_usage', true );
	}

	/**
	 * Check if impact metrics should be shown
	 *
	 * @since 0.6095
	 * @return bool True if metrics should be shown
	 */
	public static function show_impact_metrics(): bool {
		return (bool) get_option( 'thisismyurl_shadow_show_impact_metrics', true );
	}

	/**
	 * Check if value tracking is enabled
	 *
	 * @since 0.6095
	 * @return bool True if value tracking enabled
	 */
	public static function enable_value_tracking(): bool {
		return (bool) get_option( 'thisismyurl_shadow_enable_value_tracking', true );
	}

	// =================================================================
	// DEFENSIVE ENGINEERING SETTINGS HELPERS (Philosophy: Pillar ⚙️)
	// =================================================================

	/**
	 * Get auto-save frequency in seconds
	 *
	 * @since 0.6095
	 * @return int Auto-save frequency (10-300 seconds)
	 */
	public static function get_autosave_frequency(): int {
		return (int) get_option( 'thisismyurl_shadow_autosave_frequency', 30 );
	}

	/**
	 * Check if retry logic is enabled
	 *
	 * @since 0.6095
	 * @return bool True if should retry failed operations
	 */
	public static function should_retry_failed_operations(): bool {
		return (bool) get_option( 'thisismyurl_shadow_retry_failed_operations', true );
	}

	/**
	 * Check if stale cache should be used
	 *
	 * @since 0.6095
	 * @return bool True if stale cache acceptable
	 */
	public static function use_stale_cache(): bool {
		return (bool) get_option( 'thisismyurl_shadow_use_stale_cache', true );
	}

	/**
	 * Check if offline mode is enabled
	 *
	 * @since 0.6095
	 * @return bool True if offline mode enabled
	 */
	public static function enable_offline_mode(): bool {
		return (bool) get_option( 'thisismyurl_shadow_enable_offline_mode', true );
	}

	/**
	 * Check if graceful error display is enabled
	 *
	 * @since 0.6095
	 * @return bool True if should show user-friendly errors
	 */
	public static function graceful_error_display(): bool {
		return (bool) get_option( 'thisismyurl_shadow_graceful_error_display', true );
	}

	/**
	 * Get operation timeout in seconds
	 *
	 * @since 0.6095
	 * @return int Operation timeout (5-300 seconds)
	 */
	public static function get_operation_timeout(): int {
		return (int) get_option( 'thisismyurl_shadow_operation_timeout', 30 );
	}

	// =================================================================
	// ACCESSIBILITY HELPERS (Philosophy: Pillar 🌍)
	// =================================================================

	/**
	 * Check if keyboard navigation hints are enabled
	 *
	 * @since 0.6095
	 * @return bool Whether keyboard hints are enabled
	 */
	public static function is_keyboard_hints_enabled(): bool {
		return (bool) get_option( 'thisismyurl_shadow_keyboard_nav_hints', true );
	}

	/**
	 * Check if screen reader optimization is enabled
	 *
	 * @since 0.6095
	 * @return bool Whether screen reader optimization is enabled
	 */
	public static function is_screen_reader_optimized(): bool {
		return (bool) get_option( 'thisismyurl_shadow_screen_reader_optimization', false );
	}

	/**
	 * Get font size multiplier
	 *
	 * @since 0.6095
	 * @return float Font size multiplier (0.8-2.0)
	 */
	public static function get_font_multiplier(): float {
		return (float) get_option( 'thisismyurl_shadow_font_size_multiplier',1.0 );
	}

	/**
	 * Check if simplified UI is enabled
	 *
	 * @since 0.6095
	 * @return bool Whether simplified UI is enabled
	 */
	public static function is_simplified_ui(): bool {
		return (bool) get_option( 'thisismyurl_shadow_simplified_ui', false );
	}

	/**
	 * Check if high contrast mode is enabled
	 *
	 * @since 0.6095
	 * @return bool Whether high contrast is enabled
	 */
	public static function is_high_contrast(): bool {
		return (bool) get_option( 'thisismyurl_shadow_high_contrast_mode', false );
	}

	/**
	 * Check if motion reduction is enabled
	 *
	 * @since 0.6095
	 * @return bool Whether reduced motion is enabled
	 */
	public static function is_motion_reduced(): bool {
		return (bool) get_option( 'thisismyurl_shadow_reduce_motion', false );
	}

	/**
	 * Get focus indicator style
	 *
	 * @since 0.6095
	 * @return string Focus style (standard/enhanced/maximum)
	 */
	public static function get_focus_style(): string {
		return get_option( 'thisismyurl_shadow_focus_indicators', 'enhanced' );
	}

	// =================================================================
	// DEVELOPER MODE HELPERS (Philosophy: Commandment #12)
	// =================================================================

	/**
	 * Check if developer mode is enabled
	 *
	 * @since 0.6095
	 * @return bool Whether developer mode is enabled
	 */
	public static function is_developer_mode(): bool {
		return (bool) get_option( 'thisismyurl_shadow_developer_mode', false );
	}

	/**
	 * Check if hooks should be displayed
	 *
	 * @since 0.6095
	 * @return bool Whether hooks should be shown
	 */
	public static function should_show_hooks(): bool {
		return self::is_developer_mode() && (bool) get_option( 'thisismyurl_shadow_show_hooks', false );
	}

	/**
	 * Check if inline API documentation is enabled
	 *
	 * @since 0.6095
	 * @return bool Whether inline docs are enabled
	 */
	public static function is_inline_docs_enabled(): bool {
		return self::is_developer_mode() && (bool) get_option( 'thisismyurl_shadow_api_documentation_inline', false );
	}

	/**
	 * Check if extension sandbox is enabled
	 *
	 * @since 0.6095
	 * @return bool Whether sandbox is enabled
	 */
	public static function is_sandbox_enabled(): bool {
		return self::is_developer_mode() && (bool) get_option( 'thisismyurl_shadow_extension_sandbox', false );
	}
}

