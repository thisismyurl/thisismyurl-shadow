<?php
/**
 * Database Version Supported Diagnostic
 *
 * Verifies that the MySQL/MariaDB version meets WordPress minimum requirements
 * and is within a supported, actively maintained release series.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Server_Environment_Helper as Server_Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Version Supported Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Database_Version_Supported extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'database-version-supported';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Database Version Supported';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'The database server version is outdated or unsupported. Running an end-of-life database engine risks security vulnerabilities and incompatibility.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the database server version via Server_Env and compares it against
	 * minimum supported ranges for MySQL and MariaDB.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when version is unsupported, null when healthy.
	 */
	public static function check() {
		$raw_version = Server_Env::get_db_version();

		if ( '' === $raw_version ) {
			return null; // Cannot determine version.
		}

		// Extract the leading numeric portion (e.g. '8.0.32' from '8.0.32' or '10.6.12-MariaDB').
		preg_match( '/^([\d.]+)/', $raw_version, $matches );
		$clean_version = $matches[1] ?? $raw_version;

		$is_mariadb = Server_Env::is_mariadb();

		if ( $is_mariadb ) {
			// MariaDB 10.4+ is the current minimum for full JSON, InnoDB, and security support.
			$min_version = '10.4.0';
			$recommended = '10.6.0';
		} else {
			// MySQL 5.7 EOL January 2024; MySQL 8.0 is current.
			$min_version = '5.7.0';
			$recommended = '8.0.0';
		}

		if ( version_compare( $clean_version, $recommended, '>=' ) ) {
			return null;
		}

		$below_minimum = version_compare( $clean_version, $min_version, '<' );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: db type, 2: current version, 3: recommended version */
				__( 'Your %1$s version is %2$s. The recommended minimum is %3$s. Older database versions miss performance improvements, bug fixes, and security patches. Contact your hosting provider to upgrade.', 'thisismyurl-shadow' ),
				$is_mariadb ? 'MariaDB' : 'MySQL',
				$clean_version,
				$recommended
			),
			'severity'     => $below_minimum ? 'high' : 'medium',
			'threat_level' => $below_minimum ? 65 : 35,
			'details'      => array(
				'db_type'          => $is_mariadb ? 'MariaDB' : 'MySQL',
				'current_version'  => $clean_version,
				'raw_version'      => $raw_version,
				'recommended'      => $recommended,
				'minimum'          => $min_version,
			),
		);
	}
}
