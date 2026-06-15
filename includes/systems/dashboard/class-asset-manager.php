<?php
/**
 * Asset Manager for Shadow by Christopher Ross
 *
 * Centralized CSS/JS enqueuing for all Shadow by Christopher Ross pages.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Admin
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue a style only when the asset file exists.
 *
 * @param string $handle       Style handle.
 * @param string $relative_url Relative asset path from plugin root.
 * @param array  $deps         Optional dependencies.
 * @return bool True when enqueued.
 */
function thisismyurl_shadow_enqueue_style_if_exists( $handle, $relative_url, $deps = array() ) {
	$asset_path = THISISMYURL_SHADOW_PATH . ltrim( (string) $relative_url, '/' );
	if ( ! file_exists( $asset_path ) ) {
		return false;
	}

	$deps = array_values(
		array_filter(
			(array) $deps,
			static function ( $dep ): bool {
				return is_string( $dep ) && '' !== $dep && wp_style_is( $dep, 'registered' );
			}
		)
	);

	wp_enqueue_style(
		$handle,
		THISISMYURL_SHADOW_URL . ltrim( (string) $relative_url, '/' ),
		$deps,
		(string) filemtime( $asset_path )
	);

	return true;
}

/**
 * Enqueue admin pages common assets.
 *
 * @param string $hook Current admin hook.
 * @return void
 */
function thisismyurl_shadow_enqueue_admin_pages_assets( $hook ) {
	if ( strpos( $hook, 'thisismyurl-shadow' ) === false ) {
		return;
	}

	// Enqueue unified design system (replaces all old CSS files)
	thisismyurl_shadow_enqueue_style_if_exists( 'thisismyurl-shadow-system', 'assets/css/thisismyurl-shadow-system.css' );

	// Enqueue dashboard JS (vanilla, no jQuery dependency).
	$dashboard_script_path = THISISMYURL_SHADOW_PATH . 'assets/js/thisismyurl-shadow-dashboard.js';
	if ( file_exists( $dashboard_script_path ) ) {
		$dashboard_script_version = (string) filemtime( $dashboard_script_path );
		wp_enqueue_script(
			'thisismyurl-shadow-dashboard',
			THISISMYURL_SHADOW_URL . 'assets/js/thisismyurl-shadow-dashboard.js',
			array(),
			$dashboard_script_version,
			true
		);
	}

	if ( ! wp_script_is( 'thisismyurl-shadow-dashboard', 'enqueued' ) ) {
		return;
	}

	// Localize common admin data.
	\ThisIsMyURL\Shadow\Core\Admin_Asset_Registry::localize_with_ajax_nonce(
		'thisismyurl-shadow-dashboard',
		'thisismyurlShadowAdmin',
		'thisismyurl_shadow_admin',
		array(
			'locale' => get_locale(),
			'i18n'   => array(
				'saving'        => __( 'Saving...', 'thisismyurl-shadow' ),
				'saved'         => __( 'Saved successfully!', 'thisismyurl-shadow' ),
				'error'         => __( 'An error occurred. Please try again.', 'thisismyurl-shadow' ),
				'confirmDelete' => __( 'Are you sure you want to delete this?', 'thisismyurl-shadow' ),
				'working'       => __( 'Working on it...', 'thisismyurl-shadow' ),
				'workingDetails' => __( 'This can take a few minutes. You can keep this tab open while we finish.', 'thisismyurl-shadow' ),
				'cancel'        => __( 'Cancel', 'thisismyurl-shadow' ),
				'creatingBackup' => __( 'Creating backup...', 'thisismyurl-shadow' ),
				'backupDetails'  => __( 'This can take a few minutes, depending on your site size.', 'thisismyurl-shadow' ),
				'restoringBackup' => __( 'Restoring backup...', 'thisismyurl-shadow' ),
				'restoreDetails' => __( 'Please keep this tab open while we restore your site.', 'thisismyurl-shadow' ),
				'deletingBackup' => __( 'Deleting backup...', 'thisismyurl-shadow' ),
				'deleteDetails'  => __( 'This should only take a moment.', 'thisismyurl-shadow' ),
				'findReplaceRunning' => __( 'Running find and replace...', 'thisismyurl-shadow' ),
				'findReplaceDetails' => __( 'We are updating your content safely.', 'thisismyurl-shadow' ),
				'runningDiagnostics' => __( 'Running diagnostics...', 'thisismyurl-shadow' ),
				'diagnosticsDetails' => __( 'This can take a few minutes.', 'thisismyurl-shadow' ),
				'diagnosticsProgressSteps' => array(
					__( 'Reviewing site settings and visibility options', 'thisismyurl-shadow' ),
					__( 'Checking titles, descriptions, and metadata', 'thisismyurl-shadow' ),
					__( 'Looking at mobile readiness and performance', 'thisismyurl-shadow' ),
					__( 'Scanning internal links and structure', 'thisismyurl-shadow' ),
					__( 'Reviewing structured data and sharing tags', 'thisismyurl-shadow' ),
				),
				'diagnosticsStepLabel' => __( 'Step {current} of {total}', 'thisismyurl-shadow' ),
				'diagnosticsElapsedLabel' => __( 'Elapsed', 'thisismyurl-shadow' ),
				'diagnosticsRemainingLabel' => __( 'Estimated remaining', 'thisismyurl-shadow' ),
				'runningScan'    => __( 'Running a scan...', 'thisismyurl-shadow' ),
				'scanDetails'    => __( 'We will update you when the scan is done.', 'thisismyurl-shadow' ),
				'generatingDna'  => __( 'Generating site DNA...', 'thisismyurl-shadow' ),
				'dnaDetails'     => __( 'We are gathering site details.', 'thisismyurl-shadow' ),
				'preparingReport' => __( 'Preparing report...', 'thisismyurl-shadow' ),
				'reportDetails'   => __( 'This can take a few minutes.', 'thisismyurl-shadow' ),
			),
		)
	);
}

/**
 * Register all asset enqueuing hooks.
 *
 * @return void
 */
function thisismyurl_shadow_register_asset_hooks() {
	add_action( 'admin_enqueue_scripts', 'thisismyurl_shadow_enqueue_admin_pages_assets' );
}

thisismyurl_shadow_register_asset_hooks();
