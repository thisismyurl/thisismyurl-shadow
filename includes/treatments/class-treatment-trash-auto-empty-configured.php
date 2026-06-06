<?php
/**
 * Treatment: Configure WordPress trash auto-emptying
 *
 * Ensures `EMPTY_TRASH_DAYS` is set to 30 in wp-config.php. Existing defines
 * are updated in place; otherwise Christopher Ross Shadow inserts a marker-wrapped block.
 *
 * Undo restores the previous wp-config.php contents from backup.
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

class Treatment_Trash_Auto_Empty_Configured extends Treatment_Base {

	/** @var string */
	protected static $slug = 'trash-auto-empty-configured';

	private const BACKUP_OPTION = 'thisismyurl_shadow_trash_auto_empty_wp_config_backup';
	private const MARKER_SLUG   = 'trash-auto-empty-configured';
	private const DEFINE_LINE   = "define( 'EMPTY_TRASH_DAYS', 30 ); // Christopher Ross Shadow: keep trash cleanup enabled";

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

		if ( preg_match( "/define\s*\(\s*['\"]EMPTY_TRASH_DAYS['\"]\s*,\s*30\s*\)\s*;/i", $content ) ) {
			return array(
				'success' => true,
				'message' => __( 'EMPTY_TRASH_DAYS is already set to 30. No changes made.', 'thisismyurl-shadow' ),
			);
		}

		update_option( self::BACKUP_OPTION, base64_encode( $content ), false );

		$updated = preg_replace(
			"/^[ \t]*define\s*\(\s*['\"]EMPTY_TRASH_DAYS['\"]\s*,\s*.*?\)\s*;.*$/mi",
			self::DEFINE_LINE,
			$content,
			1,
			$replaced
		);

		if ( null === $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Could not prepare wp-config.php updates.', 'thisismyurl-shadow' ),
			);
		}

		if ( 0 === $replaced ) {
			$block   = "\n// thisismyurl_shadow_MARKER_START: " . self::MARKER_SLUG . "\n" . self::DEFINE_LINE . "\n// thisismyurl_shadow_MARKER_END: " . self::MARKER_SLUG . "\n";
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
			'message' => __( 'Automatic trash cleanup was configured in wp-config.php.', 'thisismyurl-shadow' ),
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

		$original = base64_decode( (string) $backup );
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
		return __( 'Set EMPTY_TRASH_DAYS to 30 in wp-config.php', 'thisismyurl-shadow' );
	}

	public static function get_proposed_snippet(): string {
		return "// thisismyurl_shadow_MARKER_START: " . self::MARKER_SLUG . "\n" . self::DEFINE_LINE . "\n// thisismyurl_shadow_MARKER_END: " . self::MARKER_SLUG;
	}

	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode( "\n", array(
			"Connect to your server via SFTP or cPanel File Manager.",
			"Navigate to: {$file}",
			"Open the file in a text editor.",
			"Either restore your previous wp-config.php from backup or remove the Christopher Ross Shadow-added EMPTY_TRASH_DAYS change.",
			"Save the file and reload your WordPress admin.",
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

Treatment_Trash_Auto_Empty_Configured::boot();