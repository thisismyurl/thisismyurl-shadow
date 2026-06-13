<?php
/**
 * Christopher Ross Shadow Uninstall Handler
 *
 * Handles plugin uninstall including:
 * - Clean up plugin data
 *
 * Philosophy:
 * - Commandment #10 (Beyond Pure) - Privacy first
 * - Commandment #1 (Helpful Neighbor) - Respectful data handling
 *
 * @package ThisIsMyURL\Shadow
 * @since 0.6095
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Clean up plugin data based on settings
 *
 * Check if user wants to keep data (for potential reinstall)
 */
$keep_data = get_option( 'thisismyurl_shadow_keep_data_on_uninstall', false );

// Always clear scheduled events during uninstall, even when site data is kept.
$cron_hooks = array(
	'thisismyurl_shadow_run_automated_fixes',
	'thisismyurl_shadow_run_data_cleanup',
	'thisismyurl_shadow_send_scheduled_reports',
	'thisismyurl_shadow_run_automatic_diagnostic_scan',
	'thisismyurl_shadow_run_scheduled_backup',
	'thisismyurl_shadow_scheduled_deep_scan',
	'thisismyurl_shadow_run_offpeak_operations',
	'thisismyurl_shadow_run_overnight_fixes',
	'thisismyurl_shadow_hourly_cleanup',
);

foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

if ( ! $keep_data ) {
	global $wpdb;
	$option_like = $wpdb->esc_like( 'thisismyurl_shadow_' ) . '%';
	$transient_like = $wpdb->esc_like( '_transient_thisismyurl_shadow_' ) . '%';
	$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_thisismyurl_shadow_' ) . '%';

	// Remove plugin options
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $option_like ) );

	// Remove user meta
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $option_like ) );

	// No custom Christopher Ross Shadow tables are maintained.

	// Clear transients
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$transient_like,
			$transient_timeout_like
		)
	);
}

// Uninstall analytics submission removed.
