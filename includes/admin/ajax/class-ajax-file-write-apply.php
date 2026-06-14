<?php
/**
 * File Write Apply AJAX Handler
 *
 * Applies a file-write treatment after the admin has:
 *   1. Reviewed the proposed change
 *   2. Created a backup (recommended — enforced server-side warning, not block)
 *   3. Read and acknowledged the SFTP recovery instructions
 *
 * The handler refuses to proceed unless the `acknowledged` flag is present in
 * the POST payload — this flag is only set by the JS after the admin checks
 * the "I have read these instructions" checkbox in the SFTP modal.
 *
 * It also honours per-file and global trust preference updates sent alongside
 * the apply request.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Admin\Ajax
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Admin\Ajax;

use ThisIsMyURL\Shadow\Core\AJAX_Handler_Base;
use ThisIsMyURL\Shadow\Admin\File_Write_Registry;
use ThisIsMyURL\Shadow\Admin\File_Write_Trust;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies a file-write treatment with acknowledgment gate.
 */
class Ajax_File_Write_Apply extends AJAX_Handler_Base {

	/**
	 * Register WordPress AJAX action.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_thisismyurl_shadow_file_write_apply', [ __CLASS__, 'handle' ] );
	}

	/**
	 * Handle apply request.
	 *
	 * POST params:
	 *   nonce        (string)  thisismyurl_shadow_file_write_apply nonce
	 *   finding_id   (string)  treatment finding ID
	 *   acknowledged (bool)    true = admin checked the SFTP acknowledgment checkbox
	 *   trust_file   (bool)    true = trust this specific file in future
	 *   trust_all    (bool)    true = skip warnings for all files globally
	 *
	 * @return void
	 */
	public static function handle(): void {
		self::verify_request( 'thisismyurl_shadow_file_write_apply', 'manage_options', 'nonce' );

		$finding_id  = self::get_post_param( 'finding_id', 'text', '', true );
		$acknowledged = self::get_post_param( 'acknowledged', 'bool', false );
		$trust_file  = self::get_post_param( 'trust_file', 'bool', false );
		$trust_all   = self::get_post_param( 'trust_all', 'bool', false );

		// Hard gate: acknowledgment is required.
		if ( ! $acknowledged ) {
			self::send_error(
				__( 'You must read and acknowledge the SFTP recovery instructions before applying a file-write change.', 'thisismyurl-shadow' )
			);
		}

		$info = self::get_treatment_info( $finding_id );
		if ( ! $info ) {
			self::send_error( __( 'Unknown treatment.', 'thisismyurl-shadow' ) );
		}

		$class     = $info['class'];
		$file_path = self::assert_allowed_managed_file_path( (string) $info['target_file'] );

		if ( ! function_exists( 'get_filesystem_method' ) || ! function_exists( 'wp_is_writable' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$filesystem_method = (string) get_filesystem_method( array(), $file_path );
		if ( 'direct' !== $filesystem_method ) {
			self::send_error(
				__( 'Shadow by Christopher Ross beta only applies file changes when WordPress has direct filesystem access. This file needs to be updated manually with your host file manager or SFTP workflow.', 'thisismyurl-shadow' )
			);
		}

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) || ! wp_is_writable( $file_path ) ) {
			self::send_error(
				__( 'This file is no longer available for safe in-dashboard updates. Refresh the review page and use the manual instructions if the file remains locked.', 'thisismyurl-shadow' )
			);
		}

		// Warn (but don't block) if no backup exists.
		$backup_key  = 'thisismyurl_shadow_file_backup_' . md5( $file_path );
		$backup_data = get_option( $backup_key, null );
		$has_backup  = is_array( $backup_data ) && ! empty( $backup_data['content'] );

		// Execute the treatment.
		$result = $class::execute( false );

		if ( empty( $result['success'] ) ) {
			self::send_error(
				$result['message'] ?? __( 'The fix could not be applied. Please check file permissions.', 'thisismyurl-shadow' ),
				[ 'has_backup' => $has_backup ]
			);
		}

		// Persist trust preferences.
		if ( $trust_all ) {
			File_Write_Trust::trust_all();
		} elseif ( $trust_file ) {
			File_Write_Trust::trust_file( $file_path );
		}

		\ThisIsMyURL\Shadow\Core\Activity_Logger::log(
			'file_write_applied',
			/* translators: %s: finding ID */
			sprintf( __( 'File-write fix applied: %s', 'thisismyurl-shadow' ), $finding_id ),
			'security',
			[
				'finding_id'   => $finding_id,
				'file_path'    => $file_path,
				'trust_file'   => $trust_file,
				'trust_all'    => $trust_all,
				'had_backup'   => $has_backup,
			]
		);

		self::send_success( [
			'message'    => $result['message'] ?? __( 'Fix applied successfully.', 'thisismyurl-shadow' ),
			'finding_id' => $finding_id,
		] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve treatment info from registry by finding ID.
	 *
	 * @param string $finding_id
	 * @return array|null
	 */
	private static function get_treatment_info( string $finding_id ): ?array {
		foreach ( File_Write_Registry::get_all() as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'get_finding_id' ) ) {
				if ( $class::get_finding_id() === $finding_id ) {
					return File_Write_Registry::get_treatment_info( $class );
				}
			}
		}
		return null;
	}
}
