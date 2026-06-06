<?php
/**
 * Trash Auto Empty Configured Diagnostic
 *
 * Checks whether WordPress automatically empties the trash on a regular
 * schedule. When EMPTY_TRASH_DAYS is 0, deleted posts and attachments
 * accumulate in the database indefinitely, bloating the wp_posts table,
 * slowing queries, and inflating backup sizes.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Trash_Auto_Empty_Configured Class
 *
 * Reads the EMPTY_TRASH_DAYS constant and flags when auto-empty is disabled
 * (value 0) or when the retention period is unusually long (> 90 days).
 *
 * @since 0.6095
 */
class Diagnostic_Trash_Auto_Empty_Configured extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'trash-auto-empty-configured';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Trash Auto Empty Configured';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is set to automatically empty the trash. When EMPTY_TRASH_DAYS is 0, deleted posts and attachments accumulate in the database indefinitely, bloating the wp_posts table.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Severity of the finding.
	 *
	 * @var string
	 */
	protected static $severity = 'low';

	/**
	 * Estimated minutes to resolve.
	 *
	 * @var int
	 */
	protected static $time_to_fix_minutes = 10;

	/**
	 * Business impact statement.
	 *
	 * @var string
	 */
	protected static $impact = 'A trash that is never emptied silently grows the database, slowing queries and inflating backup sizes without any benefit to the site owner.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the EMPTY_TRASH_DAYS constant (WordPress default is 30). Returns null
	 * when the value is between 1 and 90 days inclusive. Returns a low-severity
	 * finding when auto-empty is fully disabled (0) or when retention exceeds 90
	 * days, including the actual configured value in the details.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when trash settings are problematic, null when healthy.
	 */
	public static function check() {
		$days = defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : 30;

		if ( $days >= 1 && $days <= 90 ) {
			return null;
		}

		if ( 0 === $days ) {
			$description = __( 'Automatic trash emptying is disabled on this site (EMPTY_TRASH_DAYS is set to 0). Deleted posts and media attachments accumulate in the database indefinitely, bloating the wp_posts table, slowing queries, and inflating backup sizes. Add define( \'EMPTY_TRASH_DAYS\', 30 ) to wp-config.php to enable scheduled cleanup.', 'thisismyurl-shadow' );
		} else {
			$description = sprintf(
				/* translators: %d: trash retention days */
				__( 'Trash items are retained for %d days before being automatically deleted. This unusually long retention period allows significant database bloat to accumulate before cleanup occurs. A value between 7 and 30 days is recommended for most sites.', 'thisismyurl-shadow' ),
				$days
			);
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => $description,
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'empty_trash_days' => $days,
			),
		);
	}
}
