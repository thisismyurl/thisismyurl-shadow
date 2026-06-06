<?php
/**
 * Treatment: WP Debug Display Off
 *
 * Writes `define( 'WP_DEBUG_DISPLAY', false )` to wp-config.php to prevent
 * PHP errors from being displayed in the browser on production sites.
 * Errors are still logged (if WP_DEBUG_LOG is enabled) but are never shown
 * to visitors, preventing information disclosure.
 *
 * File written: wp-config.php
 * Risk level:   high (file write)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;
use ThisIsMyURL\Shadow\Admin\File_Write_Registry;

// Load the shared file-write helpers trait.
require_once __DIR__ . '/trait-file-write-helpers.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disables on-screen PHP error display in production.
 */
class Treatment_Wp_Debug_Display_Off extends Treatment_Base {

	use File_Write_Helpers;

	/** @var string */
	protected static $slug = 'wp-debug-display-off';

	const MARKER_SLUG = 'wp-debug-display-off';
	const DEFINE_LINE = "define( 'WP_DEBUG_DISPLAY', false ); // Christopher Ross Shadow: hide errors from visitors";

	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	public static function get_finding_id(): string {
		return self::$slug;
	}

	public static function get_risk_level(): string {
		return 'high';
	}

	public static function apply(): array {
		return self::write_wp_config_define(
			self::get_target_file(),
			self::MARKER_SLUG,
			self::DEFINE_LINE
		);
	}

	public static function undo(): array {
		return self::remove_wp_config_block( self::get_target_file(), self::MARKER_SLUG );
	}

	// =========================================================================
	// File_Write_Registry interface
	// =========================================================================

	public static function get_target_file(): string {
		return ABSPATH . 'wp-config.php';
	}

	public static function get_file_label(): string {
		return 'wp-config.php';
	}

	public static function get_proposed_change_summary(): string {
		return __( 'Disable WP_DEBUG_DISPLAY in wp-config.php (hide errors from visitors)', 'thisismyurl-shadow' );
	}

	public static function get_proposed_snippet(): string {
		return self::DEFINE_LINE;
	}

	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode( "\n", [
			"Connect to your server via SFTP or cPanel File Manager.",
			"Navigate to: {$file}",
			"Open the file in a text editor.",
			"Find and delete the following three lines:",
			"  // thisismyurl_shadow_MARKER_START: wp-debug-display-off",
			"  " . self::DEFINE_LINE,
			"  // thisismyurl_shadow_MARKER_END: wp-debug-display-off",
			"Save the file.",
			"Reload your WordPress site to confirm it works.",
		] );
	}
}

Treatment_Wp_Debug_Display_Off::boot();
