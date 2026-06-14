<?php
/**
 * Post Meta Bloat Diagnostic
 *
 * Checks that the wp_postmeta table has not grown to a size that is
 * disproportionate to the number of published posts. A high ratio usually
 * indicates orphaned or abandoned plugin data.
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
 * Diagnostic_Post_Meta_Bloat_Detected Class
 *
 * @since 0.6095
 */
class Diagnostic_Post_Meta_Bloat_Detected extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'post-meta-bloat-detected';

    /** @var string */
    protected static $title = 'wp_postmeta Not Excessively Bloated';

    /** @var string */
    protected static $description = 'Checks that the ratio of wp_postmeta rows to published post count is under 100:1. A higher ratio typically signals abandoned plugin data clogging the database.';

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
     * Counts wp_postmeta rows and published post count, then computes the ratio.
     * Returns null when the ratio is under 100:1 or when there are too few posts
     * to judge. Returns a medium or low severity finding based on the ratio.
     *
     * @since  0.6095
     * @return array|null Finding array when post meta ratio is too high, null when healthy.
     */
    public static function check() {
        global $wpdb;

        $published_posts = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'"
        );

        if ( $published_posts < 5 ) {
            return null; // Not enough data for a meaningful ratio.
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $meta_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        $ratio = $published_posts > 0 ? (int) round( $meta_count / $published_posts ) : 0;

        if ( $ratio < 100 ) {
            return null;
        }

        $severity     = $ratio >= 500 ? 'medium' : 'low';
        $threat_level = $ratio >= 500 ? 30 : 15;

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => sprintf(
                /* translators: 1: meta row count, 2: post count, 3: ratio */
                __( 'The wp_postmeta table contains %1$s rows for only %2$s published posts — a ratio of approximately %3$d:1. A healthy site typically has a ratio under 100:1. This level of bloat usually indicates orphaned metadata left behind by removed or poorly-coded plugins.', 'thisismyurl-shadow' ),
                number_format_i18n( $meta_count ),
                number_format_i18n( $published_posts ),
                $ratio
            ),
            'severity'     => $severity,
            'threat_level' => $threat_level,
            'details'      => array(
                'meta_row_count'  => $meta_count,
                'published_posts' => $published_posts,
                'ratio'           => $ratio,
            ),
        );
    }
}
