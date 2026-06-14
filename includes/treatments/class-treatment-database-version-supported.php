<?php
/**
 * Treatment: Database Version Supported
 *
 * Provides guidance for upgrading the MySQL or MariaDB database server.
 * Database engine upgrades must be performed at the hosting or server level;
 * a WordPress plugin cannot upgrade the database server software.
 *
 * Risk level: n/a (guidance only)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Returns hosting-level guidance for upgrading the database server version.
 */
class Treatment_Database_Version_Supported extends Treatment_Base {

	/** @var string */
	protected static $slug = 'database-version-supported';

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	public static function get_finding_id(): string {
		return self::$slug;
	}

	public static function get_risk_level(): string {
		return 'none';
	}

	/**
	 * Return database server upgrade guidance.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function apply(): array {
		global $wpdb;

		$db_version = $wpdb->db_version();
		$server     = $wpdb->get_var( 'SELECT VERSION()' ) ?? $db_version;

		return [
			'success' => false,
			'message' => sprintf(
				/* translators: %s: current database version string */
				__(
					"Your current database version is: %s

WordPress recommends MySQL 8.0+ or MariaDB 10.6+.
Database server upgrades must be performed at the hosting or server level.

IMPORTANT — BACK UP FIRST:
  Always create a full database backup before any upgrade.
  Use: mysqldump -u USER -p DATABASE > backup_$(date +%%Y%%m%%d).sql
  Or use the 'Backup' tool in cPanel / phpMyAdmin.

OPTION 1 — cPanel / Shared Hosting:
  1. Check if your host offers MySQL 8.0 or MariaDB 10.6 under 'MySQL Databases'
     or 'Software' in cPanel.
  2. Most hosts upgrade databases cluster-wide — open a support ticket:
     'Please upgrade my database to MySQL 8.0 or MariaDB 10.6.'
  3. Ask about maintenance windows and test your site after the upgrade.

OPTION 2 — VPS/Dedicated (Ubuntu/Debian — MariaDB):
  sudo apt-get install mariadb-server
  sudo mysql_upgrade -u root -p
  sudo service mariadb restart

OPTION 3 — VPS/Dedicated (MySQL 8):
  Follow the official MySQL upgrade guide:
  https://dev.mysql.com/doc/refman/8.0/en/upgrading.html

VERIFICATION:
  mysql --version   (or mariadb --version)

Re-run the Shadow by Christopher Ross scan after upgrading.",
					'thisismyurl-shadow'
				),
				esc_html( $server )
			),
		];
	}

	/**
	 * No state to undo (guidance only).
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function undo(): array {
		return [
			'success' => true,
			'message' => __( 'This is a guidance-only treatment — no changes were made by Shadow by Christopher Ross.', 'thisismyurl-shadow' ),
		];
	}
}
