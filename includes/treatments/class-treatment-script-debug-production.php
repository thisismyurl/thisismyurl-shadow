<?php
/**
 * Treatment: Disable SCRIPT_DEBUG in production
 *
 * Comments out a truthy SCRIPT_DEBUG define in wp-config.php so WordPress uses
 * production-minified core assets again.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.7056
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;
use ThisIsMyURL\Shadow\Admin\File_Write_Registry;

require_once __DIR__ . '/trait-file-write-helpers.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Treatment_Script_Debug_Production extends Treatment_Base {

	use File_Write_Helpers;

	/** @var string */
	protected static $slug = 'script-debug-production';

	private const MARKER_SLUG = 'script-debug-production';

	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	public static function get_finding_id(): string {
		return self::$slug;
	}

	public static function get_risk_level(): string {
		return 'high';
	}

	public static function apply(): array {
		if ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
			return array(
				'success' => true,
				'message' => __( 'SCRIPT_DEBUG is already absent or false. No change was needed.', 'thisismyurl-shadow' ),
			);
		}

		$config_path = self::locate_wp_config();
		if ( '' === $config_path ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php could not be located. Remove the SCRIPT_DEBUG define manually.', 'thisismyurl-shadow' ),
			);
		}

		if ( ! is_readable( $config_path ) || ! wp_is_writable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php is not readable/writable. Please remove the SCRIPT_DEBUG define manually.', 'thisismyurl-shadow' ),
			);
		}

		$content = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read wp-config.php.', 'thisismyurl-shadow' ),
			);
		}

		$pattern = '/^[^\S\r\n]*define\s*\(\s*[\'"]SCRIPT_DEBUG[\'"]\s*,\s*(true|1)\s*\)\s*;[^\r\n]*/mi';

		if ( ! preg_match( $pattern, $content ) ) {
			return array(
				'success' => false,
				'message' => __( 'A truthy SCRIPT_DEBUG define was not found in wp-config.php. It may be set elsewhere and cannot be modified automatically.', 'thisismyurl-shadow' ),
			);
		}

		if ( str_contains( $content, '// thisismyurl_shadow_MARKER_START: ' . self::MARKER_SLUG ) ) {
			return array(
				'success' => true,
				'message' => __( 'SCRIPT_DEBUG was already commented out by Shadow by Christopher Ross.', 'thisismyurl-shadow' ),
			);
		}

		$new_content = preg_replace_callback(
			$pattern,
			static function ( array $matches ): string {
				$original_line = $matches[0];
				return "\n// thisismyurl_shadow_MARKER_START: " . self::MARKER_SLUG . "\n"
					. '// ' . ltrim( $original_line ) . ' // commented out by Shadow by Christopher Ross - was forcing development asset builds' . "\n"
					. '// thisismyurl_shadow_MARKER_END: ' . self::MARKER_SLUG;
			},
			$content,
			1
		);

		if ( null === $new_content || $new_content === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not prepare wp-config.php updates for SCRIPT_DEBUG.', 'thisismyurl-shadow' ),
			);
		}

		$written = file_put_contents( $config_path, $new_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		if ( false === $written ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write the updated wp-config.php file.', 'thisismyurl-shadow' ),
			);
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $config_path, true );
		}

		return array(
			'success' => true,
			'message' => __( 'The truthy SCRIPT_DEBUG define was commented out in wp-config.php so WordPress can use production-minified assets again.', 'thisismyurl-shadow' ),
		);
	}

	public static function undo(): array {
		return self::remove_wp_config_block( self::get_target_file(), self::MARKER_SLUG );
	}

	public static function get_target_file(): string {
		$path = self::locate_wp_config();
		return '' !== $path ? $path : ABSPATH . 'wp-config.php';
	}

	public static function get_file_label(): string {
		return 'wp-config.php';
	}

	public static function get_proposed_change_summary(): string {
		return __( 'Comment out a truthy SCRIPT_DEBUG define in wp-config.php so WordPress stops loading development asset builds', 'thisismyurl-shadow' );
	}

	public static function get_proposed_snippet(): string {
		return "// thisismyurl_shadow_MARKER_START: script-debug-production\n"
			. "// define( 'SCRIPT_DEBUG', true ); // commented out by ThisIsMyURL\Shadow\n"
			. '// thisismyurl_shadow_MARKER_END: script-debug-production';
	}

	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode(
			"\n",
			array(
				'Connect to your server via SFTP or File Manager.',
				"Open {$file}.",
				'Find and delete the Shadow by Christopher Ross marker block for script-debug-production.',
				'If you intentionally need SCRIPT_DEBUG again, re-add the original define manually.',
				'Save the file and reload the site.',
			)
		);
	}

	private static function locate_wp_config(): string {
		$candidates = array( ABSPATH . 'wp-config.php', dirname( ABSPATH ) . '/wp-config.php' );
		foreach ( $candidates as $candidate ) {
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}
}

Treatment_Script_Debug_Production::boot();