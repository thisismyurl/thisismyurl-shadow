<?php
/**
 * Bootstrap Autoloader
 *
 * Automatically loads all Christopher Ross Shadow classes in dependency order.
 * Replaces 130+ manual require_once calls with intelligent auto-loading.
 *
 * Philosophy:
 * - Commandment #7: Ridiculously Good (zero manual loading)
 * - Phase 4: Bootstrap Consolidation (DRY bootstrap)
 * - Convention over configuration
 *
 * @package    Christopher Ross Shadow
 * @subpackage Core
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap_Autoloader Class
 *
 * Intelligently loads all Christopher Ross Shadow classes with proper dependency ordering.
 *
 * @since 0.6095
 */
class Bootstrap_Autoloader {

	/**
	 * Critical classes that must load before everything else.
	 *
	 * These are loaded in order, as each may depend on the previous.
	 *
	 * @var array
	 */
	private static $critical_classes = array(
		// Phase 2 infrastructure (loads first)
		'includes/systems/core/class-hook-subscriber-base.php',
		'includes/systems/core/class-hook-registry.php',

		// Base classes (required by many features)
		'includes/systems/core/class-ajax-handler-base.php',
		'includes/systems/core/class-diagnostic-base.php',
		'includes/systems/core/class-treatment-interface.php',
		'includes/systems/core/class-treatment-base.php',

		// Admin base classes
		'includes/admin/pages/class-settings-page-base.php',

		// Core utilities
		'includes/systems/core/class-security-validator.php',
		'includes/systems/core/class-external-request-guard.php',
		'includes/systems/core/class-secret-manager.php',
		'includes/systems/core/class-secret-audit-log.php',
		'includes/systems/core/class-activity-logger.php',
		'includes/systems/core/class-error-handler.php',
		'includes/systems/core/class-cache-manager.php',
		'includes/systems/core/class-kpi-tracker.php',
		'includes/systems/core/class-readiness-registry.php',
                'includes/systems/core/class-environment-detector.php',
                'includes/systems/core/class-diagnostic-metadata.php',
                'includes/systems/core/class-treatment-metadata.php',
				'includes/systems/core/class-treatment-toggle-policy.php',
                'includes/systems/core/class-settings-registry.php',
				'includes/systems/core/class-training-event-cpt.php',
		'includes/systems/core/class-admin-asset-registry.php',
		'includes/systems/core/class-database-migrator.php',
		'includes/systems/core/class-form-param-helper.php',
		'includes/systems/core/class-options-manager.php',
		'includes/systems/core/class-abstract-registry.php',
		'includes/systems/core/class-upgrade-path-helper.php',
		'includes/systems/core/class-finding-utils.php',
		'includes/diagnostics/helpers/class-diagnostic-wp-settings-helper.php',
		'includes/diagnostics/helpers/class-diagnostic-server-environment-helper.php',
		'includes/diagnostics/helpers/class-diagnostic-request-helper.php',
		'includes/diagnostics/helpers/class-diagnostic-admin-page-html-helper.php',
		// Plugin initialization
		'includes/systems/core/class-plugin-bootstrap.php',

		// Diagnostic registry
		'includes/systems/diagnostics/class-diagnostic-registry.php',
		'includes/systems/treatments/class-treatment-registry.php',

		// Treatment functions
		'includes/systems/core/class-category-metadata.php',
		'includes/systems/core/functions-category-metadata.php',

		// Helper functions
		'includes/utils/helpers/form-controls.php',
		'includes/utils/helpers/html-fetcher-helpers.php',
		'includes/utils/helpers/findings-cache-helpers.php',
		'includes/utils/helpers/feature-status-helpers.php',

		// View functions
		'includes/ui/views/functions-page-layout.php',
		'includes/ui/views/menu-stubs.php',
		'includes/ui/views/dashboard-shared.php',
		'includes/ui/views/dashboard-page-v2.php',
		'includes/ui/dashboard/gauges-module.php',

		// Menu and routing
		'includes/systems/core/class-menu-manager.php',
		'includes/systems/core/class-ajax-router.php',
		'includes/systems/core/class-hooks-initializer.php',
		'includes/systems/dashboard/class-asset-manager.php',
		'includes/systems/dashboard/class-asset-optimizer.php',

		// Monitoring/tracking
		'includes/features/monitoring/class-wordpress-hooks-tracker.php',

		// Persistent treatment application hooks
		'includes/utils/class-treatment-hooks.php',

		// AJAX handlers loader
		'includes/admin/ajax/ajax-handlers-loader.php',
	);

