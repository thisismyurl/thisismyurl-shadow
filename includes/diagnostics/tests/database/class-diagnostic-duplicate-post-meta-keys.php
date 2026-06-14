<?php
/**
 * Duplicate Post Meta Keys Diagnostic
 *
 * Checks for meta_key values that appear on an unusually high proportion of
 * posts compared to the total published post count. This pattern typically
 * indicates a plugin that is writing data to every post but has since been
 * removed, leaving behind large amounts of stale metadata.
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
 * Diagnostic_Duplicate_Post_Meta_Keys Class
 *
 * @since 0.6095
 */
class Diagnostic_Duplicate_Post_Meta_Keys extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'duplicate-post-meta-keys';

    /** @var string */
    protected static $title = 'No High-Frequency Orphaned Post Meta Keys';

    /** @var string */
    protected static $description = 'Checks for post meta keys written to more than 80% of published posts by a plugin that is no longer active. These orphaned rows waste database space and slow meta queries.';

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
     * Queries wp_postmeta to find meta_key values that appear on more than 80%
     * of published posts. Returns null when no such keys are found. Returns a
     * medium-severity finding listing the suspect keys and their row counts when
     * the threshold is crossed.
     *
     * @since  0.6095
     * @return array|null Finding array when suspect high-frequency meta keys are found, null when healthy.
     */
    public static function check() {
        global $wpdb;

        $published_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'"
        );

        if ( $published_count < 10 ) {
            return null; // Too few posts to draw meaningful conclusions.
        }

        $threshold = (int) ceil( $published_count * 0.8 );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $suspect_keys = $wpdb->get_results( $wpdb->prepare(
            "SELECT meta_key, COUNT(DISTINCT post_id) AS post_count
             FROM {$wpdb->postmeta}
             WHERE meta_key NOT LIKE '\_%'
             GROUP BY meta_key
             HAVING post_count >= %d
             ORDER BY post_count DESC
             LIMIT 20",
            $threshold
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        if ( empty( $suspect_keys ) ) {
            return null;
        }

        // Cross-check: skip keys written by currently active plugins (heuristic).
        $active_plugin_slugs = array_map(
            static function ( $p ) { return dirname( $p ); },
            (array) get_option( 'active_plugins', array() )
        );

        $flagged = array();
        foreach ( $suspect_keys as $row ) {
            $key = $row->meta_key;
            $is_active = false;
            foreach ( $active_plugin_slugs as $slug ) {
                if ( str_contains( strtolower( $key ), strtolower( $slug ) ) ) {
                    $is_active = true;
                    break;
                }
            }
            if ( ! $is_active ) {
                $flagged[] = array(
                    'meta_key'   => $key,
                    'post_count' => (int) $row->post_count,
                );
            }
        }

        if ( empty( $flagged ) ) {
            return null;
        }

        $key_list = implode( ', ', array_column( $flagged, 'meta_key' ) );

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => sprintf(
                /* translators: %s: comma-separated list of meta key names */
                __( 'The following post meta keys appear on more than 80%% of published posts but are not associated with any currently active plugin: %s. These rows may be orphaned data from a removed plugin and are wasting database space.', 'thisismyurl-shadow' ),
                $key_list
            ),
            'severity'     => 'medium',
            'threat_level' => 30,
            'details'      => array(
                'published_posts' => $published_count,
                'threshold'       => $threshold,
                'flagged_keys'    => $flagged,
            ),
        );
    }
}
