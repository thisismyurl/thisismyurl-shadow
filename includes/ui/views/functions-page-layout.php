<?php
/**
 * Page Header/Footer Helper Functions
 *
 * Provides convenient functions for rendering consistent page headers and footers.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Views
 * @since 0.6095
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render page header with title, subtitle, and version tag.
 *
 * @since 0.6095
 * @param  string $title       Page title (required)
 * @param  string $subtitle    Page subtitle/description (optional)
 * @param  string $icon_class  Dashicons CSS class (optional, e.g., 'dashicons-admin-settings')
 * @param  string $icon_color  Icon color value (optional, default: var(--wps-primary))
 * @return void
 *
 * @example
 * thisismyurl_shadow_render_page_header(
 *     'Settings',
 *     'Configure plugin settings',
 *     'dashicons-admin-settings',
 *     'var(--wps-primary)'
 * );
 */
function thisismyurl_shadow_render_page_header( $title = '', $subtitle = '', $icon_class = '', $icon_color = 'var(--wps-primary)' ) {
	$header_template = THISISMYURL_SHADOW_PATH . 'includes/ui/views/page-header.php';

	if ( file_exists( $header_template ) ) {
		// Load the header template (variables are available as local scope)
		include $header_template;
		return;
	}

	// Fallback markup when template file is unavailable.
	echo '<div class="wps-page-header">';
	echo '<h1 class="wps-page-title">' . esc_html( (string) $title ) . '</h1>';
	if ( '' !== (string) $subtitle ) {
		echo '<p class="wps-page-subtitle">' . esc_html( (string) $subtitle ) . '</p>';
	}
	echo '</div>';
}

/**
 * Render a shared notice slot after the page header.
 *
 * @since 0.6095
 * @return void
 */
function thisismyurl_shadow_render_page_notice_slot() {
	echo '<div class="thisismyurl-shadow-page-notices" id="thisismyurl-shadow-page-notices"></div>';
}

/**
 * Load and render page-specific activities component
 *
 * Includes the page-activities component file which provides functions for
 * rendering real-time activity displays with AJAX auto-refresh.
 *
 * @since 0.6095
 * @return void
 */
function thisismyurl_shadow_load_page_activities_component() {
	if ( ! function_exists( 'thisismyurl_shadow_render_page_activities' ) ) {
		$component_file = THISISMYURL_SHADOW_PATH . 'includes/ui/components/page-activities.php';
		if ( file_exists( $component_file ) ) {
			require_once $component_file;
		}
	}
}


// Load page activities component on init
add_action( 'wp_loaded', 'thisismyurl_shadow_load_page_activities_component' );

// Provide a consistent placement target for notices right under the header.
add_action( 'thisismyurl_shadow_after_page_header', 'thisismyurl_shadow_render_page_notice_slot', 5 );