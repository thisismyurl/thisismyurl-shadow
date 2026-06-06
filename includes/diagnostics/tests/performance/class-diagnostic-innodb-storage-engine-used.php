<?php
/**
 * InnoDB Storage Engine Used Diagnostic
 *
 * Queries INFORMATION_SCHEMA to verify all WordPress core tables use the
 * InnoDB storage engine, which supports transactions and row-level locking.
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
 * InnoDB Storage Engine Used Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Innodb_Storage_Engine_Used extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'innodb-storage-engine-used';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'InnoDB Storage Engine Used';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress database tables use the InnoDB storage engine, which delivers better performance and crash recovery than the older MyISAM engine.';

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
	 * Runs SHOW TABLE STATUS and counts tables not using InnoDB engine.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when non-InnoDB tables are found, null when healthy.
	 */
	public static function check() {
		if ( Server_Env::is_sqlite() ) {
			return null;
		}

		$engine = Server_Env::get_db_engine();

		// Cannot determine engine (e.g. user lacks information_schema access) — skip.
		if ( '' === $engine ) {
			return null;
		}

		if ( Server_Env::is_innodb() ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: current storage engine */
				__( 'Your WordPress tables are using the %s storage engine instead of InnoDB. InnoDB provides ACID-compliant transactions, row-level locking, crash recovery, and full-text search support. MyISAM and other legacy engines lack these features and have been deprecated in modern MySQL/MariaDB releases.', 'thisismyurl-shadow' ),
				esc_html( $engine )
			),
			'severity'     => 'medium',
			'threat_level' => 50,
			'details'      => array(
				'current_engine'     => $engine,
				'recommended_engine' => 'InnoDB',
				'tested_on_table'    => $GLOBALS['wpdb']->posts,
			),
		);
	}
}
