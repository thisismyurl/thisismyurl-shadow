<?php
/**
 * Treatment: Query Debug Logging Disabled (Production)
 *
 * Writes `define( 'SAVEQUERIES', false )` to wp-config.php to disable
 * WordPress's SAVEQUERIES feature on production sites. SAVEQUERIES stores
 * every database query in memory (wpdb::$queries), which increases memory
 * usage and imposes a per-request performance tax even when the queries are
 * never inspected.
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
 * Disables SAVEQUERIES in wp-config.php for production environments.
 */
class Treatment_Query_Debug_Logging_Disabled_Production extends Treatment_Base {

	use File_Write_Helpers;

	/** @var string */
	protected static $slug = 'query-debug-logging-disabled-production';

	const MARKER_SLUG = 'query-debug-logging-disabled-production';
	const DEFINE_LINE = "define( 'SAVEQUERIES', false ); // Shadow by Christopher Ross: disable query logging in production";

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
		return __( 'Set SAVEQUERIES to false in wp-config.php (disable query logging in production)', 'thisismyurl-shadow' );
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
			"  // thisismyurl_shadow_MARKER_START: query-debug-logging-disabled-production",
			"  " . self::DEFINE_LINE,
			"  // thisismyurl_shadow_MARKER_END: query-debug-logging-disabled-production",
			"Save the file.",
			"Reload your WordPress site to confirm it works.",
		] );
	}
}

Treatment_Query_Debug_Logging_Disabled_Production::boot();
