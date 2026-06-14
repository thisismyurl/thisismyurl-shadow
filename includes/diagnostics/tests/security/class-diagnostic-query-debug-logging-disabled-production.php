<?php
/**
 * Query Debug Logging Disabled In Production Diagnostic
 *
 * Checks whether SAVEQUERIES is enabled in production, which logs all
 * database queries in memory and may expose sensitive query data.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Query_Debug_Logging_Disabled_Production Class
 *
 * Checks the SAVEQUERIES constant via the Server_Env helper and flags its
 * use on production sites as a performance and information-disclosure risk.
 *
 * @since 0.6095
 */
class Diagnostic_Query_Debug_Logging_Disabled_Production extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'query-debug-logging-disabled-production';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Query Debug Logging Disabled In Production';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether SAVEQUERIES is enabled in production, which logs all database queries in memory and may expose sensitive query data or degrade performance.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the SAVEQUERIES constant via the Server_Env helper and returns a
	 * medium-severity finding when it is enabled on a production-like instance.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when SAVEQUERIES is active, null when healthy.
	 */
	public static function check() {
		if ( ! Server_Env::is_savequeries() ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'The SAVEQUERIES constant is set to true. This causes every database query to be recorded in memory on every request, increasing memory usage and exposing query data. SAVEQUERIES is a development tool and should not be active on a production site.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 45,
			'details'      => array(
				'savequeries' => true,
				'fix'         => __( 'Remove or set define( \'SAVEQUERIES\', false ); in wp-config.php.', 'thisismyurl-shadow' ),
			),
		);
	}
}
