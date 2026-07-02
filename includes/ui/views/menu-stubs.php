<?php
/**
 * Menu Callback Stubs
 *
 * Temporary stub functions for menu callbacks that don't have dedicated files yet.
 * These prevent fatal errors when menus are registered but the actual page isn't loaded.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'thisismyurl_shadow_render_findings' ) ) {
	/**
	 * Render Findings page
	 *
	 * @since 0.6095
	 */
	function thisismyurl_shadow_render_findings() {
		?>
		<div class="wrap wps-page-container">
			<?php
			thisismyurl_shadow_render_page_header(
				__( 'Findings', 'thisismyurl-shadow' ),
				__( 'Review site findings and their current status.', 'thisismyurl-shadow' ),
				'dashicons-grid-view'
			);
			?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'thisismyurl_shadow_render_workflow_builder' ) ) {
	/**
	 * Render the Automations (Workflow) page.
	 *
	 * Stub renderer for the gated Automations page. Registered by the menu manager
	 * once the core pages are released; without this the admin.php callback resolves
	 * to an undefined function and fatals under WP_DEBUG.
	 *
	 * @since 1.6183
	 */
	function thisismyurl_shadow_render_workflow_builder() {
		?>
		<div class="wrap wps-page-container">
			<?php
			thisismyurl_shadow_render_page_header(
				__( 'Automations', 'thisismyurl-shadow' ),
				__( 'Automation, scheduled tasks, and workflows.', 'thisismyurl-shadow' ),
				'dashicons-controls-repeat'
			);
			?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'thisismyurl_shadow_render_settings' ) ) {
	/**
	 * Render the Shadow Settings page.
	 *
	 * @since 0.6095
	 */
	function thisismyurl_shadow_render_settings() {
		$settings_view = THISISMYURL_SHADOW_PATH . 'includes/ui/views/settings-page.php';
		if ( file_exists( $settings_view ) ) {
			require_once $settings_view;
			return;
		}

		// Fallback when view file is missing.
		?>
		<div class="wrap wps-page-container">
			<?php
			thisismyurl_shadow_render_page_header(
				__( 'Settings', 'thisismyurl-shadow' ),
				__( 'Settings page could not be loaded.', 'thisismyurl-shadow' ),
				'dashicons-admin-settings'
			);
			?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'thisismyurl_shadow_enqueue_settings_assets' ) ) {
	/**
	 * Enqueue CSS and JS for the Settings page.
	 *
	 * @since 0.6095
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	function thisismyurl_shadow_enqueue_settings_assets( string $hook_suffix ): void {
		// Only load on the Shadow by Christopher Ross settings page.
		if ( false === strpos( $hook_suffix, 'thisismyurl-shadow-settings' ) ) {
			return;
		}

		wp_enqueue_style(
			'thisismyurl-shadow-settings-page',
			THISISMYURL_SHADOW_URL . 'assets/css/settings-page.css',
			array(),
			file_exists( THISISMYURL_SHADOW_PATH . 'assets/css/settings-page.css' )
				? (string) filemtime( THISISMYURL_SHADOW_PATH . 'assets/css/settings-page.css' )
				: THISISMYURL_SHADOW_VERSION
		);

		wp_enqueue_script(
			'thisismyurl-shadow-settings-page',
			THISISMYURL_SHADOW_URL . 'assets/js/settings-page.js',
			array( 'jquery' ),
			file_exists( THISISMYURL_SHADOW_PATH . 'assets/js/settings-page.js' )
				? (string) filemtime( THISISMYURL_SHADOW_PATH . 'assets/js/settings-page.js' )
				: THISISMYURL_SHADOW_VERSION,
			true
		);

		wp_localize_script(
			'thisismyurl-shadow-settings-page',
			'thisismyurlShadowSettingsData',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'adminNonce'        => wp_create_nonce( 'thisismyurl_shadow_admin' ),
				'scanSettingsNonce' => wp_create_nonce( 'thisismyurl_shadow_scan_settings' ),
				'i18n'              => array(
					'saving'              => __( 'Saving…', 'thisismyurl-shadow' ),
					'saved'               => __( 'Saved', 'thisismyurl-shadow' ),
					'saveError'           => __( 'Save failed', 'thisismyurl-shadow' ),
					'preferencesSaved'    => __( 'Preferences saved.', 'thisismyurl-shadow' ),
					'preferencesSaveFail' => __( 'Could not save preferences.', 'thisismyurl-shadow' ),
					'refreshFailed'       => __( 'Failed to refresh summary.', 'thisismyurl-shadow' ),
					'noItemsFound'        => __( 'No items found.', 'thisismyurl-shadow' ),
					'inventoryLoadFailed' => __( 'Failed to load inventory.', 'thisismyurl-shadow' ),
					'exporting'           => __( 'Exporting...', 'thisismyurl-shadow' ),
					'exportComplete'      => __( 'Export complete.', 'thisismyurl-shadow' ),
					'exportFailed'        => __( 'Export failed.', 'thisismyurl-shadow' ),
				),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'thisismyurl_shadow_enqueue_settings_assets' );

