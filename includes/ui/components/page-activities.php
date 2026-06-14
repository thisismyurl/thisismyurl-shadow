<?php
/**
 * Page Activity Display Component
 *
 * Reusable component for displaying page-specific activities with real-time AJAX updates.
 * Displays filtered activities based on current page context (tools, reports, workflows, etc.)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Views
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render page-specific activity display
 *
 * @param string $context Page context (tools, reports, workflows, settings, security, performance)
 * @param int    $limit Maximum activities to display (default: 10)
 * @param string $report_slug Optional report slug for report-specific filtering
 * @return void
 */
function thisismyurl_shadow_render_page_activities( string $context = '', int $limit = 10, string $report_slug = '' ): void {
	$show_activities = (bool) apply_filters( 'thisismyurl_shadow_show_page_activities', false, $context, $limit, $report_slug );
	if ( ! $show_activities ) {
		return;
	}

	if ( empty( $context ) ) {
		return;
	}

	// The dedicated activity timeline JS bundle is not shipped in the current build,
	// so this component renders nothing until that bundle is added back.
	return;
}

/**
 * Localization data for activity display
 */
function thisismyurl_shadow_activity_display_localization(): void {
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( ! $screen || strpos( (string) $screen->id, 'thisismyurl-shadow' ) === false ) {
			return;
		}
	}

	if ( ! wp_script_is( 'thisismyurl-shadow-page-activities', 'enqueued' ) ) {
		return;
	}

	wp_localize_script( 'thisismyurl-shadow-page-activities', 'thisismyurl_shadow_i18n', array(
		'no_activities' => __( 'No activities yet', 'thisismyurl-shadow' ),
		'loading'       => __( 'Loading activity...', 'thisismyurl-shadow' ),
		'report_label'  => __( 'Report', 'thisismyurl-shadow' ),
		'page_label'    => __( 'Page', 'thisismyurl-shadow' ),
		'of_label'      => __( 'of', 'thisismyurl-shadow' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'thisismyurl_shadow_activity_display_localization' );
add_action( 'admin_enqueue_scripts', 'thisismyurl_shadow_activity_display_localization' );
