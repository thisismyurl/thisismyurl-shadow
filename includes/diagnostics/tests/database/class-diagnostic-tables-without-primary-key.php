<?php
/**
 * Tables Without Primary Key Diagnostic
 *
 * Checks that every table in the WordPress database has a primary key defined.
 * Tables without primary keys cause performance problems in MySQL/MariaDB
 * replication and can slow certain query patterns significantly.
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
 * Diagnostic_Tables_Without_Primary_Key Class
 *
 * @since 0.6095
 */
class Diagnostic_Tables_Without_Primary_Key extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'tables-without-primary-key';

    /** @var string */
    protected static $title = 'All Tables Have a Primary Key';

    /** @var string */
    protected static $description = 'Checks that every WordPress database table has a primary key. Tables missing a primary key degrade replication performance and can slow certain queries.';

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
     * Queries INFORMATION_SCHEMA to find tables in the current database that
     * have no PRIMARY KEY constraint defined. Returns null when all tables have
     * a primary key. Returns a medium-severity finding listing affected tables.
     *
     * @since  0.6095
     * @return array|null Finding array when tables without a primary key are found, null when healthy.
     */
    public static function check() {
        if ( Server_Env::is_sqlite() ) {
            return null;
        }

        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $tables_without_pk = $wpdb->get_col( $wpdb->prepare(
            "SELECT t.TABLE_NAME
             FROM information_schema.TABLES t
             LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
               ON  tc.TABLE_SCHEMA = t.TABLE_SCHEMA
               AND tc.TABLE_NAME   = t.TABLE_NAME
               AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
             WHERE t.TABLE_SCHEMA = %s
               AND t.TABLE_TYPE   = 'BASE TABLE'
               AND tc.CONSTRAINT_NAME IS NULL
             ORDER BY t.TABLE_NAME",
            DB_NAME
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        if ( empty( $tables_without_pk ) ) {
            return null;
        }

        $count      = count( $tables_without_pk );
        $table_list = implode( ', ', $tables_without_pk );

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => sprintf(
                /* translators: 1: number of tables, 2: comma-separated table names */
                _n(
                    '%1$d database table has no primary key (%2$s). Tables without a primary key cause significant performance problems in MySQL/MariaDB row-based replication and can slow range queries.',
                    '%1$d database tables have no primary key (%2$s). Tables without a primary key cause significant performance problems in MySQL/MariaDB row-based replication and can slow range queries.',
                    $count,
                    'thisismyurl-shadow'
                ),
                $count,
                $table_list
            ),
            'severity'     => 'medium',
            'threat_level' => 35,
            'details'      => array(
                'tables'      => $tables_without_pk,
                'table_count' => $count,
            ),
        );
    }
}
