<?php
/**
 * Treatment: Configure silent WordPress error logging
 *
 * Ensures wp-config.php is set to log errors silently by enabling WP_DEBUG,
 * enabling WP_DEBUG_LOG, disabling WP_DEBUG_DISPLAY, and disabling PHP
 * display_errors at runtime.
 *
 * Undo restores the original wp-config.php contents from backup.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.7056
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;
use ThisIsMyURL\Shadow\Admin\File_Write_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Treatment_Error_Logging extends Treatment_Base {

	/** @var string */
	protected static $slug = 'error-logging';

	private const BACKUP_OPTION = 'thisismyurl_shadow_error_logging_wp_config_backup';
	private const MARKER_SLUG   = 'error-logging';
	private const BLOCK         = "define( 'WP_DEBUG', true ); // Christopher Ross Shadow: enable WordPress debug mode for logging\ndefine( 'WP_DEBUG_LOG', true ); // Christopher Ross Shadow: write errors to wp-content/debug.log\ndefine( 'WP_DEBUG_DISPLAY', false ); // Christopher Ross Shadow: never show errors to visitors\n@ini_set( 'display_errors', 0 ); // Christopher Ross Shadow: disable PHP error display";

	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	public static function get_risk_level(): string {
		return 'high';
	}

	public static function apply(): array {
		$file_path = self::locate_wp_config();
		if ( null === $file_path || ! is_readable( $file_path ) || ! wp_is_writable( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php could not be located or is not writable.', 'thisismyurl-shadow' ),
			);
		}

		$content = (string) file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( '' === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read wp-config.php.', 'thisismyurl-shadow' ),
			);
		}

		$has_wp_debug_true     = preg_match( "/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*true\s*\)\s*;/i", $content );
		$has_wp_debug_log_true = preg_match( "/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*true\s*\)\s*;/i", $content );
		$has_display_false     = preg_match( "/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,\s*false\s*\)\s*;/i", $content );
		$has_ini_hide          = preg_match( "/@?ini_set\s*\(\s*['\"]display_errors['\"]\s*,\s*0\s*\)\s*;/i", $content );

		if ( $has_wp_debug_true && $has_wp_debug_log_true && $has_display_false && $has_ini_hide ) {
			return array(
				'success' => true,
				'message' => __( 'Silent error logging is already configured. No changes made.', 'thisismyurl-shadow' ),
			);
		}

		update_option( self::BACKUP_OPTION, base64_encode( $content ), false );

		$updated = preg_replace( '/\n\/\/ thisismyurl_shadow_MARKER_START: error-logging\n.*?\n\/\/ thisismyurl_shadow_MARKER_END: error-logging\n/s', "\n", $content );
		if ( null === $updated ) {
			$updated = $content;
		}

		$replacements = array(
			'WP_DEBUG'         => "define( 'WP_DEBUG', true ); // Christopher Ross Shadow: enable WordPress debug mode for logging",
			'WP_DEBUG_LOG'     => "define( 'WP_DEBUG_LOG', true ); // Christopher Ross Shadow: write errors to wp-content/debug.log",
			'WP_DEBUG_DISPLAY' => "define( 'WP_DEBUG_DISPLAY', false ); // Christopher Ross Shadow: never show errors to visitors",
		);

		$missing_lines = array();
		foreach ( $replacements as $constant => $line ) {
			$updated = preg_replace(
				"/^[ \t]*define\s*\(\s*['\"]" . preg_quote( $constant, '/' ) . "['\"]\s*,\s*.*?\)\s*;.*$/mi",
				$line,
				$updated,
				1,
				$replaced
			);

			if ( 0 === $replaced ) {
				$missing_lines[] = $line;
			}
		}

		if ( ! preg_match( "/@?ini_set\s*\(\s*['\"]display_errors['\"]\s*,\s*0\s*\)\s*;/i", $updated ) ) {
			$missing_lines[] = "@ini_set( 'display_errors', 0 ); // Christopher Ross Shadow: disable PHP error display";
		}

		if ( ! empty( $missing_lines ) ) {
			$block   = "\n// thisismyurl_shadow_MARKER_START: " . self::MARKER_SLUG . "\n" . implode( "\n", $missing_lines ) . "\n// thisismyurl_shadow_MARKER_END: " . self::MARKER_SLUG . "\n";
			$updated = preg_replace( '/^<\?php\s*/', "$0" . $block, $updated, 1 );
		}

		if ( null === $updated || false === file_put_contents( $file_path, $updated ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return array(
				'success' => false,
				'message' => __( 'Could not write the updated wp-config.php file.', 'thisismyurl-shadow' ),
			);
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $file_path, true );
		}

		return array(
			'success' => true,
			'message' => __( 'Silent error logging was configured in wp-config.php.', 'thisismyurl-shadow' ),
		);
	}

	public static function undo(): array {
		$file_path = self::locate_wp_config();
		$backup    = get_option( self::BACKUP_OPTION, '' );

		if ( null === $file_path || '' === $backup ) {
			return array(
				'success' => false,
				'message' => __( 'No wp-config.php backup was stored for this fix.', 'thisismyurl-shadow' ),
			);
		}

		$original = base64_decode( (string) $backup ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the plugin's own base64-encoded config-backup snapshot before restore.
		if ( false === $original || false === file_put_contents( $file_path, $original ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return array(
				'success' => false,
				'message' => __( 'Could not restore the previous wp-config.php contents.', 'thisismyurl-shadow' ),
			);
		}

		delete_option( self::BACKUP_OPTION );
		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $file_path, true );
		}

		return array(
			'success' => true,
			'message' => __( 'The previous wp-config.php file has been restored.', 'thisismyurl-shadow' ),
		);
	}

	public static function get_target_file(): string {
		return (string) self::locate_wp_config();
	}

	public static function get_file_label(): string {
		return 'wp-config.php';
	}

	public static function get_proposed_change_summary(): string {
		return __( 'Configure wp-config.php for silent error logging (WP_DEBUG_LOG on, display off)', 'thisismyurl-shadow' );
	}

	public static function get_proposed_snippet(): string {
		return "// thisismyurl_shadow_MARKER_START: " . self::MARKER_SLUG . "\n" . self::BLOCK . "\n// thisismyurl_shadow_MARKER_END: " . self::MARKER_SLUG;
	}

	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode( "\n", array(
			"Connect to your server via SFTP or cPanel File Manager.",
			"Navigate to: {$file}",
			"Open the file in a text editor.",
			"Restore the previous wp-config.php contents or remove the Christopher Ross Shadow error-logging changes.",
			"Save the file and reload your site.",
		) );
	}

	private static function locate_wp_config(): ?string {
		$candidates = array( ABSPATH . 'wp-config.php', dirname( ABSPATH ) . '/wp-config.php' );
		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return null;
	}
}

Treatment_Error_Logging::boot();