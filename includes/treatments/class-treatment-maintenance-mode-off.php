<?php
/**
 * Treatment: Disable maintenance mode and coming-soon flags
 *
 * Clears the common maintenance-mode sources checked by the matching
 * diagnostic: the WordPress core `.maintenance` file and a small set of
 * popular plugin options.
 *
 * Undo restores the original option payloads and recreates `.maintenance`
 * when Shadow by Christopher Ross removed it.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.7056
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_WP_Settings_Helper as WP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable common maintenance-mode sources and remove the core marker file.
 */
class Treatment_Maintenance_Mode_Off extends Treatment_Base {

	/**
	 * Treatment slug.
	 *
	 * @var string
	 */
	protected static $slug = 'maintenance-mode-off';

	private const BACKUP_OPTION = 'thisismyurl_shadow_maintenance_mode_off_backup';

	/**
	 * Get the treatment risk level.
	 *
	 * @return string
	 */
	public static function get_risk_level(): string {
		return 'moderate';
	}

	/**
	 * Disable maintenance-mode flags and remove the core maintenance file.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function apply(): array {
		$maintenance_file = ABSPATH . '.maintenance';

		if ( file_exists( $maintenance_file ) && is_link( $maintenance_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'The .maintenance file is a symlink and cannot be modified automatically. Remove it manually after validating its target.', 'thisismyurl-shadow' ),
			);
		}

		if ( file_exists( $maintenance_file ) && ! is_writable( $maintenance_file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Maintenance marker file must be checked directly before a reviewed delete.
			return array(
				'success' => false,
				'message' => __( 'The .maintenance file is not writable. Disable maintenance mode manually or adjust file permissions first.', 'thisismyurl-shadow' ),
			);
		}

		$backup = array(
			'maintenance_file' => array(
				'exists'   => file_exists( $maintenance_file ),
				'contents' => file_exists( $maintenance_file ) && is_readable( $maintenance_file )
					? base64_encode( (string) file_get_contents( $maintenance_file ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Stores a reversible snapshot of the local .maintenance file for undo.
					: '',
			),
			'options'          => array(
				'seedprod_page_settings'        => self::capture_option( 'seedprod_page_settings' ),
				'maintenance_mode'              => self::capture_option( 'maintenance_mode' ),
				'wpmm_settings'                 => self::capture_option( 'wpmm_settings' ),
				'colorlib_coming_soon_settings' => self::capture_option( 'colorlib_coming_soon_settings' ),
				'wp_maintenance'                => self::capture_option( 'wp_maintenance' ),
			),
		);

		static::save_backup_value( self::BACKUP_OPTION, $backup );

		$changes = array();

		$seedprod = get_option( 'seedprod_page_settings', null );
		if ( is_array( $seedprod ) && ! empty( $seedprod['enable_maintenance_mode'] ) ) {
			$seedprod['enable_maintenance_mode'] = 0;
			update_option( 'seedprod_page_settings', $seedprod );
			$changes[] = 'SeedProd maintenance mode disabled';
		}

		if ( get_option( 'maintenance_mode', false ) ) {
			update_option( 'maintenance_mode', false );
			$changes[] = 'maintenance_mode option cleared';
		}

		$wpmm = get_option( 'wpmm_settings', null );
		if ( is_array( $wpmm ) && ! empty( $wpmm['general']['status'] ) ) {
			$wpmm['general']['status'] = 0;
			update_option( 'wpmm_settings', $wpmm );
			$changes[] = 'WP Maintenance Mode disabled';
		}

		$colorlib = get_option( 'colorlib_coming_soon_settings', null );
		if ( is_array( $colorlib ) && ! empty( $colorlib['status'] ) && '1' === (string) $colorlib['status'] ) {
			$colorlib['status'] = '0';
			update_option( 'colorlib_coming_soon_settings', $colorlib );
			$changes[] = 'Colorlib coming soon mode disabled';
		}

		$webfactory = get_option( 'wp_maintenance', null );
		if ( is_array( $webfactory ) && ! empty( $webfactory['state'] ) ) {
			$webfactory['state'] = 0;
			update_option( 'wp_maintenance', $webfactory );
			$changes[] = 'WP Maintenance state disabled';
		}

		if ( file_exists( $maintenance_file ) ) {
			wp_delete_file( $maintenance_file );

			if ( file_exists( $maintenance_file ) ) {
				return array(
					'success' => false,
					'message' => __( 'Maintenance settings were updated, but the .maintenance file could not be removed. Delete it manually.', 'thisismyurl-shadow' ),
				);
			}
			$changes[] = '.maintenance file removed';
		}

		if ( empty( $changes ) ) {
			delete_option( self::BACKUP_OPTION );
			WP_Settings::clear_cache();
			return array(
				'success' => true,
				'message' => __( 'Maintenance mode was already off. No changes made.', 'thisismyurl-shadow' ),
			);
		}

		WP_Settings::clear_cache();

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: comma-separated list of maintenance sources disabled */
				__( 'Maintenance mode disabled: %s.', 'thisismyurl-shadow' ),
				implode( ', ', $changes )
			),
		);
	}

	/**
	 * Restore maintenance-mode settings from the saved snapshot.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function undo(): array {
		$backup = get_option( self::BACKUP_OPTION );
		if ( ! is_array( $backup ) ) {
			return array(
				'success' => false,
				'message' => __( 'No maintenance-mode backup was stored.', 'thisismyurl-shadow' ),
			);
		}

		if ( isset( $backup['options'] ) && is_array( $backup['options'] ) ) {
			foreach ( $backup['options'] as $option_name => $snapshot ) {
				self::restore_captured_option( (string) $option_name, $snapshot );
			}
		}

		$maintenance_file = ABSPATH . '.maintenance';
		if ( file_exists( $maintenance_file ) && is_link( $maintenance_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'The .maintenance file is a symlink and cannot be restored automatically. Validate its target and restore it manually if needed.', 'thisismyurl-shadow' ),
			);
		}

		$file_backup = isset( $backup['maintenance_file'] ) && is_array( $backup['maintenance_file'] )
			? $backup['maintenance_file']
			: array();

		if ( ! empty( $file_backup['exists'] ) ) {
			$contents = isset( $file_backup['contents'] ) ? base64_decode( (string) $file_backup['contents'] ) : false; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Restores the local .maintenance snapshot captured during apply().
			if ( false !== $contents ) {
				file_put_contents( $maintenance_file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions, PluginCheck.CodeAnalysis.WriteFile.ABSPATHDetected -- Restores the exact local .maintenance contents captured during apply(); the .maintenance file must live in ABSPATH, that is where WordPress core reads it.
			}
		} elseif ( file_exists( $maintenance_file ) ) {
			wp_delete_file( $maintenance_file );
		}

		delete_option( self::BACKUP_OPTION );
		WP_Settings::clear_cache();

		return array(
			'success' => true,
			'message' => __( 'Maintenance-mode settings restored from backup.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Capture whether an option exists and, if so, its current value.
	 *
	 * @param string $option_name Option name to snapshot.
	 * @return array{exists:bool, value:mixed}
	 */
	private static function capture_option( string $option_name ): array {
		$sentinel = '__thisismyurl_shadow_option_missing__';
		$value    = get_option( $option_name, $sentinel );
		return array(
			'exists' => $sentinel !== $value,
			'value'  => $sentinel !== $value ? $value : null,
		);
	}

	/**
	 * Restore a previously captured option snapshot.
	 *
	 * @param string $option_name Option name to restore.
	 * @param mixed  $snapshot    Captured option snapshot.
	 * @return void
	 */
	private static function restore_captured_option( string $option_name, $snapshot ): void {
		if ( ! is_array( $snapshot ) ) {
			return;
		}

		if ( ! empty( $snapshot['exists'] ) ) {
			update_option( $option_name, $snapshot['value'] );
			return;
		}

		delete_option( $option_name );
	}
}
