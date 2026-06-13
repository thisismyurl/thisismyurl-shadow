<?php
declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Activity Logger - Comprehensive activity tracking system
 *
 * Philosophy: Show value (#9) - Track everything to prove impact
 * Scope: User actions tracked locally with no external calls
 *
 * @package ThisIsMyURL\Shadow
 */
class Activity_Logger {

	/**
	 * Option name for activity log
	 */
	const OPTION_NAME = 'thisismyurl_shadow_activity_log';

	/**
	 * Maximum activities to store (keep last 500)
	 */
	const MAX_ACTIVITIES = 500;

	/**
	 * Log an activity
	 *
	 * @param string $action Action type (e.g., 'diagnostic_run', 'treatment_applied')
	 * @param string $details Human-readable description
	 * @param string $category Optional category (security, performance, etc.)
	 * @param array  $metadata Optional additional data
	 * @return bool Success status
	 */
	public static function log( string $action, string $details, string $category = '', array $metadata = array() ): bool {
		$activity = array(
			'id'        => uniqid( 'activity_', true ),
			'action'    => $action,
			'details'   => $details,
			'category'  => $category,
			'metadata'  => $metadata,
			'user_id'   => get_current_user_id(),
			'user_name' => wp_get_current_user()->display_name,
			'timestamp' => current_time( 'timestamp' ),
			'date'      => current_time( 'mysql' ),
		);

		/**
		 * Filter a log entry before it is persisted.
		 *
		 * @param array  $activity Activity payload.
		 * @param string $action   Action key.
		 * @param string $details  Description.
		 * @param string $category Category slug.
		 * @param array  $metadata Metadata array.
		 */
		$activity = apply_filters( 'thisismyurl_shadow_activity_entry', $activity, $action, $details, $category, $metadata );

		// Get existing log
		$log = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Add new activity at the beginning
		array_unshift( $log, $activity );

		// Trim to max size
		if ( count( $log ) > self::MAX_ACTIVITIES ) {
			$log = array_slice( $log, 0, self::MAX_ACTIVITIES );
		}

		$updated = update_option( self::OPTION_NAME, $log );

		/**
		 * Fires after an activity entry is stored.
		 *
		 * @param array $activity Activity payload.
		 */
		do_action( 'thisismyurl_shadow_activity_logged', $activity );

		return $updated;
	}

	/**
	 * Get activity log with optional filters
	 *
	 * @param array $filters Optional filters (category, action, user_id, date_from, date_to)
	 * @param int   $limit Optional limit (default: 50)
	 * @param int   $offset Optional offset (default: 0)
	 * @return array Filtered activities
	 */
	public static function get_activities( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		$log = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}

		// Apply filters
		if ( ! empty( $filters ) ) {
			$log = array_filter(
				$log,
				function ( $activity ) use ( $filters ) {
					// Category filter (supports both single and array)
					if ( ! empty( $filters['category'] ) ) {
						$category = $filters['category'];
						if ( is_array( $category ) ) {
							if ( ! in_array( $activity['category'], $category, true ) ) {
								return false;
							}
						} elseif ( $activity['category'] !== $category ) {
							return false;
						}
					}

					// Categories filter (alias for category as array)
					if ( ! empty( $filters['categories'] ) && is_array( $filters['categories'] ) ) {
						if ( ! in_array( $activity['category'], $filters['categories'], true ) ) {
							return false;
						}
					}

					// Action filter (supports both single and array)
					if ( ! empty( $filters['action'] ) ) {
						$action = $filters['action'];
						if ( is_array( $action ) ) {
							if ( ! in_array( $activity['action'], $action, true ) ) {
								return false;
							}
						} elseif ( $activity['action'] !== $action ) {
							return false;
						}
					}

					// Actions filter (alias for action as array)
					if ( ! empty( $filters['actions'] ) && is_array( $filters['actions'] ) ) {
						if ( ! in_array( $activity['action'], $filters['actions'], true ) ) {
							return false;
						}
					}

					// User filter
					if ( isset( $filters['user_id'] ) && $activity['user_id'] !== $filters['user_id'] ) {
						return false;
					}

					// Date range filter
					if ( ! empty( $filters['date_from'] ) && $activity['timestamp'] < strtotime( $filters['date_from'] ) ) {
						return false;
					}
					if ( ! empty( $filters['date_to'] ) && $activity['timestamp'] > strtotime( $filters['date_to'] ) ) {
						return false;
					}

					// Search filter
					if ( ! empty( $filters['search'] ) ) {
						$search     = strtolower( $filters['search'] );
						$searchable = strtolower( $activity['details'] . ' ' . $activity['action'] );
						if ( strpos( $searchable, $search ) === false ) {
							return false;
						}
					}

					return true;
				}
			);
		}

		// Apply pagination
		$total = count( $log );
		$log   = array_slice( $log, $offset, $limit );

		return array(
			'activities' => $log,
			'total'      => $total,
			'limit'      => $limit,
			'offset'     => $offset,
		);
	}

}
