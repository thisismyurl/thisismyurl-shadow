<?php
/**
 * KPI Tracker for Shadow by Christopher Ross
 *
 * Tracks key performance indicators to prove value delivered
 *
 * @package ThisIsMyURL\Shadow
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * KPI tracking system for monitoring fixes and improvements
 */
class KPI_Tracker {
	/**
	 * Log a finding detection
	 *
	 * @param string $finding_id Finding identifier.
	 * @param string $severity   Severity level (critical, high, medium, low).
	 * @return void
	 */
	public static function log_finding_detected( $finding_id, $severity = 'medium' ) {
		$tracking = self::get_tracking_data();

		if ( ! isset( $tracking['findings_detected'] ) ) {
			$tracking['findings_detected'] = array();
		}

		$key = $finding_id . '_' . gmdate( 'Y-m-d' );
		if ( ! isset( $tracking['findings_detected'][ $key ] ) ) {
			$tracking['findings_detected'][ $key ] = array(
				'finding_id' => $finding_id,
				'severity'   => $severity,
				'date'       => gmdate( 'Y-m-d H:i:s' ),
				'count'      => 0,
			);
		}

		++$tracking['findings_detected'][ $key ]['count'];

		/**
		 * Fires when a finding is detected.
		 *
		 * @param string $finding_id Finding identifier.
		 * @param string $severity   Severity level.
		 */
		do_action( 'thisismyurl_shadow_finding_detected', $finding_id, $severity );

		self::save_tracking_data( $tracking );
	}

	/**
	 * Calculate estimated time saved with human-readable format
	 *
	 * @param int $fixes_count Number of fixes applied.
	 * @return string Formatted time saved.
	 */
	private static function format_time_saved( $fixes_count ) {
		$minutes = $fixes_count * 15; // Estimate 15 min per fix
		$hours   = intdiv( $minutes, 60 );
		$mins    = $minutes % 60;

		if ( $hours > 0 ) {
			return sprintf( '%dh %dm', $hours, $mins );
		}
		return sprintf( '%dm', $mins );
	}

