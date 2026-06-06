<?php
/**
 * Treatment: Autosave Interval Optimized
 *
 * Writes `define( 'AUTOSAVE_INTERVAL', 120 )` to wp-config.php to reduce
 * the WordPress autosave frequency from 60 s to 120 s. This halves the
 * number of autosave database writes, reducing server load on content-heavy
 * sites without meaningfully increasing data-loss risk.
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
 * Reduces autosave frequency by setting AUTOSAVE_INTERVAL to 120 seconds.
 */
class Treatment_Autosave_Interval_Optimized extends Treatment_Base {

	use File_Write_Helpers;

	/** @var string */
	protected static $slug = 'autosave-interval-optimized';

	/** Marker slug written to wp-config.php. */
	const MARKER_SLUG = 'autosave-interval-optimized';

	/** The define() statement inserted. */
	const DEFINE_LINE = "define( 'AUTOSAVE_INTERVAL', 120 ); // Christopher Ross Shadow: reduce autosave frequency";

	/**
	 * Self-register with File_Write_Registry on class load.
	 *
	 * Called once when the autoloader includes this file.
	 */
	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	/** @return string */
	public static function get_finding_id(): string {
		return self::$slug;
	}

	/** @return string */
	public static function get_risk_level(): string {
		return 'high';
	}

	/** @return array */
	public static function apply(): array {
		return self::write_wp_config_define(
			self::get_target_file(),
			self::MARKER_SLUG,
			self::DEFINE_LINE
		);
	}

	/** @return array */
	public static function undo(): array {
		return self::remove_wp_config_block( self::get_target_file(), self::MARKER_SLUG );
	}

	// =========================================================================
	// File_Write_Registry interface
	// =========================================================================

	/** @return string */
	public static function get_target_file(): string {
		return ABSPATH . 'wp-config.php';
	}

	/** @return string */
	public static function get_file_label(): string {
		return 'wp-config.php';
	}

	/** @return string */
	public static function get_proposed_change_summary(): string {
		return __( 'Set AUTOSAVE_INTERVAL to 120 seconds in wp-config.php', 'thisismyurl-shadow' );
	}

	/** @return string */
	public static function get_proposed_snippet(): string {
		return self::DEFINE_LINE;
	}

	/** @return string */
	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode( "\n", [
			"Connect to your server via SFTP or cPanel File Manager.",
			"Navigate to: {$file}",
			"Open the file in a text editor.",
			"Find and delete the following three lines:",
			"  // thisismyurl_shadow_MARKER_START: autosave-interval-optimized",
			"  " . self::DEFINE_LINE,
			"  // thisismyurl_shadow_MARKER_END: autosave-interval-optimized",
			"Save the file.",
			"Reload your WordPress site to confirm it works.",
		] );
	}
}

// Self-register immediately on file load.
Treatment_Autosave_Interval_Optimized::boot();
