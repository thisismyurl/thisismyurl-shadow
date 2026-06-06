<?php
/**
 * Database Indexes Missing Diagnostic
 *
 * Checks for missing or inefficient database indexes.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Indexes Missing Diagnostic Class
 *
 * Detects missing indexes on frequently queried columns.
 * Missing indexes cause slow queries and poor performance at scale.
 *
 * @since 0.6095
 */
class Diagnostic_Database_Indexes_Missing extends Diagnostic_Base {

	/**
	 * The diagnostic slug
	 *
	 * @var string
	 */
	protected static $slug = 'database-indexes-missing';

	/**
	 * The diagnostic title
	 *
	 * @var string
	 */
	protected static $title = 'Database Indexes Missing';

	/**
	 * The diagnostic description
	 *
	 * @var string
	 */
	protected static $description = 'Verifies that all expected indexes are present on the core WordPress database tables — wp_posts, wp_postmeta, wp_comments, and wp_options. Missing indexes force MySQL to perform full table scans, which cause slow queries and admin timeouts on sites with more than a few thousand rows.';

	/**
	 * The family this diagnostic belongs to
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * Verifies critical indexes exist on WordPress tables.
	 * Missing indexes severely impact query performance as data grows.
	 *
	 * @since 0.6095
	 * @return array|null Finding array if issue found, null otherwise.
	 */
	public static function check() {
		global $wpdb;

		/*
		 * This diagnostic intentionally queries table metadata through $wpdb.
		 * The question being answered is not "can WordPress load these entities" but "which physical
		 * indexes exist on these tables, how large are the affected tables, and do custom plugin
		 * tables appear to be unindexed?" WordPress core does not expose a table-index inspection API,
		 * so SHOW INDEX, COUNT(*), and SHOW TABLES LIKE are the correct primitives for the job.
		 */

		$missing_indexes = array();

		// Define expected indexes for WordPress core tables
		$expected_indexes = array(
			$wpdb->posts => array(
				'post_name'           => 'post_name',
				'type_status_date'    => 'type_status_date',
				'post_parent'         => 'post_parent',
				'post_author'         => 'post_author',
			),
			$wpdb->postmeta => array(
				'post_id'    => 'post_id',
				'meta_key'   => 'meta_key',
			),
			$wpdb->comments => array(
				'comment_post_ID'      => 'comment_post_ID',
				'comment_approved_date_gmt' => 'comment_approved_date_gmt',
				'comment_parent'       => 'comment_parent',
			),
			$wpdb->term_relationships => array(
				'term_taxonomy_id' => 'term_taxonomy_id',
			),
			$wpdb->usermeta => array(
				'user_id'   => 'user_id',
				'meta_key'  => 'meta_key',
			),
		);

		// Check each table for expected indexes
		foreach ( $expected_indexes as $table => $indexes ) {
			// Get existing indexes
			$existing_indexes = $wpdb->get_results(
				$wpdb->prepare( 'SHOW INDEX FROM %i', $table ),
				ARRAY_A
			);

			if ( empty( $existing_indexes ) ) {
				$missing_indexes[] = sprintf(
					/* translators: %s: table name */
					__( 'Unable to check indexes on table %s', 'thisismyurl-shadow' ),
					$table
				);
				continue;
			}

			// Build array of existing index names
			$existing_names = array();
			foreach ( $existing_indexes as $index ) {
				$existing_names[] = $index['Key_name'];
			}

			// Check for missing indexes
			foreach ( $indexes as $index_name => $column ) {
				if ( ! in_array( $index_name, $existing_names, true ) ) {
					$missing_indexes[] = sprintf(
						/* translators: 1: index name, 2: table name */
						__( 'Missing %1$s index on %2$s', 'thisismyurl-shadow' ),
						$index_name,
						$table
					);
				}
			}
		}

		// Check for large tables without indexes
		$large_tables = array();
		$table_list   = array( $wpdb->posts, $wpdb->postmeta, $wpdb->comments, $wpdb->usermeta );

		foreach ( $table_list as $table ) {
			$row_count = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
			);

			if ( $row_count > 100000 ) { // 100k+ rows
				$large_tables[ $table ] = $row_count;
			}
		}

		// If large tables exist, missing indexes are critical
		$severity     = 'medium';
		$threat_level = 50;

		if ( ! empty( $large_tables ) && ! empty( $missing_indexes ) ) {
			$severity     = 'high';
			$threat_level = 80;
		}

		// If missing indexes found
		if ( ! empty( $missing_indexes ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %s: list of missing indexes */
					__( 'Missing database indexes detected: %s. Missing indexes cause slow queries, especially on large tables. Database performance degrades significantly as data grows.', 'thisismyurl-shadow' ),
					implode( '; ', array_slice( $missing_indexes, 0, 5 ) )
				),
				'severity'     => $severity,
				'threat_level' => $threat_level,
				'meta'         => array(
					'missing_indexes'  => $missing_indexes,
					'total_missing'    => count( $missing_indexes ),
					'large_tables'     => $large_tables,
					'has_large_tables' => ! empty( $large_tables ),
					'recommendation'   => 'Run WordPress database repair or use index optimization plugin',
				),
			);
		}

		// Check for missing indexes on custom tables (common with plugins)
		$custom_tables = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$wpdb->esc_like( $wpdb->prefix ) . '%'
			),
			ARRAY_N
		);

		$unindexed_custom_tables = 0;

		if ( $custom_tables ) {
			foreach ( $custom_tables as $table_row ) {
				$table = $table_row[0];

				// Skip core tables
				if ( in_array( $table, array( $wpdb->posts, $wpdb->postmeta, $wpdb->comments, $wpdb->usermeta, $wpdb->users, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->options, $wpdb->links, $wpdb->commentmeta, $wpdb->termmeta ), true ) ) {
					continue;
				}

				// Check if table has any indexes
				$indexes = $wpdb->get_results(
					$wpdb->prepare( 'SHOW INDEX FROM %i', $table ),
					ARRAY_A
				);

				// Count non-primary indexes
				$index_count = 0;
				if ( $indexes ) {
					foreach ( $indexes as $index ) {
						if ( 'PRIMARY' !== $index['Key_name'] ) {
							$index_count++;
						}
					}
				}

				if ( $index_count === 0 ) {
					$unindexed_custom_tables++;
				}
			}
		}

		if ( $unindexed_custom_tables > 5 ) {
			return array(
				'id'           => 'custom-tables-unindexed',
				'title'        => __( 'Custom Tables Lack Indexes', 'thisismyurl-shadow' ),
				'description'  => sprintf(
					/* translators: %d: number of unindexed tables */
					__( '%d custom plugin tables have no indexes. Plugins creating custom tables should add appropriate indexes for query performance.', 'thisismyurl-shadow' ),
					$unindexed_custom_tables
				),
				'severity'     => 'medium',
				'threat_level' => 45,
				'meta'         => array(
					'unindexed_tables' => $unindexed_custom_tables,
					'recommendation'   => 'Contact plugin developers about index optimization',
				),
			);
		}

		// All expected indexes present
		return null;
	}
}
