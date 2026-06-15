<?php
/**
 * Backups Automated Diagnostic
 *
 * Automated backups are the last line of defence against data loss from
 * plugin conflicts, hacks, botched updates, or accidental deletion. Many
 * site owners rely on hosting-level backups, but without an independent
 * on-site backup scheduled there is no guaranteed recovery path.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Diagnostics
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Backups_Automated Class
 *
 * @since 0.6095
 */
class Diagnostic_Backups_Automated extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'backups-automated';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Backups Automated';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks that an automated backup plugin is active and, where detectable, that a schedule has been configured to protect site data from loss.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'monitoring';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Plugin file paths known to provide scheduled backup functionality.
	 */
	private const BACKUP_PLUGINS = array(
		'updraftplus/updraftplus.php'                       => 'UpdraftPlus',
		'backwpup/backwpup.php'                             => 'BackWPup',
		'wpvivid-backuprestore/wpvivid-backuprestore.php'   => 'WPvivid Backup',
		'duplicator/duplicator.php'                         => 'Duplicator',
		'duplicator-pro/duplicator-pro.php'                 => 'Duplicator Pro',
		'vaultpress/vaultpress.php'                         => 'Jetpack VaultPress Backup',
		'jetpack-backup/jetpack-backup.php'                 => 'Jetpack Backup',
		'jetpack/jetpack.php'                               => 'Jetpack (includes backup)',
		'wp-time-capsule/wp-time-capsule.php'               => 'WP Time Capsule',
		'blogvault-real-time-backup/blogvault.php'          => 'BlogVault',
		'xcloner-backup-and-restore/xcloner.php'            => 'XCloner',
		'backup-backup/backup-backup.php'                   => 'Backup Migration',
		'all-in-one-wp-migration/all-in-one-wp-migration.php' => 'All-in-One WP Migration',
		'wp-db-backup/wp-db-backup.php'                     => 'WP DB Backup',
	);

	/**
	 * WP-Cron hook names registered by backup plugins.
	 * Used to confirm a schedule is actually active.
	 */
	private const BACKUP_CRON_HOOKS = array(
		'updraft_backup',
		'updraft_backup_database',
		'backwpup_cron',
		'wpvivid_backup_cron',
		'jetpack_backup_cron',
	);

	/**
	 * Run the diagnostic check.
	 *
	 * Checks for active backup plugins and, for UpdraftPlus specifically,
	 * also verifies that a schedule is configured (not left at "Manual").
	 * Returns null if a backup plugin is active with a schedule, or if any
	 * backup cron hook is registered.
	 *
	 * @since  0.6095
	 * @return array|null Finding array if issue exists, null if healthy.
	 */
	public static function check() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_backup_plugin = '';

		foreach ( self::BACKUP_PLUGINS as $plugin_file => $label ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$active_backup_plugin = $label;
				break;
			}
		}

		if ( '' === $active_backup_plugin ) {
			// No backup plugin active — check for known backup cron hooks as a fallback.
			foreach ( self::BACKUP_CRON_HOOKS as $hook ) {
				if ( wp_get_scheduled_event( $hook ) ) {
					return null;
				}
			}

			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'No automated backup plugin was detected. If the site is compromised, hit by a bad update, or experiences data loss, there is no recovery point available through WordPress.', 'thisismyurl-shadow' ),
				'severity'     => 'high',
				'threat_level' => 70,
				'details'      => array(
					'fix' => __( 'Install UpdraftPlus (free) and configure daily or weekly backups to an off-site location such as Google Drive, Amazon S3, or Dropbox. Verify that at least one test restore has been completed successfully.', 'thisismyurl-shadow' ),
				),
			);
		}

		// UpdraftPlus-specific: check whether a file backup schedule is set.
		if ( is_plugin_active( 'updraftplus/updraftplus.php' ) ) {
			$updraft_interval = get_option( 'updraftplus', array() );
			$interval         = $updraft_interval['updraft_interval'] ?? 'manual';

			if ( 'manual' === $interval ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => __( 'UpdraftPlus is installed but the backup schedule is set to "Manual". Backups will only run when triggered by hand, meaning data loss can occur between manual runs.', 'thisismyurl-shadow' ),
					'severity'     => 'medium',
					'threat_level' => 45,
					'details'      => array(
						'plugin' => 'UpdraftPlus',
						'fix'    => __( 'In Settings &rsaquo; UpdraftPlus Backups, change "Files backup schedule" and "Database backup schedule" to Daily or Weekly, and configure a remote storage destination such as Google Drive or Dropbox.', 'thisismyurl-shadow' ),
					),
				);
			}
		}

		return null;
	}
}
