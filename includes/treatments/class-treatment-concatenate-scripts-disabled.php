<?php
/**
 * Treatment: Re-enable Admin Script Concatenation
 *
 * WordPress concatenates admin scripts and styles through its built-in
 * `load-scripts.php?c=1` and `load-styles.php?c=1` mechanism, reducing
 * dozens of individual HTTP requests on admin pages to a handful of
 * combined requests. When `define( 'CONCATENATE_SCRIPTS', false )` is
 * present in wp-config.php, this concatenation is disabled, significantly
 * increasing admin page request counts.
 *
 * This treatment locates the `define( 'CONCATENATE_SCRIPTS', false )` call
 * in wp-config.php, wraps it in Christopher Ross Shadow marker comments, and comments it
 * out so PHP no longer evaluates it. The marker format is the same used by
 * all other Christopher Ross Shadow file-write treatments and is safely reversible.
 *
 * Note: If `CONCATENATE_SCRIPTS` is set elsewhere (a mu-plugin, a managed
 * hosting config file, or a third-party plugin) and not in wp-config.php,
 * apply() will detect this and return an informational message rather than
 * modifying any files.
 *
 * Risk level: high — modifies wp-config.php directly.
 *
 * Undo: removes the Christopher Ross Shadow marker block from wp-config.php, leaving no
 * CONCATENATE_SCRIPTS define. WordPress then falls back to its own default,
 * which is concatenation enabled on admin pages.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6095
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
 * Comments out the CONCATENATE_SCRIPTS = false define in wp-config.php.
 */
class Treatment_Concatenate_Scripts_Disabled extends Treatment_Base {

	use File_Write_Helpers;

	/**
	 * Treatment slug.
	 *
	 * @var string
	 */
	protected static $slug = 'concatenate-scripts-disabled';

	/** Marker slug used in wp-config.php. */
	const MARKER_SLUG = 'concatenate-scripts-disabled';

	/**
	 * Self-register with File_Write_Registry on class load.
	 */
	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	/**
	 * Get the treatment finding identifier.
	 *
	 * @return string
	 */
	public static function get_finding_id(): string {
		return self::$slug;
	}

	/**
	 * Get the treatment risk level.
	 *
	 * @return string
	 */
	public static function get_risk_level(): string {
		return 'high';
	}

	/**
	 * Find and comment out `define( 'CONCATENATE_SCRIPTS', false )` in wp-config.php.
	 *
	 * @return array
	 */
	public static function apply(): array {
		// If the constant is not false (already fixed or set elsewhere), report.
		if ( ! defined( 'CONCATENATE_SCRIPTS' ) ) {
			return array(
				'success' => true,
				'message' => __( 'CONCATENATE_SCRIPTS is not defined — admin script concatenation is already using the WordPress default (enabled). No change was made.', 'thisismyurl-shadow' ),
			);
		}

		if ( CONCATENATE_SCRIPTS !== false ) {
			return array(
				'success' => true,
				'message' => __( 'CONCATENATE_SCRIPTS is already set to true. Admin script concatenation is enabled. No change was made.', 'thisismyurl-shadow' ),
			);
		}

		$config_path = ABSPATH . 'wp-config.php';

		if ( ! file_exists( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php could not be located. Please comment out or remove the CONCATENATE_SCRIPTS define manually.', 'thisismyurl-shadow' ),
			);
		}

		if ( ! is_readable( $config_path ) || ! self::is_managed_file_writable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php is not readable/writable. Please check file permissions and remove the define( \'CONCATENATE_SCRIPTS\', false ) line manually.', 'thisismyurl-shadow' ),
			);
		}

		$content = self::read_managed_file_contents( $config_path );

