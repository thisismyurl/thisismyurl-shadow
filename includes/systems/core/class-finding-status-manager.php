<?php
/**
 * Finding Status Manager
 *
 * Manages finding disposition (ignore, manual fix, automate)
 *
 * @package ThisIsMyURL\Shadow
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages finding status and organization
 */
class Finding_Status_Manager {
	// Finding status constants
	const STATUS_DETECTED  = 'detected';    // New finding
	const STATUS_IGNORED   = 'ignored';     // User chose to ignore
	const STATUS_MANUAL    = 'manual';      // User will fix manually
	const STATUS_AUTOMATED = 'automated';   // Automated fix enabled
	const STATUS_FIXED     = 'fixed';       // Already fixed

	/**
	 * Set finding status
	 *
	 * @param string $finding_id Finding identifier.
	 * @param string $status     New status.
	 * @return bool Success.
	 */
	public static function set_finding_status( $finding_id, $status ) {
		if ( ! self::is_valid_status( $status ) ) {
			return false;
		}


		$old_status = self::get_finding_status( $finding_id );
		$status_map = get_option( 'thisismyurl_shadow_finding_status_map', array() );

		// Remove from all statuses
		foreach ( array_keys( $status_map ) as $key ) {
			if ( is_array( $status_map[ $key ] ) ) {
				$status_map[ $key ] = array_filter(
					$status_map[ $key ],
					function ( $item ) use ( $finding_id ) {
						return $item['id'] !== $finding_id;
					}
				);
			}
		}

		// Add to new status
		if ( ! isset( $status_map[ $status ] ) ) {
			$status_map[ $status ] = array();
		}

		$status_map[ $status ][] = array(
			'id'        => $finding_id,
			'timestamp' => time(),
			'notes'     => '',
		);

		$result = update_option( 'thisismyurl_shadow_finding_status_map', $status_map );

		if ( $result ) {
			/**
			 * Fires when a finding status is changed.
			 *
			 * @param string      $finding_id Finding identifier.
			 * @param string      $status     New status.
			 * @param string|null $old_status Previous status (null if first time).
			 */
			do_action( 'thisismyurl_shadow_finding_status_changed', $finding_id, $status, $old_status );
		}

		return $result;
	}

	/**
	 * Get status for a finding
	 *
	 * @param string $finding_id Finding identifier.
	 * @return string|null Status or null if not found.
	 */
	public static function get_finding_status( $finding_id ) {
		$status_map = get_option( 'thisismyurl_shadow_finding_status_map', array() );

		foreach ( $status_map as $status => $findings ) {
			if ( is_array( $findings ) ) {
				foreach ( $findings as $item ) {
					if ( $item['id'] === $finding_id ) {
						return $status;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Check if status is valid
	 *
	 * @param string $status Status to validate.
	 * @return bool True if valid.
	 */
	private static function is_valid_status( $status ) {
		return in_array(
			$status,
			array(
				self::STATUS_DETECTED,
				self::STATUS_IGNORED,
				self::STATUS_MANUAL,
				self::STATUS_AUTOMATED,
				self::STATUS_FIXED,
			),
			true
		);
	}

	/**
	 * Get statistics on finding statuses
	 *
	 * @return array Statistics.
	 */
	public static function get_stats() {
		$status_map = get_option( 'thisismyurl_shadow_finding_status_map', array() );

		$stats = array(
			'detected'  => 0,
			'ignored'   => 0,
			'manual'    => 0,
			'automated' => 0,
			'fixed'     => 0,
			'total'     => 0,
		);

		foreach ( $status_map as $status => $findings ) {
			if ( is_array( $findings ) ) {
				$count = count( $findings );
				$key   = str_replace( 'thisismyurl_shadow_status_', '', $status );
				if ( isset( $stats[ $key ] ) ) {
					$stats[ $key ]   = $count;
					$stats['total'] += $count;
				}
			}
		}

		return $stats;
	}
}
