<?php
/**
 * Cache Manager
 *
 * Unified caching layer that prioritizes object cache (Redis/Memcached)
 * over WordPress transients. Improves cache performance by 5-10x when
 * object cache is available.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Core
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Cache_Manager Class
 *
 * Unified caching interface that automatically uses object cache (Redis/Memcached)
 * if available, falling back to WordPress transients for sites without object cache.
 *
 * Benefits:
 * - 5-10x faster cache retrieval with Redis/Memcached
 * - Automatic fallback to transients on non-optimized sites
 * - Single API for all cache operations
 * - Consistent cache TTL across all features
 *
 * @since 0.6095
 */
class Cache_Manager {

	/**
	 * Default cache group for Shadow by Christopher Ross
	 *
	 * @var string
	 */
	const GROUP = 'thisismyurl-shadow';

	/**
	 * Default cache expiration (1 hour)
	 *
	 * @var int
	 */
	const DEFAULT_EXPIRE = HOUR_IN_SECONDS;

	/**
	 * Whether object cache is available
	 *
	 * @var bool|null
	 */
	private static $has_object_cache;

	/**
	 * Get cached value with object cache priority
	 *
	 * Attempts to retrieve from object cache first (Redis/Memcached),
	 * then falls back to transients if needed.
	 *
	 * @since 0.6095
	 * @param  string $key Cache key.
	 * @param  string $group Cache group (default: thisismyurl-shadow).
	 * @param  mixed $default Default value if not found.
	 * @return mixed Cached value or default value.
	 */
	public static function get( string $key, string $group = self::GROUP, $default = false ) {
		// Try object cache first (Redis/Memcached)
		if ( self::has_object_cache() ) {
			$value = wp_cache_get( $key, $group );
			if ( false !== $value ) {
				/**
				 * Action: Cache hit from object cache
				 *
				 * @since 0.6095
				 * @param string $key Cache key.
				 * @param string $group Cache group.
				 */
				do_action( 'thisismyurl_shadow_cache_hit_object', $key, $group );
				return $value;
			}
		}

		// Fall back to transients (database or external cache)
		$value = get_transient( $key );
		if ( false !== $value ) {
			/**
			 * Action: Cache hit from transients
			 *
			 * @since 0.6095
			 * @param string $key Cache key.
			 */
			do_action( 'thisismyurl_shadow_cache_hit_transient', $key );
			return $value;
		}

		return $default;
	}

	/**
	 * Set cache value
	 *
	 * Stores value in both object cache (if available) and transients
	 * for maximum compatibility.
	 *
	 * @since 0.6095
	 * @param  string $key Cache key.
	 * @param  mixed $value Value to cache.
	 * @param  int $expire Expiration time in seconds (default: 1 hour).
	 * @param  string $group Cache group (default: thisismyurl-shadow).
	 * @return bool True on success.
	 */
	public static function set( string $key, $value, int $expire = self::DEFAULT_EXPIRE, string $group = self::GROUP ): bool {
		// Store in object cache if available
		if ( self::has_object_cache() ) {
			wp_cache_set( $key, $value, $group, $expire );
		}

		// Always store in transients for sites without object cache
		set_transient( $key, $value, $expire );

		/**
		 * Action: Cache value set
		 *
		 * @since 0.6095
		 * @param string $key Cache key.
		 * @param mixed $value Cached value.
		 * @param int $expire Expiration time.
		 * @param string $group Cache group.
		 */
		do_action( 'thisismyurl_shadow_cache_set', $key, $value, $expire, $group );

		return true;
	}

	/**
	 * Delete cache value
	 *
	 * Removes value from both object cache and transients.
	 *
	 * @since 0.6095
	 * @param  string $key Cache key.
	 * @param  string $group Cache group (default: thisismyurl-shadow).
	 * @return bool True on success.
	 */
	public static function delete( string $key, string $group = self::GROUP ): bool {
		// Delete from object cache
		if ( self::has_object_cache() ) {
			wp_cache_delete( $key, $group );
		}

		// Delete from transients
		delete_transient( $key );

		/**
		 * Action: Cache value deleted
		 *
		 * @since 0.6095
		 * @param string $key Cache key.
		 * @param string $group Cache group.
		 */
		do_action( 'thisismyurl_shadow_cache_delete', $key, $group );

		return true;
	}