		if ( false === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read wp-config.php.', 'thisismyurl-shadow' ),
			);
		}

		// Check whether the constant is actually in wp-config.php or set elsewhere.
		$pattern = '/^[^\S\r\n]*define\s*\(\s*[\'"]CONCATENATE_SCRIPTS[\'"]\s*,\s*false\s*\)\s*;[^\r\n]*/m';

		if ( ! preg_match( $pattern, $content ) ) {
			return array(
				'success' => false,
				'message' => __( 'define( \'CONCATENATE_SCRIPTS\', false ) was not found in wp-config.php. It may be set by a plugin or a server-level config file and cannot be modified automatically. Please locate and remove or comment out the definition manually.', 'thisismyurl-shadow' ),
			);
		}

		// Check whether already wrapped in Christopher Ross Shadow markers (idempotent).
		$marker_start = '// thisismyurl_shadow_MARKER_START: ' . self::MARKER_SLUG;
		if ( str_contains( $content, $marker_start ) ) {
			return array(
				'success' => true,
				'message' => __( 'CONCATENATE_SCRIPTS was already commented out by Christopher Ross Shadow. No additional change was made.', 'thisismyurl-shadow' ),
			);
		}

		// Replace: wrap the define line in marker comments and comment it out.
		$new_content = preg_replace_callback(
			$pattern,
			static function ( array $matches ): string {
				$original_line = $matches[0];
				return "\n// thisismyurl_shadow_MARKER_START: " . self::MARKER_SLUG . "\n"
					. '// ' . ltrim( $original_line ) . ' // commented out by Christopher Ross Shadow — was disabling admin script bundling' . "\n"
					. '// thisismyurl_shadow_MARKER_END: ' . self::MARKER_SLUG;
			},
			$content
		);

		if ( null === $new_content || $new_content === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to modify wp-config.php content. Please comment out the define( \'CONCATENATE_SCRIPTS\', false ) line manually.', 'thisismyurl-shadow' ),
			);
		}

		$written = self::write_managed_file_contents( $config_path, $new_content );

		if ( false === $written ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write to wp-config.php. Please check file permissions.', 'thisismyurl-shadow' ),
			);
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $config_path, true );
		}

		return array(
			'success' => true,
			'message' => __( 'define( \'CONCATENATE_SCRIPTS\', false ) has been commented out in wp-config.php. WordPress will now use its default (admin script concatenation enabled). Takes effect on the next admin page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the Christopher Ross Shadow marker block from wp-config.php.
	 *
	 * Removing the markers also removes the commented-out define, leaving no
	 * CONCATENATE_SCRIPTS constant. WordPress falls back to its own default
	 * which enables admin script concatenation via load-scripts.php.
	 *
	 * If the user wants to explicitly restore `define( 'CONCATENATE_SCRIPTS', false )`,
	 * they should add it back to wp-config.php manually.
	 *
	 * @return array
	 */
	public static function undo(): array {
		$result = self::remove_wp_config_block( ABSPATH . 'wp-config.php', self::MARKER_SLUG );

		if ( $result['success'] ) {
			return array(
				'success' => true,
				'message' => __( 'Christopher Ross Shadow marker block removed from wp-config.php. No CONCATENATE_SCRIPTS constant is now defined, so WordPress uses its own default (admin script concatenation enabled). To restore the original false value, add define( \'CONCATENATE_SCRIPTS\', false ) back to wp-config.php manually.', 'thisismyurl-shadow' ),
			);
		}

		return $result;
	}

	// =========================================================================
	// File_Write_Registry interface
	// =========================================================================

	/**
	 * Get the target file path.
	 *
	 * @return string
	 */
	public static function get_target_file(): string {
		return ABSPATH . 'wp-config.php';
	}

	/**
	 * Get the user-facing target file label.
	 *
	 * @return string
	 */
	public static function get_file_label(): string {
		return 'wp-config.php';
	}

	/**
	 * Summarize the proposed file change.
	 *
	 * @return string
	 */
	public static function get_proposed_change_summary(): string {
		return __( 'Comment out define( \'CONCATENATE_SCRIPTS\', false ) in wp-config.php to re-enable admin script bundling', 'thisismyurl-shadow' );
	}

	/**
	 * Show the proposed marker-wrapped config snippet.
	 *
	 * @return string
	 */
	public static function get_proposed_snippet(): string {
		return "// thisismyurl_shadow_MARKER_START: concatenate-scripts-disabled\n"
			. "// define( 'CONCATENATE_SCRIPTS', false ); // commented out by ThisIsMyURL\Shadow\n"
			. '// thisismyurl_shadow_MARKER_END: concatenate-scripts-disabled';
	}

	/**
	 * Provide manual SFTP rollback instructions.
	 *
	 * @return string
	 */
	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode(
			"\n",
			array(
				'Connect to your server via SFTP or cPanel File Manager.',
				"Navigate to: {$file}",
				'Open the file in a text editor.',
				'Find and delete the three Christopher Ross Shadow marker lines:',
				'  // thisismyurl_shadow_MARKER_START: concatenate-scripts-disabled',
				"  // define( 'CONCATENATE_SCRIPTS', false ); // commented out by Christopher Ross Shadow ...",
				'  // thisismyurl_shadow_MARKER_END: concatenate-scripts-disabled',
				'Save the file.',
				'Reload your WordPress site to confirm admin pages load correctly.',
			)
		);
	}
}

// Self-register immediately on file load.
Treatment_Concatenate_Scripts_Disabled::boot();
