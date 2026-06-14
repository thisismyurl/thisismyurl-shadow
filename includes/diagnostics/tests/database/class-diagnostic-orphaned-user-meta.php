<?php
/**
 * Orphaned User Meta Diagnostic
 *
 * Checks for rows in wp_usermeta whose user_id does not correspond to any
 * existing user in wp_users. Orphaned user meta accumulates when user accounts
 * are deleted without proper cleanup hooks running.
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
 * Diagnostic_Orphaned_User_Meta Class
 *
 * @since 0.6095
 */
class Diagnostic_Orphaned_User_Meta extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'orphaned-user-meta';

    /** @var string */
    protected static $title = 'No Orphaned User Meta';

    /** @var string */
    protected static $description = 'Checks that every row in wp_usermeta has a corresponding user in wp_users. Orphaned rows are created when users are deleted without cleanup hooks and waste database space.';

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
     * Runs a LEFT JOIN query to count wp_usermeta rows whose user_id has no
     * corresponding wp_users record. Returns null when the count is zero.
     * Returns a low or medium severity finding based on the orphaned row count.
     *
     * @since  0.6095
     * @return array|null Finding array when orphaned user meta rows are found, null when healthy.
     */
    public static function check() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $orphaned_count = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->usermeta} um
             LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
             WHERE u.ID IS NULL"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        if ( $orphaned_count === 0 ) {
            return null;
        }

        $severity     = $orphaned_count >= 500 ? 'medium' : 'low';
        $threat_level = $orphaned_count >= 500 ? 30 : 10;

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => sprintf(
                /* translators: %d: number of orphaned rows */
                _n(
                    '%d orphaned row was found in wp_usermeta — it has no corresponding user in wp_users. These rows accumulate when user accounts are deleted without proper cleanup hooks and waste database space.',
                    '%d orphaned rows were found in wp_usermeta — they have no corresponding users in wp_users. These rows accumulate when user accounts are deleted without proper cleanup hooks and waste database space.',
                    $orphaned_count,
                    'thisismyurl-shadow'
                ),
                $orphaned_count
            ),
            'severity'     => $severity,
            'threat_level' => $threat_level,
            'details'      => array(
                'orphaned_rows' => $orphaned_count,
                'explanation_sections' => array(
                    'summary' => sprintf(
                        /* translators: %d: orphaned wp_usermeta rows */
                        __( 'Shadow by Christopher Ross found %d orphaned user meta rows. These are records in wp_usermeta that no longer belong to a real user account, usually because an account was deleted by a custom workflow or plugin that skipped cleanup hooks. They do not break the front end, but they do increase table size and backup volume over time.', 'thisismyurl-shadow' ),
                        $orphaned_count
                    ),
                    'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross performed a direct LEFT JOIN between wp_usermeta and wp_users, then counted usermeta rows where no matching user ID exists. This is a deterministic database-level check, so false positives are uncommon unless custom table prefixes or direct user storage customizations bypass core conventions.', 'thisismyurl-shadow' ),
                    'why_it_matters' => __( 'Orphaned metadata adds unnecessary rows to a table that many plugins query frequently. As this grows, user-related lookups and exports become heavier, and routine operations like backups, migrations, and database optimization take longer. Keeping usermeta aligned with real accounts improves operational hygiene and helps prevent hidden data drift.', 'thisismyurl-shadow' ),
                    'how_to_fix_it' => __( 'Before cleanup, create a fresh backup. Then remove orphaned rows with a trusted maintenance plugin or a targeted SQL cleanup, and review any custom deletion workflows so future user removals call proper cleanup hooks. After remediation, run this check again to confirm the orphaned count returns to zero and stays stable over the next few scan cycles.', 'thisismyurl-shadow' ),
                ),
            ),
        );
    }
}
