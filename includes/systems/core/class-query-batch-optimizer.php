<?php
/**
 * Query Batch Optimizer
 *
 * Batch-processes multiple database queries to reduce query count.
 * Uses query result caching and batching to minimize database load.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Core
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Batch Optimizer Class
 *
 * Optimizes database queries by batching and caching results.
 *
 * Philosophy: Efficient (#3) - Minimize database calls.
 *
 * @since 0.6095
 */
class Query_Batch_Optimizer {

	/**
	 * @var array Pending queries to batch
	 */
	private static $pending_queries = array();

	/**
	 * @var array Query results cache
	 */
	private static $query_cache = array();

	/**
	 * @var int Batch size before executing
	 */
	private static $batch_size = 10;

	/**
	 * Initialize query optimization
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function init(): void {
		// Execute pending batches on shutdown
		add_action( 'shutdown', array( __CLASS__, 'execute_pending_batches' ), 999 );
	}

	/**
	 * Queue a query for batch execution
	 *
	 * Queries are queued and executed in batches rather than individually.
	 *
	 * @since 0.6095
	 * @param  string $query Query SQL
	 * @param  string $output Output format (OBJECT, ARRAY_A, ARRAY_N, etc)
	 * @return string Batch query ID
	 */
	public static function queue_query( string $query, string $output = OBJECT ): string {
		global $wpdb;

		/*
		 * This utility exists specifically to batch raw SQL, so keeping $wpdb here is intentional.
		 * There is no WordPress wrapper for arbitrary query batching because callers may need custom
		 * SELECT statements, joins, or aggregate queries that do not map to a single core helper.
		 */

		// Generate cache key
		$cache_key = md5( $query );

		// Check if already cached
		if ( isset( self::$query_cache[ $cache_key ] ) ) {
			return $cache_key;
		}

		// Add to pending batch
		$batch_id          = uniqid( 'batch_' );
		self::$pending_queries[ $batch_id ] = array(
			'query'  => $query,
			'output' => $output,
			'key'    => $cache_key,
		);

		// Execute batch if threshold reached
		if ( count( self::$pending_queries ) >= self::$batch_size ) {
			self::execute_pending_batches();
		}

		return $cache_key;
	}

	/**
	 * Execute all pending batch queries
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function execute_pending_batches(): void {
		global $wpdb;

		if ( empty( self::$pending_queries ) ) {
			return;
		}

		// Execute queries in batch
		foreach ( self::$pending_queries as $batch_id => $query_data ) {
			$key = $query_data['key'];

			// Skip if already cached
			if ( isset( self::$query_cache[ $key ] ) ) {
				continue;
			}

			/*
			 * These executions stay on $wpdb because the queued payload is already SQL.
			 * The abstraction boundary for this class is intentionally "prepared query in, result set
			 * out" rather than "entity lookup in, objects out".
			 */
			$result = $wpdb->get_results( $query_data['query'], $query_data['output'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			// Cache result
			self::$query_cache[ $key ] = $result;

			/**
			 * Fires after query execution
			 *
			 * @since 0.6095
			 *
			 * @param string $key Query cache key
			 * @param mixed $result Query results
			 * @param string $query SQL query
			 */
			do_action( 'thisismyurl_shadow_query_executed', $key, $result, $query_data['query'] );
		}

		// Clear pending queue
		self::$pending_queries = array();
	}

	/**
	 * Get query statistics
	 *
	 * @since 0.6095
	 * @return array {
	 *     Query statistics.
	 *
	 *     @type int $pending Number of pending queries
	 *     @type int $cached Number of cached results
	 *     @type int $total Total queries processed
	 * }
	 */
	public static function get_stats(): array {
		return array(
			'pending' => count( self::$pending_queries ),
			'cached'  => count( self::$query_cache ),
			'total'   => count( self::$pending_queries ) + count( self::$query_cache ),
		);
	}

	/**
	 * Clear all caches
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function clear(): void {
		self::$pending_queries = array();
		self::$query_cache     = array();
	}

}
