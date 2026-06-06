<?php
/**
 * Treatment: PHP Memory Limit Optimized
 *
 * Writes `define( 'WP_MEMORY_LIMIT', '256M' )` to wp-config.php so WordPress
 * requests are allowed to use up to 256 MB of memory. A separate constant
 * `WP_MAX_MEMORY_LIMIT` is set to 512M for admin/WP-Cron processes. Both use
 * the marker-block approach and are fully reversible.
 *
 * Note: `WP_MEMORY_LIMIT` is a WordPress-level cap; PHP's `memory_limit` in
 * php.ini must be >= this value for the constant to have any effect. If php.ini
 * is already set lower, the effective limit will be the lower of the two.
 *
 * File written: wp-config.php
 * Risk level:   medium (file write, non-destructive)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;
use ThisIsMyURL\Shadow\Admin\File_Write_Registry;

require_once __DIR__ . '/trait-file-write-helpers.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add WordPress memory limit defines to wp-config.php in a reversible block.
 *
 * This treatment exists because memory-related performance issues are common,
 * but the correct fix for WordPress beginners is not always obvious. The class
 * wraps the change in markers so Christopher Ross Shadow can show a preview, apply it safely,
 * and later remove only its own lines if the admin chooses to roll back.
 */
class Treatment_Php_Memory_Limit_Optimized extends Treatment_Base {

	use File_Write_Helpers;

	/**
	 * Finding identifier handled by this treatment.
	 *
	 * @since 0.6095
	 * @var   string
	 */
	protected static $slug = 'php-memory-limit-optimized';

	/**
	 * Marker slug used to isolate the inserted block in wp-config.php.
	 *
	 * @since 0.6095
	 * @var   string
	 */
	const MARKER_SLUG = 'php-memory-limit-optimized';

	/**
	 * PHP code block written into wp-config.php when the treatment is applied.
	 *
	 * @since 0.6095
	 * @var   string
	 */
	const DEFINE_BLOCK = "define( 'WP_MEMORY_LIMIT', '256M' );     // Christopher Ross Shadow: front-end PHP memory cap\n" .
	                     "define( 'WP_MAX_MEMORY_LIMIT', '512M' ); // Christopher Ross Shadow: admin/WP-Cron PHP memory cap";

	/**
	 * Register this treatment with the file-write review system.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	/**
	 * Return the finding identifier this treatment addresses.
	 *
	 * @since  0.6095
	 * @return string Diagnostic slug handled by this treatment.
	 */
	public static function get_finding_id(): string {
		return self::$slug;
	}

	/**
	 * Report the relative risk level of editing wp-config.php for this fix.
	 *
	 * @since  0.6095
	 * @return string Risk level label consumed by treatment orchestration.
	 */
	public static function get_risk_level(): string {
		return 'medium';
	}

	/**
	 * Insert the memory-limit definitions into wp-config.php.
	 *
	 * @since  0.6095
	 * @return array<string,mixed> Result payload from the shared file-write helper.
	 */
	public static function apply(): array {
		return self::write_wp_config_define(
			self::get_target_file(),
			self::MARKER_SLUG,
			self::DEFINE_BLOCK
		);
	}

	/**
	 * Remove the memory-limit block previously inserted by this treatment.
	 *
	 * @since  0.6095
	 * @return array<string,mixed> Result payload from the shared file-write helper.
	 */
	public static function undo(): array {
		return self::remove_wp_config_block( self::get_target_file(), self::MARKER_SLUG );
	}

	// =========================================================================
	// File_Write_Registry interface
	// =========================================================================

	/**
	 * Return the absolute path to the file this treatment edits.
	 *
	 * @since  0.6095
	 * @return string Absolute path to wp-config.php.
	 */
	public static function get_target_file(): string {
		return ABSPATH . 'wp-config.php';
	}

	/**
	 * Return the short label used for the edited file in review screens.
	 *
	 * @since  0.6095
	 * @return string Human-readable file label.
	 */
	public static function get_file_label(): string {
		return 'wp-config.php';
	}

	/**
	 * Summarize the intended wp-config.php change for review UIs.
	 *
	 * @since  0.6095
	 * @return string Localized one-line summary of the change.
	 */
	public static function get_proposed_change_summary(): string {
		return __( 'Set WP_MEMORY_LIMIT to 256M and WP_MAX_MEMORY_LIMIT to 512M in wp-config.php', 'thisismyurl-shadow' );
	}

	/**
	 * Return the exact marker-wrapped snippet proposed for insertion.
	 *
	 * @since  0.6095
	 * @return string Previewable wp-config.php code block.
	 */
	public static function get_proposed_snippet(): string {
		return "// thisismyurl_shadow_MARKER_START: php-memory-limit-optimized\n" .
		       self::DEFINE_BLOCK . "\n" .
		       "// thisismyurl_shadow_MARKER_END: php-memory-limit-optimized";
	}

	/**
	 * Provide explicit manual rollback steps for inexperienced admins.
	 *
	 * @since  0.6095
	 * @return string Multi-line rollback instructions for SFTP or file-manager use.
	 */
	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode( "\n", [
			"Connect to your server via SFTP or cPanel File Manager.",
			"Navigate to: {$file}",
			"Open the file in a text editor.",
			"Find and delete the block between these two marker lines (inclusive):",
			"  // thisismyurl_shadow_MARKER_START: php-memory-limit-optimized",
			"  define( 'WP_MEMORY_LIMIT', '256M' );",
			"  define( 'WP_MAX_MEMORY_LIMIT', '512M' );",
			"  // thisismyurl_shadow_MARKER_END: php-memory-limit-optimized",
			"Save the file.",
			"WordPress will revert to its built-in memory defaults.",
		] );
	}
}

Treatment_Php_Memory_Limit_Optimized::boot();
