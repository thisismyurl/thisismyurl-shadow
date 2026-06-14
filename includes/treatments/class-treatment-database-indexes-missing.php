<?php
/**
 * Treatment: Database Indexes Missing
 *
 * Inspects the same expected indexes that the diagnostic checks and runs
 * a CREATE INDEX statement for each one that is absent. The treatment stores
 * the list of indexes it added so that undo() can drop them precisely.
 *
 * Expected indexes (matching the diagnostic):
 *  - wp_posts:             post_name, type_status_date, post_parent, post_author
 *  - wp_postmeta:          post_id, meta_key
 *  - wp_comments:          comment_post_ID, comment_approved_date_gmt, comment_parent
 *  - wp_term_relationships: term_taxonomy_id
 *  - wp_usermeta:          user_id, meta_key
 *
 * Most of these indexes are created by WordPress's dbDelta() at install time.
 * They can go missing after manual table modifications or faulty plugin
 * migrations. Adding them back is non-destructive and does not alter data.
 *
 * Risk level: medium (schema change)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

require_once __DIR__ . '/trait-database-schema-helpers.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Adds missing indexes to core WordPress tables.
 */
class Treatment_Database_Indexes_Missing extends Treatment_Base {

	use Database_Schema_Helpers;

	/**
	 * Treatment slug.
	 *
	 * @var string
	 */
	protected static $slug = 'database-indexes-missing';

	const OPTION_KEY = 'thisismyurl_shadow_added_db_indexes';

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	/**
	 * Get the treatment finding identifier.
	 *
	 * @return string
	 */
	public static function get_finding_id(): string {
		return self::$slug;
	}

	/**
	 * Get the treatment risk level.
	 *
	 * @return string
	 */
	public static function get_risk_level(): string {
		return 'medium';
	}

	/**
	 * Add each missing index to its table.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function apply(): array {
		global $wpdb;

		/*
		 * Index inspection and creation intentionally stay on $wpdb.
		 * Core does not expose an API for SHOW INDEX, for comparing existing index definitions, or
		 * for issuing ALTER TABLE ... ADD INDEX statements. This treatment is working against table
		 * metadata rather than posts or options, so a schema-level query is the only faithful tool.
		 */

		$expected = self::get_expected_indexes();
		$added    = array();
		$failures = array();

		foreach ( $expected as $table => $indexes ) {
			$table_name = self::require_schema_identifier( (string) $table );
			if ( '' === $table_name ) {
				$failures[] = sprintf( 'Invalid table identifier for index inspection: %s', (string) $table );
				continue;
			}

			// Get existing index names for this table.
			$existing_rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SHOW INDEX is required for bounded schema inspection before applying this treatment.
				$wpdb->prepare( 'SHOW INDEX FROM %i', $table_name ),
				ARRAY_A
			);
			$existing_names = array_column( (array) $existing_rows, 'Key_name' );

			foreach ( $indexes as $index_name => $definition ) {
				$index_identifier = self::require_schema_identifier( (string) $index_name );
				$index_definition = self::require_index_definition( (string) $definition );

				if ( '' === $index_identifier || '' === $index_definition ) {
					$failures[] = sprintf( 'Invalid index definition for `%s`.', $table_name );
					continue;
				}

				if ( in_array( $index_name, $existing_names, true ) ) {
					continue; // Already present.
				}

				$sql    = sprintf( 'ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table_name, $index_identifier, $index_definition );
				$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ALTER TABLE requires validated DDL fragments for this bounded schema migration.

				if ( false === $result ) {
					$failures[] = sprintf( '`%s`.`%s`: %s', $table_name, $index_identifier, $wpdb->last_error );
				} else {
					$added[] = array(
						'table' => $table_name,
						'index' => $index_identifier,
					);
				}
			}
		}

		if ( ! empty( $added ) ) {
			// Store what we added so undo() can remove exactly those indexes.
			$previous = (array) get_option( self::OPTION_KEY, array() );
			update_option( self::OPTION_KEY, array_merge( $previous, $added ), false );
		}