	/**
	 * Feature directories to auto-load.
	 *
	 * All PHP files in these directories will be loaded automatically.
	 *
	 * @var array
	 */
	private static $feature_directories = array(
		'includes/content/post-types/',
		'includes/content/',
		'includes/blocks/',
		// Utilities are loaded selectively via required_files/bootstrap, not as a blanket directory include.
		'includes/admin/',
		'includes/analytics/',
		'includes/features/',
	);

	/**
	 * Prefixes that should only be loaded for admin-side contexts.
	 *
	 * @var array<int, string>
	 */
	private static $admin_only_prefixes = array(
		'includes/admin/',
		'includes/ui/',
		'includes/systems/dashboard/',
		'includes/systems/core/class-menu-manager.php',
		'includes/systems/core/class-ajax-router.php',
		'includes/systems/core/class-hooks-initializer.php',
	);

	/**
	 * Cache key for loaded files.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'thisismyurl_shadow_autoloaded_files';

	/**
	 * Initialize autoloader and load all classes.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function init(): void {
		// Load essential classes first (in order)
		self::load_critical_classes();

		// Load feature classes
		self::load_features();

		// Fire autoloading complete hook
		do_action( 'thisismyurl_shadow_autoloader_complete' );
	}

	/**
	 * Load critical classes in dependency order.
	 *
	 * @since 0.6095
	 * @return void
	 */
	private static function load_critical_classes(): void {
		foreach ( self::$critical_classes as $file ) {
			if ( self::is_admin_only_path( $file ) && ! self::is_admin_runtime_context() ) {
				continue;
			}

			$path = THISISMYURL_SHADOW_PATH . $file;

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Load feature classes from directories.
	 *
	 * @since 0.6095
	 * @return void
	 */
	private static function load_features(): void {
		$cache_key = self::get_feature_cache_key();

		// Get cached file list or discover them
		$files = wp_cache_get( $cache_key, 'thisismyurl-shadow' );

		if ( false === $files ) {
			$files = self::discover_feature_files();
			wp_cache_set( $cache_key, $files, 'thisismyurl-shadow', HOUR_IN_SECONDS );
		}

		// Load all discovered files
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Discover all PHP files in feature directories.
	 *
	 * @since 0.6095
	 * @return array Array of file paths.
	 */
	private static function discover_feature_files(): array {
		$files = array();

		foreach ( self::$feature_directories as $directory ) {
			if ( self::is_admin_only_path( $directory ) && ! self::is_admin_runtime_context() ) {
				continue;
			}

			$dir_path = THISISMYURL_SHADOW_PATH . $directory;

			if ( ! is_dir( $dir_path ) ) {
				continue;
			}

			$discovered = self::scan_directory_recursive( $dir_path );
			$files      = array_merge( $files, $discovered );
		}

		return $files;
	}

	/**
	 * Determine whether a file path should only load in admin contexts.
	 *
	 * @since 0.6095
	 * @param  string $path Relative plugin path.
	 * @return bool True when the path is admin-only.
	 */
	private static function is_admin_only_path( string $path ): bool {
		foreach ( self::$admin_only_prefixes as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether current runtime should include admin files.
	 *
	 * @since 0.6095
	 * @return bool True when current request is admin, AJAX, CLI, or WP-CLI.
	 */
	private static function is_admin_runtime_context(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		return false;
	}

	/**
	 * Recursively scan directory for PHP files.
	 *
	 * @since 0.6095
	 * @param  string $directory Directory to scan.
	 * @return array Array of file paths.
	 */
	private static function scan_directory_recursive( string $directory ): array {
		$files = array();

		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return $files;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$file_path = $file->getPathname();

				if ( false !== strpos( $file_path, '/includes/ui/reports/' ) || false !== strpos( $file_path, '/includes/features/onboarding/data/' ) ) {
					continue;
				}

				// Skip test files
				if ( false !== strpos( $file_path, '/tests/' ) ) {
					continue;
				}

				// Skip vendor files
				if ( false !== strpos( $file_path, '/vendor/' ) ) {
					continue;
				}

				$files[] = $file_path;
			}
		}

		return $files;
	}

	/**
	 * Clear autoloader cache.
	 *
	 * Useful during development when new files are added.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function clear_cache(): void {
		wp_cache_delete( self::CACHE_KEY . '_admin', 'thisismyurl-shadow' );
		wp_cache_delete( self::CACHE_KEY . '_frontend', 'thisismyurl-shadow' );
	}

	/**
	 * Build runtime-specific cache key for feature file discovery.
	 *
	 * @since 0.6095
	 * @return string Cache key suffix by request context.
	 */
	private static function get_feature_cache_key(): string {
		return self::is_admin_runtime_context()
			? self::CACHE_KEY . '_admin'
			: self::CACHE_KEY . '_frontend';
	}

}
