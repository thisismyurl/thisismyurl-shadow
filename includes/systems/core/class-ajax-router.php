<?php

/**
 * Shadow AJAX Router
 *
 * Centralizes registration of all AJAX handlers.
 * Extracted from thisismyurl-shadow.php as part of Phase 4.5 refactoring.
 *
 * Philosophy: Commandment #7 (Ridiculously Good - clear separation of concerns)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 */

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes and registers all AJAX handlers for Shadow by Christopher Ross
 */
class AJAX_Router {

	/**
	 * Register all AJAX handlers via auto-discovery.
	 *
	 * Scans the includes/admin/ajax/ directory and automatically
	 * registers all classes that extend AJAX_Handler_Base.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function init() {
		self::discover_and_register_handlers();
	}

	/**
	 * Auto-discover and register all AJAX handlers.
	 *
	 * Convention: All handlers extend AJAX_Handler_Base and live in
	 * includes/admin/ajax/ directory.
	 *
	 * @since 0.6095
	 * @return void
	 */
	private static function discover_and_register_handlers() {
		$ajax_dir = defined( 'THISISMYURL_SHADOW_PATH' ) ? THISISMYURL_SHADOW_PATH . 'includes/admin/ajax/' : '';

		if ( empty( $ajax_dir ) || ! is_dir( $ajax_dir ) ) {
			return;
		}

		// Get all PHP files in ajax directory.
		$files = glob( $ajax_dir . '*.php' );

		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			// Convert filename to expected class name.
			// Example: dismiss-finding-handler.php -> Dismiss_Finding_Handler.
			$basename   = basename( $file, '.php' );
			$class_name = self::filename_to_classname( $basename );
			$full_class = "\\ThisIsMyURL\\Shadow\\Admin\\Ajax\\{$class_name}";

			// Check if class exists and extends AJAX_Handler_Base.
			if ( class_exists( $full_class ) ) {
				if ( is_subclass_of( $full_class, '\\ThisIsMyURL\\Shadow\\Core\\AJAX_Handler_Base' ) ) {
					// Auto-register the handler.
					$full_class::register();
				}
			}
		}
	}

	/**
	 * Convert filename to class name.
	 *
	 * Examples:
	 * - dismiss-finding-handler.php -> Dismiss_Finding_Handler
	 * - class-site-dna-handler.php -> Site_Dna_Handler
	 *
	 * @since 0.6095
	 * @param  string $filename Filename without .php extension.
	 * @return string Class name.
	 */
	private static function filename_to_classname( $filename ) {
		// Remove 'class-' prefix if present.
		$filename = preg_replace( '/^class-/', '', $filename );

		// Split on hyphens, capitalize each part.
		$parts = array_map( 'ucfirst', explode( '-', $filename ) );

		// Join with underscores.
		return implode( '_', $parts );
	}
}