		if ( empty( $added ) && empty( $failures ) ) {
			return array(
				'success' => true,
				'message' => __( 'All expected indexes are already present — no changes were made.', 'thisismyurl-shadow' ),
			);
		}

		$added_summary = implode(
			', ',
			array_map(
				static function ( $a ): string {
					return sprintf( '`%s`.`%s`', $a['table'], $a['index'] );
				},
				$added
			)
		);

		if ( ! empty( $failures ) ) {
			$added_summary_text = '' !== $added_summary ? $added_summary : 'none';

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: added list, 2: failure list */
					__( 'Added: %1$s. Failed to add: %2$s.', 'thisismyurl-shadow' ),
					$added_summary_text,
					implode( '; ', $failures )
				),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: comma-separated list of index.table pairs */
				__( 'Successfully added %1$d missing index(es): %2$s.', 'thisismyurl-shadow' ),
				count( $added ),
				$added_summary
			),
		);
	}

	/**
	 * Drop only the indexes that this treatment added.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function undo(): array {
		global $wpdb;

		/*
		 * Dropping only the indexes created by this treatment is also a schema concern with no core
		 * wrapper. Direct SQL is necessary to target the exact index names recorded in the backup.
		 */

		$added = (array) get_option( self::OPTION_KEY, array() );

		if ( empty( $added ) ) {
			return array(
				'success' => false,
				'message' => __( 'No record of indexes added by Shadow by Christopher Ross — cannot undo. Indexes (if present) must be removed manually.', 'thisismyurl-shadow' ),
			);
		}

		$dropped  = array();
		$failures = array();

		foreach ( $added as $item ) {
			$table = self::require_schema_identifier( (string) ( $item['table'] ?? '' ) );
			$index = self::require_schema_identifier( (string) ( $item['index'] ?? '' ) );

			if ( '' === $table || '' === $index ) {
				continue;
			}

			$result = $wpdb->query( sprintf( 'ALTER TABLE `%s` DROP INDEX `%s`', $table, $index ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- ALTER TABLE requires validated DDL fragments for this bounded schema rollback.

			if ( false === $result ) {
				$failures[] = sprintf( '`%s`.`%s`: %s', $table, $index, $wpdb->last_error );
			} else {
				$dropped[] = sprintf( '`%s`.`%s`', $table, $index );
			}
		}

		delete_option( self::OPTION_KEY );

		if ( ! empty( $failures ) ) {
			$dropped_summary = ! empty( $dropped ) ? implode( ', ', $dropped ) : 'none';

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: dropped list, 2: failure list */
					__( 'Dropped: %1$s. Failed to drop: %2$s.', 'thisismyurl-shadow' ),
					$dropped_summary,
					implode( '; ', $failures )
				),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: list of removed indexes */
				__( 'Removed %1$d index(es) added by Shadow by Christopher Ross: %2$s.', 'thisismyurl-shadow' ),
				count( $dropped ),
				implode( ', ', $dropped )
			),
		);
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Return the expected indexes for each WordPress core table.
	 *
	 * The key is the index name; the value is the column expression used in
	 * CREATE INDEX.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function get_expected_indexes(): array {
		global $wpdb;

		return array(
			$wpdb->posts              => array(
				'post_name'        => '`post_name`(20)',
				'type_status_date' => '`post_type`, `post_status`, `post_date`, `ID`',
				'post_parent'      => '`post_parent`',
				'post_author'      => '`post_author`',
			),
			$wpdb->postmeta           => array(
				'post_id'  => '`post_id`',
				'meta_key' => '`meta_key`(191)', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is a schema definition list, not a runtime content query.
			),
			$wpdb->comments           => array(
				'comment_post_ID'           => '`comment_post_ID`',
				'comment_approved_date_gmt' => '`comment_approved`, `comment_date_gmt`',
				'comment_parent'            => '`comment_parent`',
			),
			$wpdb->term_relationships => array(
				'term_taxonomy_id' => '`term_taxonomy_id`',
			),
			$wpdb->usermeta           => array(
				'user_id'  => '`user_id`',
				'meta_key' => '`meta_key`(191)', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is a schema definition list, not a runtime content query.
			),
		);
	}
}
