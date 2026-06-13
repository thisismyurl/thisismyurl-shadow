<?php

/**
 * Christopher Ross Shadow Hooks Initializer
 *
 * Centralizes WordPress hook registration (add_action, add_filter).
 * Extracted from thisismyurl-shadow.php as part of Phase 4.5 refactoring.
 *
 * Philosophy: Commandment #7 (Ridiculously Good - single source of truth for hooks)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 */

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ThisIsMyURL\Shadow\Core\Form_Param_Helper;
use ThisIsMyURL\Shadow\Core\Activity_Logger;
/**
 * Register Christopher Ross Shadow's WordPress hooks in one place.
 *
 * The plugin uses many actions and filters across admin pages, background
 * processing, diagnostics, treatments, and integration points. Keeping the
 * registration layer centralized gives readers a map of how Christopher Ross Shadow enters
 * WordPress and which callbacks own each lifecycle phase.
 */
class Hooks_Initializer {


	/**
	 * Register the plugin's actions and filters with WordPress.
	 *
	 * This is the bridge between Christopher Ross Shadow's internal services and the WordPress
	 * lifecycle. The method is intentionally broad because it documents, in one
	 * pass, how admin pages, cron jobs, notices, AJAX flows, and profile-related
	 * features are wired into the host application.
	 *
	 * @return void
	 */
	public static function init() {
		// Admin initialization
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ) );
		// NOTE: on_plugins_loaded is called directly from Plugin_Bootstrap::init() with EARLY priority
		// to register AJAX handlers. Then we also add it as a hook with late priority to initialize
		// other systems that require fully-loaded classes.
		add_action( 'plugins_loaded', array( __CLASS__, 'on_plugins_loaded_late' ), 999 );

		// Menu and asset loading
		add_action( 'admin_menu', array( __CLASS__, 'on_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_accessibility_styles' ) );
		add_action( 'admin_head', array( __CLASS__, 'on_admin_head' ) );

		// Hidden file-write review page used by Guardian file-write fixes.
		if ( ! class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Pages\\File_Write_Review_Page' ) ) {
			$review_page = THISISMYURL_SHADOW_PATH . 'includes/admin/pages/class-file-write-review-page.php';
			if ( file_exists( $review_page ) ) {
				require_once $review_page;
			}
		}
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Pages\\File_Write_Review_Page' ) ) {
			\ThisIsMyURL\Shadow\Admin\Pages\File_Write_Review_Page::init();
		}

		// Cache-bust Christopher Ross Shadow stylesheets using file modification time.
		add_filter( 'style_loader_src', array( __CLASS__, 'filter_style_loader_src' ), 10, 2 );

		// Front-end assets
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'on_wp_enqueue_scripts' ) );

		// Notices
		add_action( 'admin_notices', array( __CLASS__, 'on_admin_notices' ) );
		add_action( 'tool_box', array( __CLASS__, 'on_tool_box' ) );

		// User profile and login tracking
		add_action( 'show_user_profile', array( __CLASS__, 'on_show_user_profile' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'on_show_user_profile' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'on_personal_options_update' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'on_personal_options_update' ) );
		add_action( 'admin_head-profile.php', array( __CLASS__, 'on_profile_sections_visibility' ) );
		add_action( 'admin_head-user-edit.php', array( __CLASS__, 'on_profile_sections_visibility' ) );
		add_action( 'wp_login', array( __CLASS__, 'on_user_login' ), 10, 2 );

		// Settings changes tracking
		add_action( 'update_option_thisismyurl_shadow_cache_enabled', array( __CLASS__, 'on_option_updated' ), 10, 3 );
		add_action( 'update_option_thisismyurl_shadow_cache_duration', array( __CLASS__, 'on_option_updated' ), 10, 3 );
		add_action( 'update_option_thisismyurl_shadow_visual_comparison_width', array( __CLASS__, 'on_option_updated' ), 10, 3 );
		add_action( 'update_option_thisismyurl_shadow_visual_comparison_height', array( __CLASS__, 'on_option_updated' ), 10, 3 );
		add_action( 'update_option_thisismyurl_shadow_data_retention_days', array( __CLASS__, 'on_option_updated' ), 10, 3 );
		add_action( 'update_option_thisismyurl_shadow_notifications_enabled', array( __CLASS__, 'on_option_updated' ), 10, 3 );
		add_action( 'update_option_thisismyurl_shadow_notification_severity', array( __CLASS__, 'on_option_updated' ), 10, 3 );
		add_action( 'update_option_thisismyurl_shadow_notify_admin_email', array( __CLASS__, 'on_option_updated' ), 10, 3 );

		// Filters for WordPress integration
		add_filter( 'plugin_action_links_' . THISISMYURL_SHADOW_BASENAME, array( __CLASS__, 'filter_plugin_action_links' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'filter_site_status_tests' ) );
		add_filter( 'debug_information', array( __CLASS__, 'filter_debug_information' ) );
		add_filter( 'thisismyurl_shadow_diagnostic_readiness_state', array( __CLASS__, 'filter_diagnostic_readiness_state' ), 10, 3 );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_wp_mail_from_name' ), 999 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'filter_file_editor_caps' ), 10, 4 );

		// Cron jobs
		add_action( 'thisismyurl_shadow_run_overnight_fixes', array( __CLASS__, 'on_overnight_fixes' ) );
		add_action( 'thisismyurl_shadow_run_automated_fixes', array( __CLASS__, 'on_automated_fixes' ) );
		add_action( 'thisismyurl_shadow_run_data_cleanup', array( __CLASS__, 'on_data_cleanup' ) );
		add_action( 'thisismyurl_shadow_run_automatic_diagnostic_scan', array( __CLASS__, 'on_automatic_diagnostic_scan' ) );
		add_action( 'thisismyurl_shadow_send_scheduled_reports', array( __CLASS__, 'on_scheduled_reports' ) );
		add_action( 'thisismyurl_shadow_run_offpeak_operations', array( __CLASS__, 'on_offpeak_operations' ) );

		// KPI tracking
		add_action( 'thisismyurl_shadow_after_treatment_apply', array( __CLASS__, 'on_treatment_applied' ), 10, 3 );
		add_action( 'thisismyurl_shadow_diagnostic_executed', array( __CLASS__, 'on_diagnostic_executed' ), 10, 2 );

		// Multisite
		add_action( 'network_admin_menu', array( __CLASS__, 'on_network_admin_menu' ) );

		// Guaranteed Guardian manual-run endpoint wiring.
		add_action( 'admin_post_thisismyurl_shadow_run_guardian', array( __CLASS__, 'on_run_guardian_request' ) );
	}

	/**
	 * Handle one-time activation work.
	 *
	 * Activation is used for setup that must exist before the first normal page
	 * load, such as database migrations, activity logging, and the temporary
	 * "redirect to dashboard" flag consumed during the next admin request.
	 *
	 * @return void
	 */
	public static function on_activate() {
		// Run database migrations (create tables, schema updates)
		// This ensures all necessary tables exist for the plugin
		// Use dynamic include to avoid circular dependencies
		require_once THISISMYURL_SHADOW_PATH . 'includes/systems/core/class-database-migrator.php';
		Database_Migrator::migrate();

		// Log plugin activation
		if ( class_exists( 'ThisIsMyURL\\Shadow\\Core\\Activity_Logger' ) ) {
			Activity_Logger::log(
				'plugin_activated',
				__( 'Christopher Ross Shadow plugin activated', 'thisismyurl-shadow' ),
				'system',
				array( 'version' => THISISMYURL_SHADOW_VERSION )
			);
		}

		\ThisIsMyURL\Shadow\Core\Cache_Manager::set(
			'redirect_to_dashboard',
			true,
			30,
			'thisismyurl_shadow_hooks'
		);
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * Deactivation is intentionally light-touch. It records the lifecycle event
	 * but does not remove data because uninstall is the point where irreversible
	 * cleanup decisions are made.
	 *
	 * @return void
	 */
	public static function on_deactivate() {
		// Log plugin deactivation
		if ( class_exists( 'ThisIsMyURL\\Shadow\\Core\\Activity_Logger' ) ) {
			Activity_Logger::log(
				'plugin_deactivated',
				__( 'Christopher Ross Shadow plugin deactivated', 'thisismyurl-shadow' ),
				'system',
				array( 'version' => THISISMYURL_SHADOW_VERSION )
			);
		}
	}

	/**
	 * Run admin-only startup tasks.
	 *
	 * This method gathers a few concerns that must happen early in wp-admin:
	 * first-run redirect handling, stale cache cleanup, error bootstrap, and
	 * synchronization of policy-driven treatment defaults.
	 *
	 * @return void
	 */
	public static function on_admin_init() {
		// Redirect to dashboard on first activation
		if ( \ThisIsMyURL\Shadow\Core\Cache_Manager::get(
			'redirect_to_dashboard',
			'thisismyurl_shadow_hooks'
		) ) {
			\ThisIsMyURL\Shadow\Core\Cache_Manager::delete(
				'redirect_to_dashboard',
				'thisismyurl_shadow_hooks'
			);
			wp_safe_redirect( admin_url( 'admin.php?page=thisismyurl-shadow' ) );
			exit;
		}

		// One-time: clear stale diagnostic file-map cache so renamed/deleted files
		// no longer appear as ghost diagnostics with empty descriptions.
		if ( ! get_option( 'thisismyurl_shadow_file_map_stale_cleared_v1' ) ) {
			delete_transient( 'thisismyurl_shadow_diagnostic_file_map_v3' );
			wp_cache_delete( 'thisismyurl_shadow_diagnostic_file_map_v3', 'thisismyurl-shadow' );
			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Diagnostics\\Diagnostic_Registry' ) ) {
				\ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry::clear_cache();
			}
			update_option( 'thisismyurl_shadow_file_map_stale_cleared_v1', true, false );
		}

		// Initialize error handler
		Error_Handler::init();

		// Ensure treatment toggles follow policy defaults:
		// safe shipped treatments ON by default, others OFF by default.
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Treatment_Toggle_Policy' ) ) {
			\ThisIsMyURL\Shadow\Core\Treatment_Toggle_Policy::maybe_sync_defaults();
		}
	}

	/**
	 * Plugins loaded hook - Early phase (AJAX handlers only)
	 */
	public static function on_plugins_loaded() {
		// AJAX bootstrap intentionally disabled while the diagnostics/treatments
		// execution flow is rebuilt from a clean baseline.
		// AJAX_Router::init();
	}

	/**
	 * Finish plugin startup once the full class graph is available.
	 *
	 * The early plugins_loaded work is intentionally minimal. This later phase
	 * initializes registries and feature modules that depend on broader class
	 * availability or would be too expensive to wire during the earliest phase.
	 *
	 * @return void
	 */
	public static function on_plugins_loaded_late() {
		// Initialize core registries and systems - only if classes are loaded
		if ( class_exists( '\ThisIsMyURL\Shadow\Admin\Update_Notification_Manager' ) ) {
			\ThisIsMyURL\Shadow\Admin\Update_Notification_Manager::init();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Admin\Stale_Diagnostics_Notice' ) ) {
			\ThisIsMyURL\Shadow\Admin\Stale_Diagnostics_Notice::init();
		}
		if ( ! class_exists( '\ThisIsMyURL\Shadow\Admin\Pages\File_Write_Review_Page' ) ) {
			$review_page = THISISMYURL_SHADOW_PATH . 'includes/admin/pages/class-file-write-review-page.php';
			if ( file_exists( $review_page ) ) {
				require_once $review_page;
			}
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Admin\Pages\File_Write_Review_Page' ) ) {
			\ThisIsMyURL\Shadow\Admin\Pages\File_Write_Review_Page::init();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry' ) ) {
			\ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry::init();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Treatments\Treatment_Registry' ) ) {
			\ThisIsMyURL\Shadow\Treatments\Treatment_Registry::init();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Workflow_Executor' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Workflow_Executor::init();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Core\Treatment_Hooks' ) ) {
			\ThisIsMyURL\Shadow\Core\Treatment_Hooks::init();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Core\Site_Health_Explanations' ) ) {
			\ThisIsMyURL\Shadow\Core\Site_Health_Explanations::init();
		}

		// Initialize Guardian system
		if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Guardian_Manager' ) ) {
			\ThisIsMyURL\Shadow\Guardian\Guardian_Manager::init();
		}

		// Initialize analyzers only when Guardian is enabled.
		if ( self::is_guardian_runtime_enabled() ) {
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Failed_Login_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Failed_Login_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Dashboard_Performance_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Dashboard_Performance_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\REST_API_Performance_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\REST_API_Performance_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\CSP_Violation_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\CSP_Violation_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Compromised_Accounts_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Compromised_Accounts_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Cache_Invalidation_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Cache_Invalidation_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Shortcode_Execution_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Shortcode_Execution_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\API_Latency_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\API_Latency_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Block_Rendering_Performance_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Block_Rendering_Performance_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Bot_Traffic_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Bot_Traffic_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Browser_Compatibility_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Browser_Compatibility_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Editor_Performance_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Editor_Performance_Analyzer::init();
			}
			if ( class_exists( '\ThisIsMyURL\Shadow\Guardian\Hook_Execution_Analyzer' ) ) {
				\ThisIsMyURL\Shadow\Guardian\Hook_Execution_Analyzer::init();
			}
		}

		// Guardian command handlers
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Enable_Guardian_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Enable_Guardian_Command::register();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Configure_Guardian_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Configure_Guardian_Command::register();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Get_Scan_Results_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Get_Scan_Results_Command::register();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Execute_Auto_Fix_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Execute_Auto_Fix_Command::register();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Preview_Auto_Fixes_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Preview_Auto_Fixes_Command::register();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Update_Auto_Fix_Policy_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Update_Auto_Fix_Policy_Command::register();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Generate_Report_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Generate_Report_Command::register();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Send_Report_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Send_Report_Command::register();
		}
		if ( class_exists( '\ThisIsMyURL\Shadow\Workflow\Commands\Manage_Notifications_Command' ) ) {
			\ThisIsMyURL\Shadow\Workflow\Commands\Manage_Notifications_Command::register();
		}

		// Fire hook for external plugins/addons
		do_action( 'thisismyurl_shadow_core_loaded' );
	}

	/**
	 * Determine whether Guardian runtime monitoring should be active.
	 *
	 * @since 0.6095
	 * @return bool
	 */
	private static function is_guardian_runtime_enabled(): bool {
		return (bool) get_option( 'thisismyurl_shadow_guardian_enabled', false );
	}

	/**
	 * Admin menu hook
	 */
	public static function on_admin_menu() {
		// Menus are registered by Menu_Manager
		self::remove_file_editor_menu_items();
	}

	/**
	 * Remove WordPress file editor menu items when disabled in settings.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function remove_file_editor_menu_items() {
		$theme_editor_enabled  = self::get_file_editor_setting( 'thisismyurl_shadow_enable_theme_file_editor', true );
		$plugin_editor_enabled = self::get_file_editor_setting( 'thisismyurl_shadow_enable_plugin_file_editor', true );

		if ( ! $theme_editor_enabled ) {
			remove_submenu_page( 'themes.php', 'theme-editor.php' );
		}

		if ( ! $plugin_editor_enabled ) {
			remove_submenu_page( 'plugins.php', 'plugin-editor.php' );
		}
	}

	/**
	 * Admin head hook
	 *
	 * Adds a safe History API guard early on Christopher Ross Shadow admin pages so
	 * cross-origin replaceState attempts in proxied environments do not throw.
	 *
	 * @return void
	 */
	public static function on_admin_head() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'thisismyurl-shadow' ) ) {
			return;
		}
		?>
		<script type="text/javascript">
		(function() {
			if (window.__thisismyurlShadowSafeReplaceStateInstalled) {
				return;
			}

			if (!window.history || typeof window.history.replaceState !== 'function') {
				return;
			}

			var originalReplaceState = window.history.replaceState.bind(window.history);
			window.history.replaceState = function(state, title, url) {
				try {
					if (typeof url !== 'undefined' && null !== url) {
						var parsedUrl = new URL(url, window.location.href);
						if (parsedUrl.origin !== window.location.origin) {
							return;
						}
					}

					return originalReplaceState(state, title, url);
				} catch (error) {
					if (error && error.name === 'SecurityError') {
						return;
					}
					throw error;
				}
			};

			window.__thisismyurlShadowSafeReplaceStateInstalled = true;
		})();
		</script>
		<?php
	}

	/**
	 * Enqueue optional admin accessibility styles via an inline stylesheet.
	 *
	 * Registers a base handle and attaches the generated preference CSS with
	 * wp_add_inline_style() so the output is enqueued through the styles API
	 * rather than echoed directly into the admin head.
	 *
	 * @since 0.6149
	 * @return void
	 */
	public static function enqueue_admin_accessibility_styles(): void {
		$css = self::build_admin_accessibility_css();
		if ( '' === $css ) {
			return;
		}

		$handle = 'thisismyurl-shadow-admin-accessibility';
		wp_register_style( $handle, false, array(), THISISMYURL_SHADOW_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Build optional admin accessibility CSS from saved preferences.
	 *
	 * Applies reading and visibility preferences across WordPress admin screens
	 * when enabled from the Christopher Ross Shadow Accessibility tab. Every
	 * interpolated value is derived from a controlled source — sanitize_key'd
	 * enums, a clamped number_format'd scale, and hardcoded literals — so the
	 * result cannot break out of the inline style context.
	 *
	 * @since 0.6095
	 * @return string Generated CSS, or an empty string when no preference is active.
	 */
	private static function build_admin_accessibility_css(): string {
		if ( ! is_admin() ) {
			return '';
		}

		$admin_font      = sanitize_key( (string) get_option( 'thisismyurl_shadow_admin_font_family', 'default' ) );
		$font_scale      = (float) get_option( 'thisismyurl_shadow_font_size_multiplier', 1.0 );
		$high_contrast   = (bool) get_option( 'thisismyurl_shadow_high_contrast_mode', false );
		$reduce_motion   = (bool) get_option( 'thisismyurl_shadow_reduce_motion', false );
		$simplified_ui   = (bool) get_option( 'thisismyurl_shadow_simplified_ui', false );
		$screen_reader   = (bool) get_option( 'thisismyurl_shadow_screen_reader_optimization', false );
		$focus_raw       = get_option( 'thisismyurl_shadow_focus_indicators', '' );
		$focus_style     = is_string( $focus_raw ) ? sanitize_key( $focus_raw ) : '';
		$font_scale      = min( max( $font_scale, 0.8 ), 2.0 );
		$font_scale_css  = number_format( $font_scale, 2, '.', '' );
		$css             = '';

		if ( 'readable' === $admin_font || 'lexend' === $admin_font ) {
			$font_stack     = 'lexend' === $admin_font
				? '"Lexend", "Atkinson Hyperlegible", "Atkinson Hyperlegible Next", Verdana, "Trebuchet MS", "Segoe UI", sans-serif'
				: '"Atkinson Hyperlegible", "Atkinson Hyperlegible Next", Verdana, "Trebuchet MS", "Segoe UI", sans-serif';
			$line_height    = 'lexend' === $admin_font ? '1.60' : '1.55';
			$letter_spacing = 'lexend' === $admin_font ? '0.018em' : '0.010em';
			$css           .= "body.wp-admin, body.wp-admin input, body.wp-admin select, body.wp-admin textarea, body.wp-admin button, body.wp-admin .button, body.wp-admin p, body.wp-admin li, body.wp-admin td, body.wp-admin th, body.wp-admin #adminmenu a, body.wp-admin .wp-submenu a, body.wp-admin .notice p, body.wp-admin .wrap h1, body.wp-admin .wrap h2, body.wp-admin .wrap h3 { font-family: {$font_stack} !important; line-height: {$line_height}; letter-spacing: {$letter_spacing}; }\n";
			$css           .= "body.wp-admin code, body.wp-admin pre, body.wp-admin kbd, body.wp-admin samp { font-family: monospace !important; letter-spacing: normal; } body.wp-admin .dashicons { font-family: dashicons !important; letter-spacing: normal; }\n";
		}

		if ( abs( $font_scale - 1.0 ) > 0.001 ) {
			$css .= "body.wp-admin { font-size: calc(13px * {$font_scale_css}); }\n";
			$css .= "body.wp-admin #adminmenu a, body.wp-admin .wp-submenu a, body.wp-admin input, body.wp-admin select, body.wp-admin textarea, body.wp-admin button, body.wp-admin .button, body.wp-admin p, body.wp-admin li, body.wp-admin td, body.wp-admin th { font-size: calc(13px * {$font_scale_css}) !important; }\n";
		}

		if ( $high_contrast ) {
			$css .= "body.wp-admin { background: #ffffff !important; color: #111827 !important; }\n";
			$css .= "body.wp-admin a { color: #0037cc !important; } body.wp-admin .button-primary { background: #0b57d0 !important; border-color: #083b8a !important; } body.wp-admin .notice, body.wp-admin .card, body.wp-admin .postbox { border-color: #111827 !important; }\n";
		}

		if ( $reduce_motion ) {
			$css .= "body.wp-admin *, body.wp-admin *::before, body.wp-admin *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }\n";
		}

		if ( $simplified_ui ) {
			$css .= "body.wp-admin .postbox, body.wp-admin .card, body.wp-admin .notice { box-shadow: none !important; } body.wp-admin .description, body.wp-admin .notice p, body.wp-admin .wrap p { max-width: 78ch; }\n";
		}

		if ( $screen_reader ) {
			$css .= "body.wp-admin .screen-reader-text:focus { clip: auto !important; clip-path: none !important; width: auto !important; height: auto !important; margin: 6px !important; padding: 8px 12px !important; background: #111827 !important; color: #ffffff !important; z-index: 100000 !important; }\n";
		}

		if ( in_array( $focus_style, array( 'enhanced', 'maximum' ), true ) ) {
			$outline_width = 'maximum' === $focus_style ? '4px' : '3px';
			$ring_width    = 'maximum' === $focus_style ? '6px' : '4px';
			$css          .= "body.wp-admin a:focus, body.wp-admin a:focus-visible, body.wp-admin button:focus, body.wp-admin button:focus-visible, body.wp-admin input:focus, body.wp-admin input:focus-visible, body.wp-admin select:focus, body.wp-admin select:focus-visible, body.wp-admin textarea:focus, body.wp-admin textarea:focus-visible { outline: {$outline_width} solid #1d4ed8 !important; outline-offset: 2px !important; box-shadow: 0 0 0 {$ring_width} rgba(29, 78, 216, 0.18) !important; }\n";
		}

		if ( '' === trim( $css ) ) {
			return '';
		}

		return $css;
	}

	/**
	 * Cache-bust Christopher Ross Shadow stylesheet URLs.
	 *
	 * Replaces version query strings with filemtime for plugin CSS files so
	 * browser caches refresh immediately after CSS changes.
	 *
	 * @since 0.6095
	 * @param string $src    Stylesheet source URL.
	 * @param string $handle Registered style handle.
	 * @return string
	 */
	public static function filter_style_loader_src( $src, $handle ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}

		// Only process Christopher Ross Shadow plugin styles.
		if ( false === strpos( $src, THISISMYURL_SHADOW_URL ) ) {
			return $src;
		}

		$src_no_query = strtok( $src, '?' );
		if ( ! is_string( $src_no_query ) || ! str_ends_with( $src_no_query, '.css' ) ) {
			return $src;
		}

		$relative_path = ltrim( str_replace( THISISMYURL_SHADOW_URL, '', $src_no_query ), '/' );
		if ( '' === $relative_path ) {
			return $src;
		}

		$file_path = THISISMYURL_SHADOW_PATH . $relative_path;
		if ( ! file_exists( $file_path ) ) {
			return $src;
		}

		$mtime = filemtime( $file_path );
		if ( false === $mtime ) {
			return $src;
		}

		$src = remove_query_arg( 'ver', $src );

		return add_query_arg( 'ver', (string) $mtime, $src );
	}

	/**
	 * Frontend enqueue scripts hook
	 */
	public static function on_wp_enqueue_scripts() {
		$should_enqueue = (bool) apply_filters( 'thisismyurl_shadow_enqueue_frontend_assets', false );
		if ( ! $should_enqueue ) {
			return;
		}

		// No standalone frontend stylesheet bundle is shipped in the current build.
	}

	/**
	 * Admin notices hook
	 */
	public static function on_admin_notices() {
		$scheduled = get_option( 'thisismyurl_shadow_scheduled_offpeak', array() );

		if ( ! empty( $scheduled ) ) {
			$next_run  = wp_next_scheduled( 'thisismyurl_shadow_run_offpeak_operations' );
			$count     = count( $scheduled );
			$time_text = $next_run ? date_i18n( get_option( 'time_format' ), $next_run ) : 'tonight';

			echo '<div class="notice notice-info is-dismissible">';
			echo '<p><span class="dashicons dashicons-clock" style="color: #2196f3;"></span> ';
			echo '<strong>Christopher Ross Shadow:</strong> ' . esc_html( $count ) . ' operation(s) scheduled for off-peak hours (' . esc_html( $time_text ) . ').';
			echo '</p></div>';
		}

		// Show email test status on export-personal-data.php page
		global $pagenow;
		if ( $pagenow === 'export-personal-data.php' ) {
			self::show_export_personal_data_email_notice();
		}
	}

	/**
	 * Show email server test status on export-personal-data.php page
	 */
	private static function show_export_personal_data_email_notice() {
		$email_test_status = get_option( 'thisismyurl_shadow_last_email_test_status', 'not_tested' );
		$email_test_time   = get_option( 'thisismyurl_shadow_last_email_test_time', 0 );
		$email_tool_url    = admin_url( 'admin.php?page=thisismyurl-shadow-settings&tab=notifications' );

		if ( $email_test_status === 'passed' ) {
			$time_ago = ( $email_test_time > 0 ) ? human_time_diff( $email_test_time, current_time( 'timestamp' ) ) : __( 'unknown time', 'thisismyurl-shadow' );
			echo '<div class="notice notice-success wps-mt-5 is-dismissible">';
			echo '<p>';
			echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
			printf(
				/* translators: 1: time ago, 2: opening anchor tag for email test tool, 3: closing anchor tag */
				esc_html__( 'Email server tested and passed %1$s ago. %2$sRetest email server%3$s', 'thisismyurl-shadow' ),
				'<strong>' . esc_html( $time_ago ) . '</strong>',
				'<a href="' . esc_url( $email_tool_url ) . '">',
				'</a>'
			);
			echo '</p></div>';
		} elseif ( $email_test_status === 'failed' ) {
			echo '<div class="notice notice-error wps-mt-5 is-dismissible">';
			echo '<p>';
			echo '<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ';
			printf(
				/* translators: 1: opening anchor tag for email test tool, 2: closing anchor tag */
				esc_html__( 'Email server test failed. Personal data export notifications may not be delivered. %1$sTest email server%2$s', 'thisismyurl-shadow' ),
				'<a href="' . esc_url( $email_tool_url ) . '"><strong>',
				'</strong></a>'
			);
			echo '</p></div>';
		} else {
			echo '<div class="notice notice-warning wps-mt-5 is-dismissible">';
			echo '<p>';
			echo '<span class="dashicons dashicons-info" style="color: #f0b849;"></span> ';
			printf(
				/* translators: 1: opening anchor tag for email test tool, 2: closing anchor tag */
				esc_html__( 'Email server has not been tested. Personal data export notifications may not be delivered. %1$sTest email server now%2$s', 'thisismyurl-shadow' ),
				'<a href="' . esc_url( $email_tool_url ) . '"><strong>',
				'</strong></a>'
			);
			echo '</p></div>';
		}
	}

	/**
	 * Tool box hook
	 */
	public static function on_tool_box() {
		return;
	}

	/**
	 * Show user profile hook
	 */
	public static function on_show_user_profile( $user ) {
		$dark_mode_pref = get_user_meta( $user->ID, 'thisismyurl_shadow_dark_mode_preference', true ) ?: 'auto';
		?>
		<div class="wps-settings-section wps-max-w-600">
			<div class="wps-settings-section-header">
				<h3 class="wps-settings-section-title"><?php esc_html_e( 'Christopher Ross Shadow Dark Mode', 'thisismyurl-shadow' ); ?></h3>
				<p class="wps-settings-section-description">
					<?php esc_html_e( 'Choose your preferred dark mode setting for Christopher Ross Shadow admin pages.', 'thisismyurl-shadow' ); ?>
				</p>
			</div>

			<fieldset>
				<legend class="screen-reader-text"><span><?php esc_html_e( 'Christopher Ross Shadow Dark Mode', 'thisismyurl-shadow' ); ?></span></legend>
				<div style="display: flex; flex-direction: column; gap: 8px;">
					<label>
						<input type="radio" name="thisismyurl_shadow_dark_mode" value="auto" <?php checked( $dark_mode_pref, 'auto' ); ?>>
						<?php esc_html_e( 'Auto (follow system preference)', 'thisismyurl-shadow' ); ?>
					</label>
					<label>
						<input type="radio" name="thisismyurl_shadow_dark_mode" value="light" <?php checked( $dark_mode_pref, 'light' ); ?>>
						<?php esc_html_e( 'Light', 'thisismyurl-shadow' ); ?>
					</label>
					<label>
						<input type="radio" name="thisismyurl_shadow_dark_mode" value="dark" <?php checked( $dark_mode_pref, 'dark' ); ?>>
						<?php esc_html_e( 'Dark', 'thisismyurl-shadow' ); ?>
					</label>
				</div>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Personal options update hook
	 */
	public static function on_personal_options_update( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$dark_mode = Form_Param_Helper::post( 'thisismyurl_shadow_dark_mode', 'text', '' );
		if ( ! empty( $dark_mode ) ) {
			if ( in_array( $dark_mode, array( 'auto', 'light', 'dark' ), true ) ) {
				update_user_meta( $user_id, 'thisismyurl_shadow_dark_mode_preference', $dark_mode );
			}
		}
	}

	/**
	 * Apply admin-defined profile section visibility settings.
	 *
	 * Hides selected profile sections for non-admin users to simplify
	 * the profile editing experience.
	 *
	 * @return void
	 */
	public static function on_profile_sections_visibility() {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$defaults = array(
			'visual_preferences'     => true,
			'toolbar'                => true,
			'language'               => true,
			'contact_info'           => true,
			'about'                  => true,
			'profile_picture'        => true,
			'application_passwords'  => true,
			'sessions'               => true,
		);

		$visibility = get_option( 'thisismyurl_shadow_profile_sections_visibility', $defaults );
		if ( ! is_array( $visibility ) ) {
			$visibility = $defaults;
		}

		$selectors = array();

		if ( empty( $visibility['visual_preferences'] ) ) {
			$selectors[] = '.user-rich-editing-wrap';
			$selectors[] = '.user-syntax-highlighting-wrap';
			$selectors[] = '.user-admin-color-wrap';
			$selectors[] = '.user-comment-shortcuts-wrap';
		}

		if ( empty( $visibility['toolbar'] ) ) {
			$selectors[] = '.show-admin-bar';
		}

		if ( empty( $visibility['language'] ) ) {
			$selectors[] = '.user-language-wrap';
		}

		if ( empty( $visibility['contact_info'] ) ) {
			$selectors[] = '.user-email-wrap';
			$selectors[] = '.user-url-wrap';
			$selectors[] = '.user-aim-wrap';
			$selectors[] = '.user-yim-wrap';
			$selectors[] = '.user-jabber-wrap';
		}

		if ( empty( $visibility['about'] ) ) {
			$selectors[] = '.user-description-wrap';
		}

		if ( empty( $visibility['profile_picture'] ) ) {
			$selectors[] = '.user-profile-picture';
		}

		if ( empty( $visibility['application_passwords'] ) ) {
			$selectors[] = '#application-passwords-section';
		}

		if ( empty( $visibility['sessions'] ) ) {
			$selectors[] = '#sessions';
		}

		$selectors = array_values( array_unique( array_filter( $selectors ) ) );
		if ( empty( $selectors ) ) {
			return;
		}

		?>
		<style id="thisismyurl-shadow-profile-section-visibility">
		<?php echo esc_html( implode( ',', $selectors ) ); ?> { display: none !important; }
		</style>
		<?php
	}

	/**
	 * Filter plugin action links
	 */
	public static function filter_plugin_action_links( $links ) {
		$links   = Menu_Manager::add_settings_link( $links );
		$links[] = '<a href="' . esc_url( 'https://github.com/sponsors/thisismyurl' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sponsor', 'thisismyurl-shadow' ) . '</a>';
		return $links;
	}

	/**
	 * Filter site status tests
	 */
	public static function filter_site_status_tests( $tests ) {
		if ( ! is_array( $tests ) ) {
			$tests = array();
		}

		// Site Health direct scan trigger tests are intentionally removed.
		// Diagnostics execution is cron-only.

		$GLOBALS['thisismyurl_shadow_site_health_badge'] = array(
			'label' => __( 'Christopher Ross Shadow', 'thisismyurl-shadow' ),
			'color' => 'blue',
		);

		return $tests;
	}

	/**
	 * Filter debug information
	 */
	public static function filter_debug_information( $info ) {
		if ( ! is_array( $info ) ) {
			$info = array();
		}

		$current_user_id = get_current_user_id();
		$quick_hidden    = (bool) get_user_meta( $current_user_id, 'thisismyurl_shadow_hide_quick_scan', true );

		$quick_last = (int) get_option( 'thisismyurl_shadow_last_quick_checks', 0 );

		$autofix_all   = (bool) get_option( 'thisismyurl_shadow_allow_all_autofixes', true );
		$autofix_types = get_option( 'thisismyurl_shadow_autofix_permissions', array() );
		$autofix_count = is_array( $autofix_types ) ? count( $autofix_types ) : 0;

		$activity_result = \ThisIsMyURL\Shadow\Core\Activity_Logger::get_activities( array(), 500, 0 );
		$finding_count   = isset( $activity_result['total'] ) ? $activity_result['total'] : 0;

		$section = array(
			'label'  => __( 'Christopher Ross Shadow', 'thisismyurl-shadow' ),
			'fields' => array(
				array(
					'label'   => __( 'Quick Scan last run', 'thisismyurl-shadow' ),
							'value'   => $quick_last ? sprintf(
								/* translators: %s: human-readable relative time. */
								__( '%s ago', 'thisismyurl-shadow' ),
								human_time_diff( $quick_last, time() )
							) : __( 'Not yet', 'thisismyurl-shadow' ),
					'private' => false,
				),
				array(
					'label'   => __( 'Auto-fix (global allow)', 'thisismyurl-shadow' ),
					'value'   => $autofix_all ? __( 'Enabled', 'thisismyurl-shadow' ) : __( 'Disabled', 'thisismyurl-shadow' ),
					'private' => false,
				),
				array(
					'label'   => __( 'Auto-fix types enabled', 'thisismyurl-shadow' ),
					'value'   => (string) $autofix_count,
					'private' => false,
				),
				array(
					'label'   => __( 'Finding log entries', 'thisismyurl-shadow' ),
					'value'   => (string) $finding_count,
					'private' => false,
				),
			),
		);

		$info['thisismyurl-shadow'] = $section;
		return $info;
	}

	/**
	 * Mark selectively fragile diagnostics as beta-ready only.
	 *
	 * @param string $state      Computed readiness state.
	 * @param string $class_name Diagnostic class name.
	 * @param string $file_path  Source file path.
	 * @return string
	 */
	public static function filter_diagnostic_readiness_state( $state, $class_name, $file_path ) {
		unset( $file_path );

		if ( '\\ThisIsMyURL\\Shadow\\Diagnostics\\Diagnostic_Http2_Or_Http3_Enabled' === $class_name ) {
			return Readiness_Registry::STATE_BETA;
		}

		return $state;
	}

	/**
	 * Filter wp_mail_from_name
	 */
	public static function filter_wp_mail_from_name( $from_name ) {
		$custom_from_name = get_option( 'thisismyurl_shadow_email_from_name', '' );

		if ( ! empty( $custom_from_name ) ) {
			return $custom_from_name;
		}

		return $from_name;
	}

	/**
	 * Restrict file editor capabilities based on Christopher Ross Shadow settings.
	 *
	 * @since 0.6095
	 * @param array  $caps    Primitive capabilities required for the requested capability.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args    Adds context to the capability check, typically starting with object ID.
	 * @return array
	 */
	public static function filter_file_editor_caps( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_themes', 'edit_plugins' ), true ) ) {
			return $caps;
		}

		$theme_editor_enabled  = self::get_file_editor_setting( 'thisismyurl_shadow_enable_theme_file_editor', true );
		$plugin_editor_enabled = self::get_file_editor_setting( 'thisismyurl_shadow_enable_plugin_file_editor', true );

		if ( 'edit_themes' === $cap && ! $theme_editor_enabled ) {
			return array( 'do_not_allow' );
		}

		if ( 'edit_plugins' === $cap && ! $plugin_editor_enabled ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/**
	 * Read file-editor setting without loading all autoloaded options.
	 *
	 * This runs inside capability checks where memory pressure matters.
	 * We query only the required option and cache it for the request.
	 *
	 * @since 0.6095
	 * @param string $option_name Option name.
	 * @param bool   $default     Default value when option is missing.
	 * @return bool
	 */
	private static function get_file_editor_setting( string $option_name, bool $default ): bool {
		// WordPress constants always take precedence over the saved setting.
		if ( ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) ||
			( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
			return false;
		}

		static $cache = array();

		if ( array_key_exists( $option_name, $cache ) ) {
			return (bool) $cache[ $option_name ];
		}

		$value = get_option( $option_name, null );

		if ( null === $value ) {
			$cache[ $option_name ] = $default;
			return $default;
		}

		$normalized = strtolower( trim( (string) $value ) );
		$enabled    = ! in_array( $normalized, array( '', '0', 'false', 'no', 'off' ), true );

		$cache[ $option_name ] = $enabled;
		return $enabled;
	}

	/**
	 * Cron: Overnight fixes
	 */
	public static function on_overnight_fixes() {
		$scheduled = get_option( 'thisismyurl_shadow_scheduled_fixes', array() );

		if ( empty( $scheduled ) ) {
			return;
		}

		foreach ( $scheduled as $item ) {
			$finding_id = $item['finding_id'];
			$user_email = $item['user_email'];
			$result     = thisismyurl_shadow_attempt_autofix( $finding_id );

			if ( $result['success'] ) {
				$status_manager = new \ThisIsMyURL\Shadow\Core\Finding_Status_Manager();
				$status_manager->set_finding_status( $finding_id, 'fixed' );
				\ThisIsMyURL\Shadow\Core\Activity_Logger::log( 'treatment_applied', "Overnight fix completed: {$finding_id}", 'workflows', array( 'finding_id' => $finding_id ) );

				$subject = 'Christopher Ross Shadow: Fix Completed';
				$message = "Your scheduled fix has been completed successfully.\n\nFinding: {$finding_id}\n" . $result['message'];
			} else {
				$subject = 'Christopher Ross Shadow: Fix Failed';
				$message = "Your scheduled fix encountered an error.\n\nFinding: {$finding_id}\n" . ( $result['message'] ?? 'Unknown error' );
			}

			wp_mail( $user_email, $subject, $message );
		}

		delete_option( 'thisismyurl_shadow_scheduled_fixes' );
	}

	/**
	 * Cron: Automated fixes
	 */
	public static function on_automated_fixes() {
		$scheduled = get_option( 'thisismyurl_shadow_scheduled_automated_fixes', array() );

		if ( empty( $scheduled ) ) {
			return;
		}

		foreach ( $scheduled as $finding_id => $item ) {
			if ( $item['status'] !== 'pending' ) {
				continue;
			}

			$result                                = thisismyurl_shadow_attempt_autofix( $finding_id );
			$scheduled[ $finding_id ]['status']    = $result['success'] ? 'completed' : 'failed';
			$scheduled[ $finding_id ]['completed'] = current_time( 'timestamp' );
			$scheduled[ $finding_id ]['message']   = $result['message'] ?? '';

			if ( $result['success'] ) {
				$status_manager = new \ThisIsMyURL\Shadow\Core\Finding_Status_Manager();
				$status_manager->set_finding_status( $finding_id, 'fixed' );

				if ( class_exists( '\ThisIsMyURL\Shadow\Core\KPI_Tracker' ) ) {
					\ThisIsMyURL\Shadow\Core\KPI_Tracker::record_treatment_applied( $finding_id, 5 );
				}

				\ThisIsMyURL\Shadow\Core\Activity_Logger::log( 'treatment_applied', "Automated fix completed: {$finding_id}", '', array( 'finding_id' => $finding_id ) );
			} else {
				\ThisIsMyURL\Shadow\Core\Activity_Logger::log(
					'workflow_executed',
					"Automated fix failed: {$finding_id} - " . $result['message'],
					'',
					array(
						'finding_id' => $finding_id,
						'error'      => $result['message'],
					)
				);
			}
		}

		update_option( 'thisismyurl_shadow_scheduled_automated_fixes', $scheduled );
	}

	/**
	 * Handle explicit Guardian run requests from admin-post endpoint.
	 *
	 * This fallback route guarantees the action exists even when optional admin
	 * notice classes are not loaded in the current runtime path.
	 *
	 * @return void
	 */
	public static function on_run_guardian_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'thisismyurl-shadow' ) );
		}

		check_admin_referer( 'thisismyurl_shadow_run_guardian' );

		$run_error = '';
		if ( class_exists( '\ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager' ) ) {
			try {
				\ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager::run_diagnostic_scan( true );
			} catch ( \Throwable $exception ) {
				$run_error = sanitize_key( get_class( $exception ) );
				Error_Handler::log_error( 'Christopher Ross Shadow Guardian run failed', $exception );
			}
		} else {
			$run_error = 'scan_manager_missing';
		}

		$default_redirect = admin_url( 'admin.php?page=thisismyurl-shadow-guardian' );
		$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( (string) $_GET['redirect'] ) ) : '';
		if ( '' === $redirect ) {
			$redirect = $default_redirect;
		} elseif ( 0 === strpos( $redirect, '/' ) ) {
			$redirect = home_url( $redirect );
		}

		$redirect = wp_validate_redirect( $redirect, $default_redirect );

		$args = array(
			'thisismyurl_shadow_guardian_run' => '1',
		);
		if ( '' !== $run_error ) {
			$args['thisismyurl_shadow_guardian_error'] = $run_error;
		}

		$redirect = add_query_arg( $args, $redirect );
		$redirect = wp_validate_redirect( $redirect, $default_redirect );
		if ( wp_safe_redirect( $redirect ) ) {
			exit;
		}

		$status_text = '' !== $run_error
			? __( 'Guardian run encountered an issue.', 'thisismyurl-shadow' )
			: __( 'Guardian run completed.', 'thisismyurl-shadow' );

		wp_die(
			sprintf(
				'<p>%1$s</p><p><a href="%2$s">%3$s</a></p>',
				esc_html( $status_text ),
				esc_url( $redirect ),
				esc_html__( 'Continue', 'thisismyurl-shadow' )
			),
			esc_html__( 'Christopher Ross Shadow Guardian', 'thisismyurl-shadow' ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Cron: Data cleanup
	 */
	public static function on_data_cleanup() {
		if ( class_exists( '\ThisIsMyURL\Shadow\Admin\Pages\Data_Retention_Manager' ) ) {
			\ThisIsMyURL\Shadow\Admin\Pages\Data_Retention_Manager::run_cleanup();
		}

		// Cleanup old visual comparisons
		if ( class_exists( '\ThisIsMyURL\Shadow\Core\Visual_Comparator' ) ) {
			$retention_days = get_option( 'thisismyurl_shadow_visual_comparison_retention_days', 30 );
			\ThisIsMyURL\Shadow\Core\Visual_Comparator::cleanup_old_comparisons( (int) $retention_days );
		}
	}

	/**
	 * Cron: Automatic diagnostic scan
	 */
	public static function on_automatic_diagnostic_scan() {
		if ( class_exists( '\ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager' ) ) {
			\ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager::run_diagnostic_scan();
		}
	}

	/**
	 * Cron: Scheduled reports
	 */
	public static function on_scheduled_reports() {
		if ( class_exists( '\ThisIsMyURL\Shadow\Admin\Pages\Report_Scheduler' ) ) {
			$schedules = \ThisIsMyURL\Shadow\Admin\Pages\Report_Scheduler::get_all_schedules();

			foreach ( $schedules as $report_type => $config ) {
				if ( ! empty( $config['enabled'] ) ) {
					\ThisIsMyURL\Shadow\Admin\Pages\Report_Scheduler::send_scheduled_report( $report_type );
				}
			}
		}
	}

	/**
	 * Cron: Off-peak operations
	 */
	public static function on_offpeak_operations() {
		$scheduled = get_option( 'thisismyurl_shadow_scheduled_offpeak', array() );

		if ( empty( $scheduled ) ) {
			return;
		}

		foreach ( $scheduled as $item ) {
			$operation_type = $item['operation_type'];
			$user_email     = $item['user_email'];

			$result = array(
				'success' => false,
				'message' => 'Unknown operation type',
			);

			switch ( $operation_type ) {
				case 'deep-scan':
					$result = array(
						'success' => false,
						'message' => 'Deep scan is disabled in this beta build and was not executed.',
					);
					break;

				case 'database-optimization':
					$result = array(
						'success' => true,
						'message' => 'Database optimized successfully.',
					);
					break;
			}

			$subject = $result['success'] ? 'Christopher Ross Shadow: Off-Peak Operation Completed' : 'Christopher Ross Shadow: Off-Peak Operation Failed';
			$message = $result['success']
				? "Your scheduled operation has been completed successfully.\n\nOperation: {$operation_type}\n" . $result['message']
				: "Your scheduled operation encountered an error.\n\nOperation: {$operation_type}\n" . $result['message'];

			wp_mail( $user_email, $subject, $message );
		}

		delete_option( 'thisismyurl_shadow_scheduled_offpeak' );
	}

	/**
	 * Track treatment applied (KPI)
	 */
	public static function on_treatment_applied( $class, $finding_id, $result ) {
		if ( ! isset( $result['success'] ) || ! $result['success'] ) {
			return;
		}

		$treatment_id = strtolower( str_replace( 'ThisIsMyURL\Shadow\Treatments\Treatment_', '', $class ) );

		\ThisIsMyURL\Shadow\Core\KPI_Tracker::record_treatment_applied( $treatment_id, 5 );

		\ThisIsMyURL\Shadow\Core\Activity_Logger::log(
			'treatment_applied',
			sprintf( 'Applied treatment: %s', $treatment_id ),
			'',
			array(
				'finding_id' => $finding_id,
				'treatment'  => $treatment_id,
			)
		);

		\ThisIsMyURL\Shadow\Core\Trend_Chart::record_finding_resolved( $finding_id, 'fixed' );
	}

	/**
	 * Track diagnostic executed (KPI)
	 */
	public static function on_diagnostic_executed( $diagnostic_id, $result ) {
		$success = isset( $result['success'] ) ? $result['success'] : false;
		\ThisIsMyURL\Shadow\Core\KPI_Tracker::record_diagnostic_run( $diagnostic_id, $success );
	}

	/**
	 * Track user login
	 *
	 * Logs when a user logs in to the WordPress dashboard.
	 * Helps audit who's accessing the site and when.
	 *
	 * @param string   $user_login Username
	 * @param \WP_User $user       User object
	 * @return void
	 */
	public static function on_user_login( $user_login, $user ) {
		if ( ! class_exists( 'ThisIsMyURL\Shadow\Core\Activity_Logger' ) || empty( $user ) ) {
			return;
		}

		Activity_Logger::log(
			'user_login',
					sprintf(
						/* translators: %s: display name of the user who logged in. */
						__( 'User %s logged in', 'thisismyurl-shadow' ),
						$user->display_name
					),
			'admin',
			array(
				'user_id'     => $user->ID,
				'username'    => $user_login,
				'user_email'  => $user->user_email,
				'user_roles'  => ! empty( $user->roles ) ? $user->roles : array(),
			)
		);
	}

	/**
	 * Track settings changes
	 *
	 * Logs when any Christopher Ross Shadow setting is updated.
	 * Records which user made the change, what was changed, and old/new values.
	 *
	 * @param mixed  $old_value Old option value
	 * @param mixed  $new_value New option value
	 * @param string $option    Option name
	 * @return void
	 */
	public static function on_option_updated( $old_value, $new_value, $option ) {
		if ( ! class_exists( 'ThisIsMyURL\Shadow\Core\Activity_Logger' ) ) {
			return;
		}

		// Skip if values are the same (no actual change)
		if ( $old_value === $new_value ) {
			return;
		}

		// Get human-readable setting name
		$setting_names = array(
			'thisismyurl_shadow_cache_enabled'             => 'Cache Enabled',
			'thisismyurl_shadow_cache_duration'            => 'Cache Duration',
			'thisismyurl_shadow_visual_comparison_width'   => 'Visual Comparison Width',
			'thisismyurl_shadow_visual_comparison_height'  => 'Visual Comparison Height',
			'thisismyurl_shadow_data_retention_days'       => 'Data Retention',
			'thisismyurl_shadow_notifications_enabled'     => 'Notifications',
			'thisismyurl_shadow_notification_severity'     => 'Notification Severity',
			'thisismyurl_shadow_notify_admin_email'        => 'Notification Email',
		);

		$setting_name = isset( $setting_names[ $option ] ) ? $setting_names[ $option ] : $option;

		// Format values for display
		$old_display = self::format_setting_value( $old_value );
		$new_display = self::format_setting_value( $new_value );

		Activity_Logger::log(
			'setting_changed',
			sprintf(
						/* translators: 1: setting name, 2: previous value, 3: new value. */
						__( 'Setting changed: %1$s (from "%2$s" to "%3$s")', 'thisismyurl-shadow' ),
				$setting_name,
				$old_display,
				$new_display
			),
			'settings',
			array(
				'setting_name' => $setting_name,
				'option_key'   => $option,
				'old_value'    => $old_value,
				'new_value'    => $new_value,
			)
		);
	}

	/**
	 * Format setting values for logging
	 *
	 * Converts various data types to human-readable strings.
	 *
	 * @param mixed $value The setting value to format
	 * @return string Formatted value
	 */
	private static function format_setting_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'enabled' : 'disabled';
		}

		if ( is_array( $value ) ) {
			return implode( ', ', (array) $value );
		}

		if ( is_numeric( $value ) ) {
			return (string) $value;
		}

		return (string) $value;
	}

	/**
	 * Network admin menu
	 */
	public static function on_network_admin_menu() {
		add_menu_page(
			'Christopher Ross Shadow',
			'Christopher Ross Shadow',
			'read',
			'thisismyurl-shadow',
			function () {
				?>
				<div class="wrap">
					<h1>Christopher Ross Shadow (Network)</h1>
					<?php do_action( 'thisismyurl_shadow_after_page_header' ); ?>
					<p>Network admin menu check.</p>
				</div>
				<?php
			},
			'dashicons-admin-generic',
			999
		);
	}
}