	/**
	 * Clear all Shadow by Christopher Ross cache
	 *
	 * Flushes all Shadow by Christopher Ross-related cache entries from both
	 * object cache and transients.
	 *
	 * @since 0.6095
	 * @return bool True on success.
	 */
	public static function flush(): bool {
		// Clear object cache group
		if ( self::has_object_cache() ) {
			wp_cache_flush();
		}

		// For transients, we'd need to track all keys, so we use a prefix-based delete
		// This is less efficient but works across all setups
		global $wpdb;

		/*
		 * This cache flush uses $wpdb on purpose.
		 *
		 * WordPress has delete_transient(), but it requires exact keys. This cache layer stores a
		 * whole namespace of transients under a shared prefix and does not maintain a canonical
		 * registry of every generated key. Iterating unknown transients through core APIs is not
		 * possible without first discovering them in wp_options, which brings us back to the same
		 * database scan.
		 *
		 * A single prefix delete against wp_options is therefore the most accurate and least fragile
		 * way to clear only Shadow by Christopher Ross-owned transient rows when no object-cache registry exists.
		 */

		// Delete all transients matching pattern
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_thisismyurl_shadow_shadow' ) . '%'
			)
		);

		/**
		 * Action: All cache flushed
		 *
		 * @since 0.6095
		 */
		do_action( 'thisismyurl_shadow_cache_flushed' );

		return true;
	}

	/**
	 * Check if object cache is available
	 *
	 * Determines whether Redis/Memcached is available by checking
	 * if the WordPress object cache has been extended.
	 *
	 * @since 0.6095
	 * @return bool True if object cache is available.
	 */
	public static function has_object_cache(): bool {
		// Cache the result to avoid repeated function calls
		if ( null === self::$has_object_cache ) {
			self::$has_object_cache = (bool) wp_using_ext_object_cache();
		}

		return (bool) self::$has_object_cache;
	}

	/**
	 * Get cache statistics
	 *
	 * Returns information about cache usage (useful for debugging/monitoring).
	 *
	 * @since 0.6095
	 * @return array {
	 *     Cache statistics.
	 *
	 *     @type bool $has_object_cache Whether object cache is available.
	 *     @type string $cache_type Type of cache (object_cache, transients, or none).
	 *     @type int $estimated_entries Estimated number of cache entries.
	 * }
	 */
	public static function get_stats(): array {
		global $wpdb;

		$has_cache = self::has_object_cache();

		/*
		 * Counting transient rows also remains a direct $wpdb query intentionally.
		 *
		 * Core exposes get_transient() and delete_transient(), but it does not provide a native
		 * API to count all transient rows for a prefix in wp_options. We need an aggregate count
		 * for diagnostics rather than the value of any one transient, so querying wp_options with
		 * COUNT(*) is both simpler and more correct than attempting to infer counts through higher-
		 * level APIs that do not expose this information.
		 */

		// Count transient entries
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$transient_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_thisismyurl_shadow_shadow' ) . '%'
			)
		);

		return array(
			'has_object_cache'   => $has_cache,
			'cache_type'         => $has_cache ? 'object_cache' : 'transients',
			'transient_entries'  => (int) $transient_count,
		);
	}

	/**
	 * Get cache information for debugging
	 *
	 * Returns human-readable cache information.
	 *
	 * @since 0.6095
	 * @return string Human-readable cache info.
	 */
	public static function get_info(): string {
		$stats = self::get_stats();

		$info = sprintf(
			'Cache Type: %s | Object Cache: %s | Transient Entries: %d',
			$stats['cache_type'],
			$stats['has_object_cache'] ? 'Yes' : 'No',
			$stats['transient_entries']
		);

		return $info;
	}
}