	/**
	 * Count fixes by severity level
	 *
	 * @param array  $tracking Tracking data.
	 * @param string $severity Severity level (critical, high, medium, low).
	 * @return int Count of fixes.
	 */
	private static function count_fixes_by_severity( $tracking, $severity ) {
		if ( empty( $tracking['fixes_applied'] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $tracking['fixes_applied'] as $fix ) {
			$finding_severity = self::get_finding_severity( $fix['finding_id'] );
			if ( $finding_severity === $severity ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Count fixes by category
	 *
	 * @param array  $tracking Tracking data.
	 * @param string $category Category (security, performance, code_quality, etc).
	 * @return int Count of fixes.
	 */
	private static function count_fixes_by_category( $tracking, $category ) {
		if ( empty( $tracking['fixes_applied'] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $tracking['fixes_applied'] as $fix ) {
			$finding_category = self::get_finding_category( $fix['finding_id'] );
			if ( $finding_category === $category ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Get finding severity from diagnostic metadata
	 *
	 * @param string $finding_id Finding identifier.
	 * @return string Severity level.
	 */
	private static function get_finding_severity( $finding_id ) {
		$definition = self::resolve_finding_definition( (string) $finding_id );
		if ( is_array( $definition ) && ! empty( $definition['severity'] ) ) {
			return (string) $definition['severity'];
		}

		return 'medium';
	}

	/**
	 * Get finding category from diagnostic metadata
	 *
	 * @param string $finding_id Finding identifier.
	 * @return string Category.
	 */
	private static function get_finding_category( $finding_id ) {
		$definition = self::resolve_finding_definition( (string) $finding_id );
		if ( is_array( $definition ) && ! empty( $definition['family'] ) ) {
			return sanitize_key( (string) $definition['family'] );
		}

		return 'general';
	}

	/**
	 * Resolve a finding ID back to its diagnostic definition.
	 *
	 * Fix and finding events generally store the diagnostic run key, but some
	 * historic records may contain a class name or a loosely related identifier.
	 * This helper matches exact run keys first, then falls back to exact class
	 * names and conservative partial slug matches.
	 *
	 * @param string $finding_id Finding identifier or run key.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_finding_definition( string $finding_id ): ?array {
		$finding_id = sanitize_key( $finding_id );
		if ( '' === $finding_id || ! class_exists( '\ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry' ) ) {
			return null;
		}

		$definitions = \ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry::get_diagnostic_definitions();
		if ( ! is_array( $definitions ) || empty( $definitions ) ) {
			return null;
		}

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$run_key = isset( $definition['run_key'] ) ? sanitize_key( (string) $definition['run_key'] ) : '';
			if ( '' !== $run_key && $run_key === $finding_id ) {
				return $definition;
			}
		}

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$class_name  = isset( $definition['class'] ) ? strtolower( ltrim( (string) $definition['class'], '\\' ) ) : '';
			$short_class = isset( $definition['short_class'] ) ? strtolower( (string) $definition['short_class'] ) : '';
			if ( $class_name === strtolower( $finding_id ) || $short_class === strtolower( $finding_id ) ) {
				return $definition;
			}
		}

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$run_key = isset( $definition['run_key'] ) ? sanitize_key( (string) $definition['run_key'] ) : '';
			if ( '' !== $run_key && ( false !== strpos( $finding_id, $run_key ) || false !== strpos( $run_key, $finding_id ) ) ) {
				return $definition;
			}
		}

		return null;
	}

	/**
	 * Get score trend over 30 days
	 *
	 * @return array Trend data with comparison points.
	 */
	private static function get_score_trend() {
		$current_health = get_option( 'thisismyurl_shadow_health_status', array() );
		$score_history  = get_option( 'thisismyurl_shadow_score_history', array() );

		$score_today = isset( $current_health['score'] ) ? (int) $current_health['score'] : 0;

		// Find score from 30 days ago
		$cutoff_date       = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$score_30_days_ago = 0;

		if ( is_array( $score_history ) ) {
			foreach ( array_reverse( $score_history ) as $entry ) {
				if ( isset( $entry['date'] ) && $entry['date'] <= $cutoff_date ) {
					$score_30_days_ago = isset( $entry['score'] ) ? (int) $entry['score'] : 0;
					break;
				}
			}
		}

		// Calculate improvement
		$improvement            = $score_today - $score_30_days_ago;
		$improvement_percentage = $score_30_days_ago > 0 ? round( ( $improvement / $score_30_days_ago ) * 100, 1 ) : 0;

		// Ensure confidence_change is never empty or just '%'
		if ( $improvement > 0 ) {
			$confidence_change = '+' . $improvement . '%';
		} elseif ( $improvement < 0 ) {
			$confidence_change = $improvement . '%';
		} else {
			$confidence_change = '0%';
		}

		return array(
			'score_today'            => $score_today,
			'score_30_days_ago'      => $score_30_days_ago,
			'improvement_percentage' => $improvement_percentage,
			'confidence_change'      => $confidence_change,
		);
	}

	/**
	 * Get all tracking data
	 *
	 * @return array Tracking data.
	 */
	private static function get_tracking_data() {
		$data = get_option( 'thisismyurl_shadow_kpi_tracking', array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Save tracking data
	 *
	 * @param array $data Data to save.
	 * @return void
	 */
	private static function save_tracking_data( $data ) {
		// Keep only last 90 days of data
		$cutoff_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );

		if ( ! empty( $data['findings_detected'] ) ) {
			$data['findings_detected'] = array_filter(
				$data['findings_detected'],
				function ( $item ) use ( $cutoff_date ) {
					return $item['date'] >= $cutoff_date;
				}
			);
		}

		if ( ! empty( $data['fixes_applied'] ) ) {
			$data['fixes_applied'] = array_filter(
				$data['fixes_applied'],
				function ( $item ) use ( $cutoff_date ) {
					return $item['date'] >= $cutoff_date;
				}
			);
		}

		if ( ! empty( $data['findings_dismissed'] ) ) {
			$data['findings_dismissed'] = array_filter(
				$data['findings_dismissed'],
				function ( $item ) use ( $cutoff_date ) {
					return $item['date'] >= $cutoff_date;
				}
			);
		}

		update_option( 'thisismyurl_shadow_kpi_tracking', $data );
	}

	/**
	 * Reset all KPI data (useful for testing)
	 *
	 * @return void
	 */
	public static function reset() {
		delete_option( 'thisismyurl_shadow_kpi_tracking' );
	}

	/**
	 * Record a treatment application (Phase 3: KPI Wiring)
	 *
	 * @param string $treatment_id Treatment identifier.
	 * @param int    $time_saved_minutes Time saved by applying treatment.
	 * @return void
	 */
	public static function record_treatment_applied( $treatment_id, $time_saved_minutes = 15 ) {
		$tracking = self::get_tracking_data();

		if ( ! isset( $tracking['treatments_applied'] ) ) {
			$tracking['treatments_applied'] = array();
		}

		$tracking['treatments_applied'][] = array(
			'treatment_id' => $treatment_id,
			'time_saved'   => $time_saved_minutes,
			'date'         => gmdate( 'Y-m-d H:i:s' ),
			'user_id'      => get_current_user_id(),
		);

		/**
		 * Fires when a treatment is recorded in KPI tracking.
		 *
		 * @param string $treatment_id Treatment identifier.
		 * @param int    $time_saved_minutes Time saved.
		 */
		do_action( 'thisismyurl_shadow_treatment_kpi_recorded', $treatment_id, $time_saved_minutes );

		self::save_tracking_data( $tracking );
	}

	/**
	 * Record a diagnostic run (Phase 3: KPI Wiring)
	 *
	 * @param string $diagnostic_id Diagnostic identifier.
	 * @param bool   $success Whether diagnostic ran successfully.
	 * @return void
	 */
	public static function record_diagnostic_run( $diagnostic_id, $success = true ) {
		$tracking = self::get_tracking_data();

		if ( ! isset( $tracking['diagnostics_run'] ) ) {
			$tracking['diagnostics_run'] = array();
		}

		$tracking['diagnostics_run'][] = array(
			'diagnostic_id' => $diagnostic_id,
			'success'       => $success,
			'date'          => gmdate( 'Y-m-d H:i:s' ),
		);

		/**
		 * Fires when a diagnostic run is recorded.
		 *
		 * @param string $diagnostic_id Diagnostic identifier.
		 * @param bool   $success Success status.
		 */
		do_action( 'thisismyurl_shadow_diagnostic_kpi_recorded', $diagnostic_id, $success );

		self::save_tracking_data( $tracking );
	}

	/**
	 * Record a finding as resolved (Phase 3: KPI Wiring)
	 *
	 * @param string $finding_id Finding identifier.
	 * @param string $resolution_type Type of resolution (fixed, ignored, delegated).
	 * @return void
	 */
	public static function record_finding_resolved( $finding_id, $resolution_type = 'fixed' ) {
		$tracking = self::get_tracking_data();

		if ( ! isset( $tracking['findings_resolved'] ) ) {
			$tracking['findings_resolved'] = array();
		}

		$tracking['findings_resolved'][] = array(
			'finding_id'      => $finding_id,
			'resolution_type' => $resolution_type,
			'date'            => gmdate( 'Y-m-d H:i:s' ),
			'user_id'         => get_current_user_id(),
		);

		/**
		 * Fires when a finding is marked resolved.
		 *
		 * @param string $finding_id Finding identifier.
		 * @param string $resolution_type Resolution type.
		 */
		do_action( 'thisismyurl_shadow_finding_resolved', $finding_id, $resolution_type );

		self::save_tracking_data( $tracking );
	}
}
