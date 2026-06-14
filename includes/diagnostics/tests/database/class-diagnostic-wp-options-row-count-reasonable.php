<?php
/**
 * wp_options Row Count Reasonable Diagnostic
 *
 * Checks that the total number of rows in wp_options has not grown to an
 * unhealthy size. Bloated options tables are often caused by plugins that
 * write per-item records rather than using a single serialised option.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Wp_Options_Row_Count_Reasonable Class
 *
 * @since 0.6095
 */
class Diagnostic_Wp_Options_Row_Count_Reasonable extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'wp-options-row-count-reasonable';

    /** @var string */
    protected static $title = 'wp_options Table Not Bloated';

    /** @var string */
    protected static $description = 'Checks that the total row count in wp_options is under 2,000. An abnormally large options table is a sign of plugin data bloat.';

    /** @var string */
    protected static $family = 'database';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

    /**
     * Run the diagnostic check.
     *
     * Counts rows in wp_options. Returns null when the total is under 2,000.
     * Returns a low finding for 2,000–4,999, and a medium finding for 5,000 or
     * more, indicating significant options-table bloat.
     *
     * @since  0.6095
     * @return array|null Finding array when row count is excessive, null when healthy.
     */
    public static function check() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        if ( $row_count < 2000 ) {
            return null;
        }

        $severity     = $row_count >= 5000 ? 'medium' : 'low';
        $threat_level = $row_count >= 5000 ? 30 : 10;

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => sprintf(
                /* translators: %s: formatted row count */
                __( 'The wp_options table contains %s rows. A count above 2,000 is an early sign of plugin data bloat — some plugins write per-post or per-event records into wp_options instead of using custom tables. A healthy site typically has fewer than 1,000 rows.', 'thisismyurl-shadow' ),
                number_format_i18n( $row_count )
            ),
            'severity'     => $severity,
            'threat_level' => $threat_level,
            'details'      => array(
                'options_row_count' => $row_count,
            ),
        );
    }
}
