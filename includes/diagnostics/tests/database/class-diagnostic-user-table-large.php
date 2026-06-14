<?php
/**
 * User Table Large Diagnostic
 *
 * Checks whether the wp_users table has grown large enough to cause
 * noticeable performance degradation in the WordPress admin. Large user
 * tables slow down the Users list screen, author dropdown menus in the
 * Gutenberg post editor, and any query that joins against wp_users.
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
 * Diagnostic_User_Table_Large Class
 *
 * @since 0.6095
 */
class Diagnostic_User_Table_Large extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'user-table-large';

    /** @var string */
    protected static $title = 'User Table Unusually Large';

    /** @var string */
    protected static $description = 'Checks whether wp_users has grown large enough to cause noticeable slowdowns in the admin. Large user tables degrade the Users list screen, author dropdowns in the block editor, and any query that joins against wp_users.';

    /** @var string */
    protected static $family = 'database';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

    /**
     * Medium-severity threshold: user count above this triggers a medium warning.
     *
     * Gutenberg's author panel begins showing visible lag around this mark on
     * servers without a persistent object cache.
     *
     * @var int
     */
    private const THRESHOLD_MEDIUM = 5000;

    /**
     * High-severity threshold: user count above this triggers a high warning.
     *
     * The Users list screen, wp_count_users(), and core JOIN queries become
     * notably slow at this scale without proper indexing or a query cache.
     *
     * @var int
     */
    private const THRESHOLD_HIGH = 20000;

    /**
     * Run the diagnostic check.
     *
     * Issues a COUNT(*) query against wp_users. The Primary Key index on the ID
     * column makes this a cheap index scan (< 1 ms on modern MySQL/MariaDB) even
     * for tables with hundreds of thousands of rows.
     *
     * @since  0.6095
     * @return array|null Finding array when user count exceeds thresholds, null when healthy.
     */
    public static function check(): ?array {
        $user_count_data = count_users();
        $user_count      = isset( $user_count_data['total_users'] ) ? (int) $user_count_data['total_users'] : 0;

        if ( $user_count < self::THRESHOLD_MEDIUM ) {
            return null;
        }

        if ( $user_count >= self::THRESHOLD_HIGH ) {
            $severity     = 'high';
            $threat_level = 60;
            $description  = sprintf(
                /* translators: %s: formatted user count */
                __(
                    'wp_users contains %s user records. At this scale, the Users admin screen, Gutenberg\'s author selector panel, and any plugin or code that loads a full user list will suffer significant slowdowns unless a persistent object cache (Redis/Memcached) is in place. Review whether all accounts are needed, implement bulk user cleanup for inactive accounts, and ensure the object cache is active.',
                    'thisismyurl-shadow'
                ),
                number_format_i18n( $user_count )
            );
        } else {
            $severity     = 'medium';
            $threat_level = 35;
            $description  = sprintf(
                /* translators: %s: formatted user count */
                __(
                    'wp_users contains %s user records. The Gutenberg author selector and the Users admin screen may experience noticeable lag on servers without a persistent object cache. Consider whether all accounts are still required and audit for unused or spam registrations.',
                    'thisismyurl-shadow'
                ),
                number_format_i18n( $user_count )
            );
        }

        // Cache-aware check: if a persistent object cache is active, the impact
        // is significantly reduced. Downgrade severity by one level if detected.
        $has_object_cache = wp_using_ext_object_cache();
        if ( $has_object_cache && 'high' === $severity ) {
            $severity     = 'medium';
            $threat_level = 40;
        } elseif ( $has_object_cache && 'medium' === $severity ) {
            $threat_level = max( 15, $threat_level - 15 );
        }

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => $description,
            'severity'     => $severity,
            'threat_level' => $threat_level,
            'details'      => array(
                'user_count'            => $user_count,
                'threshold_medium'      => self::THRESHOLD_MEDIUM,
                'threshold_high'        => self::THRESHOLD_HIGH,
                'persistent_cache'      => $has_object_cache ? 'active' : 'not active',
                'affected_areas'        => array(
                    'Admin › Users list screen',
                    'Block editor › Post author selector',
                    'Plugins that enumerate wp_get_users()',
                    'Widgets that display author lists',
                ),
                'note'                  => $has_object_cache
                    ? __(
                        'A persistent object cache is active, which reduces the impact. Still review whether all accounts are required.',
                        'thisismyurl-shadow'
                    )
                    : __(
                        'No persistent object cache detected. Adding Redis or Memcached will significantly reduce the query overhead from large user tables.',
                        'thisismyurl-shadow'
                    ),
            ),
        );
    }
}
