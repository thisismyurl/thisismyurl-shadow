<?php
/**
 * Stale Sessions Diagnostic
 *
 * Checks that expired WordPress user sessions are not accumulating in
 * wp_usermeta. Each logged-in user stores a serialised session token in
 * wp_usermeta; WordPress should be expiring these automatically, but on busy
 * sites they can pile up and slow user-related queries.
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
 * Diagnostic_Stale_Sessions_Cleared Class
 *
 * @since 0.6095
 */
class Diagnostic_Stale_Sessions_Cleared extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'stale-sessions-cleared';

    /** @var string */
    protected static $title = 'Stale User Sessions Not Accumulating';

    /** @var string */
    protected static $description = 'Checks that the number of user session token rows in wp_usermeta is not disproportionate to the active user count, which would indicate expired sessions are not being cleared.';

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
     * Counts wp_usermeta rows with meta_key = 'session_tokens' (WordPress
     * native session storage) and compares against the registered user count.
     * Returns null when the session count is reasonable. Returns a low or medium
     * finding when sessions significantly outnumber users.
     *
     * @since  0.6095
     * @return array|null Finding array when stale sessions are accumulating, null when healthy.
     */
    public static function check() {
        global $wpdb;

        $user_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users}"
        );

        if ( $user_count === 0 ) {
            return null;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $session_rows = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
            'session_tokens'
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        // Each user can legitimately have one session_tokens row (containing
        // multiple device tokens). A ratio above 1:1 indicates stale rows.
        // We flag when sessions are more than 2× the user count.
        if ( $session_rows <= ( $user_count * 2 ) ) {
            return null;
        }

        $excess        = $session_rows - $user_count;
        $severity      = $excess >= 1000 ? 'medium' : 'low';
        $threat_level  = $excess >= 1000 ? 30 : 10;

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => sprintf(
                /* translators: 1: session row count, 2: user count */
                __( '%1$d session token rows exist in wp_usermeta for only %2$d registered users. This suggests expired sessions are accumulating and not being cleaned up. Stale sessions bloat the database and can slow user-authentication queries.', 'thisismyurl-shadow' ),
                $session_rows,
                $user_count
            ),
            'severity'     => $severity,
            'threat_level' => $threat_level,
            'details'      => array(
                'session_token_rows' => $session_rows,
                'user_count'         => $user_count,
                'excess_rows'        => $excess,
            ),
        );
    }
}
