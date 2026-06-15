<?php
/**
 * User Meta Bloat Diagnostic
 *
 * Checks that the wp_usermeta table has not grown to a size disproportionate
 * to the number of registered users. A high ratio often indicates abandoned
 * plugin metadata that was never cleaned up.
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
 * Diagnostic_User_Meta_Bloat_Detected Class
 *
 * @since 0.6095
 */
class Diagnostic_User_Meta_Bloat_Detected extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'user-meta-bloat-detected';

    /** @var string */
    protected static $title = 'wp_usermeta Not Excessively Bloated';

    /** @var string */
    protected static $description = 'Checks that the ratio of wp_usermeta rows to registered users is under 200:1. A higher ratio typically signals abandoned plugin data left behind in the database.';

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
     * Counts wp_usermeta rows and registered users, then computes the ratio.
     * Returns null when the ratio is under 200:1 or there are too few users.
     * Returns a medium or low severity finding based on the severity of the
     * ratio.
     *
     * @since  0.6095
     * @return array|null Finding array when user meta ratio is too high, null when healthy.
     */
    public static function check() {
        global $wpdb;

        $user_count_data = count_users();
        $user_count      = isset( $user_count_data['total_users'] ) ? (int) $user_count_data['total_users'] : 0;

        if ( $user_count < 2 ) {
            return null; // Single-user sites are expected to have proportionally more meta.
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $meta_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta}" );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        $ratio = $user_count > 0 ? (int) round( $meta_count / $user_count ) : 0;

        if ( $ratio < 200 ) {
            return null;
        }

        $severity     = $ratio >= 1000 ? 'medium' : 'low';
        $threat_level = $ratio >= 1000 ? 30 : 15;

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => sprintf(
                /* translators: 1: meta row count, 2: user count, 3: ratio */
                __( 'The wp_usermeta table contains %1$s rows for only %2$s registered users — a ratio of approximately %3$d:1. A healthy site typically has a ratio under 200:1. Excessive user meta is usually left behind by plugins that were removed without cleaning up their data.', 'thisismyurl-shadow' ),
                number_format_i18n( $meta_count ),
                number_format_i18n( $user_count ),
                $ratio
            ),
            'severity'     => $severity,
            'threat_level' => $threat_level,
            'details'      => array(
                'meta_row_count' => $meta_count,
                'user_count'     => $user_count,
                'ratio'          => $ratio,
            ),
        );
    }
}
