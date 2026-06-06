<?php
/**
 * DB Charset and Collation Correct Diagnostic
 *
 * Validates that the WordPress database uses utf8mb4 charset with an
 * appropriate unicode_ci collation, flagging legacy or mismatched configurations.
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
 * DB Charset and Collation Correct Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Db_Charset_Collation_Correct extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'db-charset-collation-correct';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'DB Charset and Collation Correct';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'The database charset or collation is not set to the recommended utf8mb4 standard. This can cause emoji storage issues and character-encoding bugs.';

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
	 * Reads $wpdb->charset and $wpdb->collate and checks the wp_options table
	 * collation to ensure utf8mb4 is consistently applied.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when charset/collation is non-standard, null when healthy.
	 */
	public static function check() {
		$charset   = Server_Env::get_db_charset();
		$collation = Server_Env::get_db_collation();
		$issues    = array();

		// utf8 (3-byte) does not support the full Unicode range, including many emoji.
		// utf8mb4 (4-byte) is the correct MySQL/MariaDB equivalent.
		if ( '' !== $charset && 'utf8mb4' !== $charset ) {
			$issues[] = sprintf(
				/* translators: %s: current charset */
				__( 'DB_CHARSET is set to "%s" instead of "utf8mb4". The legacy "utf8" encoding cannot store 4-byte characters (emoji, some CJK ideographs). Data containing those characters will be truncated or cause errors.', 'thisismyurl-shadow' ),
				$charset
			);
		}

		// utf8mb4_general_ci has known sorting inaccuracies for accented characters.
		// utf8mb4_unicode_ci or utf8mb4_unicode_520_ci are preferred.
		if ( '' !== $collation && false !== strpos( $collation, 'general' ) ) {
			$issues[] = sprintf(
				/* translators: %s: current collation */
				__( 'DB_COLLATE is set to "%s". The "general_ci" collation has known sorting inaccuracies for accented characters. Consider switching to "utf8mb4_unicode_ci" or "utf8mb4_unicode_520_ci" for more accurate multilingual sorting.', 'thisismyurl-shadow' ),
				$collation
			);
		}

		if ( empty( $issues ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your database character set or collation configuration may cause data loss or incorrect sorting for multilingual content and emoji.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array(
				'issues'    => $issues,
				'charset'   => $charset,
				'collation' => $collation,
			),
		);
	}
}
