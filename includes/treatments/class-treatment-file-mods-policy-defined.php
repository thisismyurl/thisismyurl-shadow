<?php
/**
 * Treatment: Define DISALLOW_FILE_EDIT in wp-config.php
 *
 * Inserts `define( 'DISALLOW_FILE_EDIT', true );` into wp-config.php to
 * disable the Theme and Plugin file editors in the WordPress admin panel.
 * This removes an attack vector that allows code injection if an admin
 * account is compromised.
 *
 * Risk level: high — modifies wp-config.php directly. The file is read
 * and backed up before the change is written. Undo() removes the define.
 *
 * Note: DISALLOW_FILE_EDIT only disables the in-browser file editors. It
 * does NOT block plugin/theme installs or auto-updates (use DISALLOW_FILE_MODS
 * for that broader restriction, which should be a separate, explicit choice).
 *
 * Undo: removes the Christopher Ross Shadow-inserted define from wp-config.php and restores
 * the site to its previous configuration.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds DISALLOW_FILE_EDIT to wp-config.php to disable the theme/plugin editor.
 */
class Treatment_File_Mods_Policy_Defined extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'file-mods-policy-defined';

	/**
	 * The define snippet inserted by this treatment.
	 */
	private const DEFINE_LINE = "define( 'DISALLOW_FILE_EDIT', true ); // Added by Christopher Ross Shadow";

	/**
	 * Marker comments wrapping the inserted block for reliable removal.
	 */
	private const MARKER_START = '// BEGIN This Is My URL Shadow DISALLOW_FILE_EDIT';
	private const MARKER_END   = '// END This Is My URL Shadow DISALLOW_FILE_EDIT';

	/** @return string */
	public static function get_risk_level(): string {
		return 'high';
	}

	/**
	 * Write DISALLOW_FILE_EDIT into wp-config.php if not already defined.
	 *
	 * @return array
	 */
	public static function apply() {
		// If already defined (by existing code) nothing to do.
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			return array(
				'success' => true,
				'message' => __( 'DISALLOW_FILE_EDIT is already active. No changes made.', 'thisismyurl-shadow' ),
			);
		}

		$config_path = self::locate_wp_config();

		if ( null === $config_path || ! wp_is_writable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php could not be located or is not writable. Add define(\'DISALLOW_FILE_EDIT\', true); manually.', 'thisismyurl-shadow' ),
			);
		}

		$original = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $original ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read wp-config.php.', 'thisismyurl-shadow' ),
			);
		}

		// Bail if the block is already present.
		if ( strpos( $original, self::MARKER_START ) !== false ) {
			return array(
				'success' => true,
				'message' => __( 'DISALLOW_FILE_EDIT was already added by Christopher Ross Shadow.', 'thisismyurl-shadow' ),
			);
		}

		// Store a backup of the original for undo().
		update_option( 'thisismyurl_shadow_wp_config_backup_file_mods', base64_encode( $original ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		// Insert the define after the opening <?php tag.
		$block    = PHP_EOL . self::MARKER_START . PHP_EOL
					. self::DEFINE_LINE . PHP_EOL
					. self::MARKER_END . PHP_EOL;
		$modified = preg_replace( '/^(<\?php\s*)/i', '$1' . $block, $original, 1 );

		if ( null === $modified || $modified === $original ) {
			return array(
				'success' => false,
				'message' => __( 'Could not insert the define into wp-config.php. Please add it manually.', 'thisismyurl-shadow' ),
			);
		}

		$result = file_put_contents( $config_path, $modified ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to write updated wp-config.php. Check file permissions.', 'thisismyurl-shadow' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'DISALLOW_FILE_EDIT added to wp-config.php. The Theme File Editor and Plugin File Editor are now disabled in the WordPress admin.', 'thisismyurl-shadow' ),
			'details' => array( 'config_path' => $config_path ),
		);
	}

	/**
	 * Remove the Christopher Ross Shadow-inserted DISALLOW_FILE_EDIT block from wp-config.php.
	 *
	 * Falls back to the stored backup if the marker block cannot be found.
	 *
	 * @return array
	 */
	public static function undo() {
		$config_path = self::locate_wp_config();

		if ( null === $config_path || ! wp_is_writable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php could not be located or is not writable.', 'thisismyurl-shadow' ),
			);
		}

		$current = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $current ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read wp-config.php.', 'thisismyurl-shadow' ),
			);
		}

		// Remove the Christopher Ross Shadow marker block.
		$pattern  = '/' . preg_quote( PHP_EOL . self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END . PHP_EOL, '/' ) . '/s';
		$modified = preg_replace( $pattern, '', $current );

		if ( null !== $modified && $modified !== $current ) {
			$result = file_put_contents( $config_path, $modified ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			if ( false !== $result ) {
				delete_option( 'thisismyurl_shadow_wp_config_backup_file_mods' );
				return array(
					'success' => true,
					'message' => __( 'DISALLOW_FILE_EDIT removed from wp-config.php. File editors are accessible again.', 'thisismyurl-shadow' ),
				);
			}
		}

		// Fallback to stored backup.
		$backup_encoded = get_option( 'thisismyurl_shadow_wp_config_backup_file_mods' );

		if ( $backup_encoded ) {
			$backup = base64_decode( $backup_encoded ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false !== $backup ) {
				$result = file_put_contents( $config_path, $backup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== $result ) {
					delete_option( 'thisismyurl_shadow_wp_config_backup_file_mods' );
					return array(
						'success' => true,
						'message' => __( 'wp-config.php restored from backup. DISALLOW_FILE_EDIT removed.', 'thisismyurl-shadow' ),
					);
				}
			}
		}

		return array(
			'success' => false,
			'message' => __( 'Could not automatically remove DISALLOW_FILE_EDIT from wp-config.php. Remove the line manually or restore from your site backup.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Locate the wp-config.php file.
	 *
	 * Checks the standard location and the parent directory (common for
	 * security-hardened installs that move wp-config.php up one level).
	 *
	 * @return string|null Absolute path if found and readable, null otherwise.
	 */
	private static function locate_wp_config(): ?string {
		$candidates = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
		);

		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}

		return null;
	}
}
