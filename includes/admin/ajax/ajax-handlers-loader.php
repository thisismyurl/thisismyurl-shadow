<?php
/**
 * AJAX Handlers Loader
 *
 * Loads all AJAX handler classes before they're registered in AJAX_Router.
 *
 * NOTE: With PSR-4 autoloading enabled, this file is no longer strictly necessary
 * as classes will be loaded automatically when referenced. However, we keep it for:
 * - Explicit dependency loading order
 * - Backwards compatibility
 * - Clear documentation of all AJAX handlers
 *
 * @package ThisIsMyURL\Shadow
 * @since 0.6095
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$thisismyurl_shadow_ajax_path = __DIR__ . '/';

if ( ! function_exists( 'thisismyurl_shadow_require_ajax_handler' ) ) {
	/**
	 * Require an AJAX handler file when present.
	 *
	 * @param string $base_path Base AJAX handlers directory.
	 * @param string $file      Relative handler filename.
	 * @return void
	 */
	function thisismyurl_shadow_require_ajax_handler( $base_path, $file ) {
		$path = $base_path . $file;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}

// Core finding operations.
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'autofix-finding-handler.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'dry-run-treatment-handler.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'change-finding-status-handler.php' );

// Post-scan treatment application.
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-post-scan-treatments-handler.php' );

// Dashboard operations.
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-get-dashboard-data-handler.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-save-dashboard-prefs-handler.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'heartbeat-diagnostics-handler.php' );

// Scanning operations.
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'dismiss-scan-notice-handler.php' );

// Reporting.
// Settings management.
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-save-setting-handler.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-save-scan-config.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-save-diagnostic-frequency.php' );

// Activity tracking operations.
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-get-activities-handler.php' );
// Diagnostics & Treatments listing/toggles (Scan Settings UI).
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-diagnostics-list.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-toggle-diagnostic.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-set-diagnostic-frequency.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-treatments-list.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-toggle-treatment.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-save-treatment-inputs.php' );

// Governance & Readiness reporting.
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-readiness-inventory.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-readiness-export.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-environment-policy.php' );
thisismyurl_shadow_require_ajax_handler( $thisismyurl_shadow_ajax_path, 'class-ajax-treatment-maturity.php' );

// Register dashboard handlers explicitly for real-time updates.
if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Ajax\\Get_Dashboard_Data_Handler' ) ) {
	\ThisIsMyURL\Shadow\Admin\Ajax\Get_Dashboard_Data_Handler::register();
}

if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Ajax\\Save_Dashboard_Prefs_Handler' ) ) {
	\ThisIsMyURL\Shadow\Admin\Ajax\Save_Dashboard_Prefs_Handler::register();
}

if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Ajax\\Heartbeat_Diagnostics_Handler' ) ) {
	\ThisIsMyURL\Shadow\Admin\Ajax\Heartbeat_Diagnostics_Handler::register();
}

if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Ajax\\Post_Scan_Treatments_Handler' ) ) {
	\ThisIsMyURL\Shadow\Admin\Ajax\Post_Scan_Treatments_Handler::register();
}
