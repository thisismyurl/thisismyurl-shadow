<?php
/**
 * Treatment: Limit post revisions in wp-config.php
 *
 * WordPress saves an unlimited number of post revisions by default. On busy
 * editorial sites this inflates the database significantly. Setting
 * WP_POST_REVISIONS to 5 keeps the five most recent drafts — enough to
 * recover from accidental edits without the database bloat.
 *
 * Risk level: high — modifies wp-config.php directly. The original file is
 * backed up (base64-encoded) before writing. Undo removes the inserted block.
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
 * Inserts define('WP_POST_REVISIONS', 5) into wp-config.php.
 */
class Treatment_Post_Revision_Limit_Set extends Treatment_Base {

	/** @var string */
	protected static $slug = 'post-revision-limit-set';

	/** The define line inserted by this treatment. */
	private const DEFINE_LINE = "define( 'WP_POST_REVISIONS', 5 ); // Added by Shadow by Christopher Ross";

	/** Markers wrapping the inserted block. */
	private const MARKER_START = '// BEGIN This Is My URL Shadow WP_POST_REVISIONS';
	private const MARKER_END   = '// END This Is My URL Shadow WP_POST_REVISIONS';

	/** @return string */
	public static function get_risk_level(): string {
		return 'high';
	}

	/**
	 * Write WP_POST_REVISIONS = 5 into wp-config.php.
	 *
	 * @return array
	 */
	public static function apply(): array {
		// Already limited to a reasonable number — skip.
		if ( defined( 'WP_POST_REVISIONS' ) && WP_POST_REVISIONS !== true && (int) WP_POST_REVISIONS <= 10 ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: Current revision limit */
					__( 'WP_POST_REVISIONS is already set to %s. No changes made.', 'thisismyurl-shadow' ),
					esc_html( (string) WP_POST_REVISIONS )
				),
			);
		}

		$config_path = self::locate_wp_config();

		if ( null === $config_path || ! wp_is_writable( $config_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php could not be located or is not writable. Add define(\'WP_POST_REVISIONS\', 5); manually.', 'thisismyurl-shadow' ),
			);
		}

		$original = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $original ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read wp-config.php.', 'thisismyurl-shadow' ),
			);
		}

		if ( strpos( $original, self::MARKER_START ) !== false ) {
			return array(
				'success' => true,
				'message' => __( 'WP_POST_REVISIONS was already added by Shadow by Christopher Ross.', 'thisismyurl-shadow' ),
			);
		}

		// Back up the original file for undo().
		update_option( 'thisismyurl_shadow_wp_config_backup_post_revisions', base64_encode( $original ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$block    = PHP_EOL . self::MARKER_START . PHP_EOL
					. self::DEFINE_LINE . PHP_EOL
					. self::MARKER_END . PHP_EOL;
		$modified = preg_replace( '/^(<\?php\s*)/i', '$1' . $block, $original, 1 );

		if ( null === $modified || $modified === $original ) {
			return array(
				'success' => false,
				'message' => __( 'Could not insert WP_POST_REVISIONS into wp-config.php. Please add it manually.', 'thisismyurl-shadow' ),
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
			'message' => __( 'WP_POST_REVISIONS set to 5 in wp-config.php. Post revision history is now limited to the 5 most recent drafts.', 'thisismyurl-shadow' ),
			'details' => array( 'config_path' => $config_path ),
		);
	}

	/**
	 * Remove the This Is My URL Shadow WP_POST_REVISIONS block from wp-config.php.
	 *
	 * @return array
	 */
	public static function undo(): array {
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

		$pattern  = '/' . preg_quote( PHP_EOL . self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END . PHP_EOL, '/' ) . '/s';
		$modified = preg_replace( $pattern, '', $current );

		if ( null !== $modified && $modified !== $current ) {
			$result = file_put_contents( $config_path, $modified ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			if ( false !== $result ) {
				delete_option( 'thisismyurl_shadow_wp_config_backup_post_revisions' );
				return array(
					'success' => true,
					'message' => __( 'WP_POST_REVISIONS removed from wp-config.php. WordPress will store unlimited revisions again.', 'thisismyurl-shadow' ),
				);
			}
		}

		// Fallback to stored backup.
		$backup_encoded = get_option( 'thisismyurl_shadow_wp_config_backup_post_revisions' );

		if ( $backup_encoded ) {
			$backup = base64_decode( $backup_encoded ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false !== $backup ) {
				$result = file_put_contents( $config_path, $backup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false !== $result ) {
					delete_option( 'thisismyurl_shadow_wp_config_backup_post_revisions' );
					return array(
						'success' => true,
						'message' => __( 'wp-config.php restored from backup. WP_POST_REVISIONS removed.', 'thisismyurl-shadow' ),
					);
				}
			}
		}

		return array(
			'success' => false,
			'message' => __( 'Could not automatically remove WP_POST_REVISIONS from wp-config.php. Remove the line manually or restore from your site backup.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Locate wp-config.php (standard or moved up one directory level).
	 *
	 * @return string|null
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
