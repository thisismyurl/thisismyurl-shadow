<?php
/**
 * AJAX Handler: Deep Scan (Comprehensive Health Check)
 *
 * Runs ALL registered diagnostics for complete site health analysis.
 * Includes expensive checks like malware scanning, link validation, SEO audit.
 * Designed to run when user has time (admin doesn't need instant results).
 *
 * **Scope:**
 * - All 50+ registered diagnostics
 * - Includes slow checks: malware, link validation, performance profiles
 * - Excludes only manually-disabled diagnostics
 * - Typically takes 5-15 minutes depending on site size
 *
 * **Server Awareness:**
 * - Checks server capacity before heavy operations
 * - Throttles if CPU/memory trending high
 * - Can be interrupted by user (returns partial results)
 * - Saves progress for resumption
 *
 * **Philosophy Alignment:**
 * - #9 (Show Value): Comprehensive findings prove plugin value
 * - #1 (Helpful Neighbor): "Here's EVERYTHING we found"
 * - #8 (Inspire Confidence): Thoroughness builds trust
 *
 * @package ThisIsMyURL\Shadow
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Admin\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged

use ThisIsMyURL\Shadow\Core\AJAX_Handler_Base;
use ThisIsMyURL\Shadow\Core\Options_Manager;
use ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry;
use ThisIsMyURL\Shadow\Core\Activity_Logger;
use ThisIsMyURL\Shadow\Core\Error_Handler;
use ThisIsMyURL\Shadow\Core\KPI_Tracker;

/**
 * Deep_Scan_Handler Class
 *
 * @since 0.6095
 * @package ThisIsMyURL\Shadow
 */
class Deep_Scan_Handler extends AJAX_Handler_Base {
	/**
	 * Guard against duplicate hook registration.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Transient key storing live deep scan progress metadata.
	 */
	const PROGRESS_TRANSIENT_KEY = 'thisismyurl_shadow_scan_progress_state';

	/**
	 * Transient key storing queued scan session data between batch requests.
	 */
	const SESSION_TRANSIENT_KEY = 'thisismyurl_shadow_scan_session_state';

	/**
	 * Transient key preventing overlapping batch execution requests.
	 */
	const REQUEST_LOCK_TRANSIENT_KEY = 'thisismyurl_shadow_scan_batch_running';

	/**
	 * Progress transient TTL in seconds.
	 */
	const PROGRESS_TRANSIENT_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Per-request execution lock TTL in seconds.
	 */
	const REQUEST_LOCK_TTL = 2 * MINUTE_IN_SECONDS;

	/**
	 * Default number of diagnostics to process per deep scan request.
	 */
	const DEFAULT_BATCH_SIZE = 8;


	/**
	 * Register AJAX hook
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		add_action( 'wp_ajax_thisismyurl_shadow_deep_scan', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_thisismyurl_shadow_deep_scan_status', array( __CLASS__, 'handle_status' ) );
		self::$registered = true;
	}

	/**
	 * Handle deep scan status polling.
	 *
	 * @return void
	 */
	public static function handle_status(): void {
		self::verify_request( 'thisismyurl_shadow_scan_nonce', 'manage_options' );

		$scan_lock_state = self::get_scan_lock_state();
		if ( ! empty( $scan_lock_state ) ) {
			$heartbeat_at = (int) ( $scan_lock_state['heartbeat_at'] ?? 0 );
			if ( $heartbeat_at > 0 && ( time() - $heartbeat_at ) >= ( 10 * MINUTE_IN_SECONDS ) ) {
				self::clear_scan_state();
				$scan_lock_state = array();
			}
		}

		$request_lock = get_transient( self::REQUEST_LOCK_TRANSIENT_KEY );
		if ( false !== $request_lock && is_numeric( $request_lock ) && ( time() - (int) $request_lock ) >= self::REQUEST_LOCK_TTL ) {
			delete_transient( self::REQUEST_LOCK_TRANSIENT_KEY );
			$request_lock = false;
		}

		$progress_state = get_transient( self::PROGRESS_TRANSIENT_KEY );
		$progress_state = is_array( $progress_state ) ? $progress_state : array();
		$session_state  = self::get_scan_session_state();
		$cursor_fields  = self::build_scan_cursor_fields( $progress_state, $session_state );
		$stalled_message = '';

		if ( ! empty( $scan_lock_state ) ) {
			$progress_phase = isset( $progress_state['phase'] ) ? (string) $progress_state['phase'] : '';
			$progress_updated_at = isset( $progress_state['updated_at'] ) ? (int) $progress_state['updated_at'] : 0;
			$progress_completed = isset( $progress_state['completed'] ) ? (int) $progress_state['completed'] : 0;

			// Recover automatically if startup stalled before diagnostics began.
			if ( 'starting' === $progress_phase && $progress_updated_at > 0 && ( time() - $progress_updated_at ) > 20 && $progress_completed <= 0 ) {
				self::clear_scan_state();
				$scan_lock_state = array();
				$progress_state = array();
				$stalled_message = __( 'Scan startup stalled before diagnostics began. This usually means the server ran out of memory during startup.', 'thisismyurl-shadow' );
			}
		}

		$running           = ! empty( $scan_lock_state );
		$started_at        = $running ? (int) ( $scan_lock_state['started_at'] ?? 0 ) : 0;
		$elapsed_seconds   = $started_at > 0 ? max( 0, time() - $started_at ) : 0;
		$estimated_seconds = 10 * MINUTE_IN_SECONDS;
		$completed_items   = isset( $progress_state['completed'] ) ? (int) $progress_state['completed'] : 0;
		$total_items       = isset( $progress_state['total'] ) ? (int) $progress_state['total'] : 0;
		$dashboard_summary = array();
		$resume_available  = $running && false !== get_transient( self::SESSION_TRANSIENT_KEY ) && false === $request_lock;

		if ( $running && $total_items > 0 ) {
			$progress_percent = min( 99, (int) floor( ( max( 0, $completed_items ) / max( 1, $total_items ) ) * 100 ) );
			if ( isset( $progress_state['dashboard_summary'] ) && is_array( $progress_state['dashboard_summary'] ) ) {
				$dashboard_summary = $progress_state['dashboard_summary'];
			}
		} elseif ( $running ) {
			$progress_percent = min( 99, (int) floor( ( $elapsed_seconds / $estimated_seconds ) * 100 ) );
			if ( isset( $progress_state['dashboard_summary'] ) && is_array( $progress_state['dashboard_summary'] ) ) {
				$dashboard_summary = $progress_state['dashboard_summary'];
			}
		} else {
			$progress_percent = 100;
			$dashboard_summary = self::build_dashboard_summary();
		}

		self::send_success(
			array(
				'running'           => $running,
				'started_at'        => $started_at,
				'elapsed_seconds'   => $elapsed_seconds,
				'estimated_seconds' => $estimated_seconds,
				'progress_percent'  => $progress_percent,
				'current_class'     => $cursor_fields['current_class'],
				'current_slug'      => $cursor_fields['current_slug'],
				'current_label'     => $cursor_fields['current_label'],
				'display_label'     => $cursor_fields['current_label'],
				'current_label_source' => $cursor_fields['current_label_source'],
				'completed_items'   => $completed_items,
				'total_items'       => $total_items,
				'dashboard_summary' => $dashboard_summary,
				'request_in_progress' => false !== $request_lock,
				'resume_available'  => $resume_available,
				'stalled'           => '' !== $stalled_message,
				'stalled_message'   => $stalled_message,
			)
		);
	}

	/**
	 * Handle deep scan AJAX request
	 */
	public static function handle(): void {
		try {
			// Verify security
			self::verify_request( 'thisismyurl_shadow_scan_nonce', 'manage_options' );

			// Get scan mode: 'now' or 'schedule'
			$mode = self::get_post_param( 'mode', 'text', 'now', true );

			if ( $mode === 'schedule' ) {
				// Schedule deep scan for weekly execution
				$result = self::schedule_deep_scan();
			} else {
				// Run immediately (with warning already acknowledged)
				$result = self::run_deep_scan();
			}

			if ( isset( $result['success'] ) && false === $result['success'] ) {
				self::send_error(
					isset( $result['message'] ) ? (string) $result['message'] : __( 'Deep scan failed.', 'thisismyurl-shadow' ),
					$result
				);
				wp_die();
			}

			self::send_success( $result );
			wp_die();
		} catch ( \Throwable $e ) {
			Error_Handler::log_error( 'Deep scan AJAX request failed', $e );
			self::send_error( __( 'Deep scan failed.', 'thisismyurl-shadow' ) );
			wp_die();
		}
	}

	/**
	 * Run deep scan immediately
	 *
	 * MURPHY-SAFE: Prevents concurrent scans that could overwhelm server.
	 *
	 * @return array Result data
	 */
	private static function run_deep_scan(): array {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'memory_limit', '2048M' ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed, Squiz.PHP.DiscouragedFunctions.Discouraged -- Scoped to the deep-scan execution method; a deep scan can exceed the default memory limit on large sites.
		}

		$request_lock = get_transient( self::REQUEST_LOCK_TRANSIENT_KEY );
		if ( false !== $request_lock ) {
			$progress_state = get_transient( self::PROGRESS_TRANSIENT_KEY );
			$progress_state = is_array( $progress_state ) ? $progress_state : array();
			$session_state  = self::get_scan_session_state();
			$cursor_fields  = self::build_scan_cursor_fields( $progress_state, $session_state );
			$scan_lock_state = self::get_scan_lock_state();

			return array(
				'success'         => false,
				'message'         => __( 'A scan batch is already running. Waiting for it to finish.', 'thisismyurl-shadow' ),
				'started_at'      => (int) ( $scan_lock_state['started_at'] ?? 0 ),
				'locked'          => true,
				'current_class'   => $cursor_fields['current_class'],
				'current_slug'    => $cursor_fields['current_slug'],
				'current_label'   => $cursor_fields['current_label'],
				'display_label'   => $cursor_fields['current_label'],
				'current_label_source' => $cursor_fields['current_label_source'],
				'completed_items' => isset( $progress_state['completed'] ) ? (int) $progress_state['completed'] : 0,
				'total_items'     => isset( $progress_state['total'] ) ? (int) $progress_state['total'] : 0,
				'resume_available' => false,
			);
		}

		$session = self::get_scan_session_state();
		if ( ! self::is_valid_scan_session( $session ) ) {
			$session = self::initialize_scan_session();
		}

		if ( empty( $session['remaining'] ) ) {
			return self::finalize_scan_session( $session );
		}

		set_transient( self::REQUEST_LOCK_TRANSIENT_KEY, time(), self::REQUEST_LOCK_TTL );

		try {
			$session = self::process_scan_batch( $session );
		} catch ( \Throwable $e ) {
			self::clear_scan_state();
			Error_Handler::log_error( 'Deep scan failed', $e );

			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		} finally {
			delete_transient( self::REQUEST_LOCK_TRANSIENT_KEY );
		}

		if ( empty( $session['remaining'] ) ) {
			return self::finalize_scan_session( $session );
		}

		// Read the label the after_hook already wrote to the progress transient for the
		// last diagnostic in this batch. Do this BEFORE overwriting the transient below.
		$fresh_progress   = get_transient( self::PROGRESS_TRANSIENT_KEY );
		$batch_last_label = is_array( $fresh_progress )
			&& isset( $fresh_progress['current_label'] )
			&& '' !== (string) $fresh_progress['current_label']
			? (string) $fresh_progress['current_label']
			: '';

		if ( '' === $batch_last_label ) {
			$batch_last_label = self::resolve_current_label( $fresh_progress, $session );
		}
		$batch_last_class = self::resolve_current_class( $fresh_progress, $session );
		$batch_cursor     = self::resolve_scan_cursor( is_array( $fresh_progress ) ? $fresh_progress : array(), $session );

		self::update_scan_session_state( $session );
		self::update_scan_progress_state(
			self::build_progress_state(
				$session,
				array(
					'phase'         => 'waiting',
					'current_class' => $batch_last_class,
					'current_slug'  => '',
					'current_label' => $batch_last_label,
				)
			)
		);

		return array(
			'mode'              => 'now',
			'running'           => true,
			'continue'          => true,
			'complete'          => false,
			'completed'         => (int) $session['completed'],
			'total'             => (int) $session['total'],
			'findings_count'    => count( $session['findings'] ),
			'progress_percent'  => self::calculate_progress_percent( (int) $session['completed'], (int) $session['total'] ),
			'current_class'     => $batch_last_class,
			'current_slug'      => $batch_cursor['slug'],
			'current_label'     => $batch_last_label,
			'display_label'     => $batch_last_label,
			'current_label_source' => $batch_cursor['source'],
			'dashboard_summary' => $session['dashboard_summary'],
			'message'           => sprintf(
						/* translators: 1: completed diagnostics count, 2: total diagnostics count. */
				__( 'Processed %1$d of %2$d diagnostics. Continuing…', 'thisismyurl-shadow' ),
				(int) $session['completed'],
				(int) $session['total']
			),
		);
	}

	/**
	 * Initialize a resumable deep scan session.
	 *
	 * @return array<string,mixed>
	 */
	private static function initialize_scan_session(): array {
		$started_at       = time();
		$all_diagnostics  = Diagnostic_Registry::get_deep_scan_diagnostics();
		$enabled          = self::filter_enabled_diagnostics( $all_diagnostics );
		$disabled_count   = max( 0, count( $all_diagnostics ) - count( $enabled ) );
		$family_totals    = self::build_family_totals();
		$diagnostic_families = self::build_diagnostic_family_map();
		$dashboard_summary = array(
			'total'    => count( $all_diagnostics ),
			'passed'   => 0,
			'failed'   => 0,
			'disabled' => $disabled_count,
			'active'   => count( $enabled ),
			'pending'  => count( $enabled ),
			'families' => self::build_family_summary_from_totals( $family_totals ),
			'score'    => count( $enabled ) > 0 ? 0 : 100,
		);

		$session = array(
			'started_at'        => $started_at,
			'all_total'         => count( $all_diagnostics ),
			'total'             => count( $enabled ),
			'disabled_total'    => $disabled_count,
			'completed'         => 0,
			'remaining'         => array_values( $enabled ),
			'requested'         => array(),
			'executed'          => array(),
			'results'           => array(),
			'findings'          => array(),
			'family_totals'     => $family_totals,
			'diagnostic_families' => $diagnostic_families,
			'dashboard_summary' => $dashboard_summary,
		);

		$next_class = ! empty( $session['remaining'] ) ? (string) reset( $session['remaining'] ) : '';

		self::refresh_scan_lock( $started_at );
		update_option( 'thisismyurl_shadow_last_deep_scan', $started_at );
		self::update_scan_session_state( $session );
		self::update_scan_progress_state(
			self::build_progress_state(
				$session,
				array(
					'phase'         => 'starting',
					'current_class' => $next_class,
					'current_slug'  => '',
					'current_label' => '' !== $next_class ? self::build_scan_label( $next_class, '' ) : __( 'Preparing diagnostics…', 'thisismyurl-shadow' ),
				)
			)
		);

		return $session;
	}

	/**
	 * Process the next deep scan batch.
	 *
	 * @param array<string,mixed> $session Scan session state.
	 * @return array<string,mixed>
	 */
	private static function process_scan_batch( array $session ): array {
		$batch_size    = self::get_scan_batch_size();
		$batch_classes = array_slice( (array) $session['remaining'], 0, $batch_size );
		$session['remaining'] = array_values( array_slice( (array) $session['remaining'], $batch_size ) );

		$completed_items = (int) ( $session['completed'] ?? 0 );
		$total_items     = (int) ( $session['total'] ?? 0 );
		$batch_summary   = is_array( $session['dashboard_summary'] ?? null ) ? $session['dashboard_summary'] : array();
		$diagnostic_families = is_array( $session['diagnostic_families'] ?? null ) ? $session['diagnostic_families'] : array();

		$before_hook = static function ( $class, $slug ) use ( &$completed_items, $total_items, &$batch_summary ): void {
			self::update_scan_progress_state(
				array(
					'phase'             => 'running',
					'current_class'     => (string) $class,
					'current_slug'      => (string) $slug,
					'current_label'     => self::build_scan_label( (string) $class, (string) $slug ),
					'completed'         => $completed_items,
					'total'             => $total_items,
					'dashboard_summary' => $batch_summary,
					'updated_at'        => time(),
				)
			);
		};

		$after_hook = static function ( $class, $slug, $_finding = null ) use ( &$completed_items, $total_items, &$batch_summary, $diagnostic_families ): void {
			$completed_items++;
			$family = isset( $diagnostic_families[ (string) $class ] ) ? sanitize_key( (string) $diagnostic_families[ (string) $class ] ) : 'other';
			if ( '' === $family ) {
				$family = 'other';
			}
			if ( ! isset( $batch_summary['families'] ) || ! is_array( $batch_summary['families'] ) ) {
				$batch_summary['families'] = array();
			}
			if ( ! isset( $batch_summary['families'][ $family ] ) || ! is_array( $batch_summary['families'][ $family ] ) ) {
				$batch_summary['families'][ $family ] = array(
					'total'    => 0,
					'disabled' => 0,
					'active'   => 0,
					'passed'   => 0,
					'failed'   => 0,
					'pending'  => 0,
					'score'    => 100,
				);
			}

			if ( null === $_finding ) {
				$batch_summary['passed'] = (int) ( $batch_summary['passed'] ?? 0 ) + 1;
				$batch_summary['families'][ $family ]['passed'] = (int) ( $batch_summary['families'][ $family ]['passed'] ?? 0 ) + 1;
			} else {
				$batch_summary['failed'] = (int) ( $batch_summary['failed'] ?? 0 ) + 1;
				$batch_summary['families'][ $family ]['failed'] = (int) ( $batch_summary['families'][ $family ]['failed'] ?? 0 ) + 1;
			}

			$batch_summary['pending'] = max( 0, (int) ( $batch_summary['active'] ?? 0 ) - (int) ( $batch_summary['passed'] ?? 0 ) - (int) ( $batch_summary['failed'] ?? 0 ) );
			$family_active = (int) ( $batch_summary['families'][ $family ]['active'] ?? 0 );
			$family_passed = (int) ( $batch_summary['families'][ $family ]['passed'] ?? 0 );
			$family_failed = (int) ( $batch_summary['families'][ $family ]['failed'] ?? 0 );
			$batch_summary['families'][ $family ]['pending'] = max( 0, $family_active - $family_passed - $family_failed );
			$batch_summary['families'][ $family ]['score'] = $family_active > 0 ? (int) round( ( $family_passed / $family_active ) * 100 ) : 100;

			$active_total = max( 1, (int) ( $batch_summary['active'] ?? 0 ) );
			$batch_summary['score'] = (int) round( ( (int) $batch_summary['passed'] / $active_total ) * 100 );

			self::update_scan_progress_state(
				array(
					'phase'             => 'running',
					'current_class'     => (string) $class,
					'current_slug'      => (string) $slug,
					'current_label'     => self::build_scan_label( (string) $class, (string) $slug ),
					'completed'         => $completed_items,
					'total'             => $total_items,
					'dashboard_summary' => $batch_summary,
					'updated_at'        => time(),
				)
			);
		};

		add_action( 'thisismyurl_shadow_before_diagnostic_check', $before_hook, 10, 2 );
		add_action( 'thisismyurl_shadow_after_diagnostic_check', $after_hook, 10, 3 );

		try {
			$batch_findings = Diagnostic_Registry::run_checks_for_classes( $batch_classes );
		} finally {
			remove_action( 'thisismyurl_shadow_before_diagnostic_check', $before_hook, 10 );
			remove_action( 'thisismyurl_shadow_after_diagnostic_check', $after_hook, 10 );
		}

		$scan_stats = Diagnostic_Registry::get_last_run_stats();
		$session['requested'] = self::merge_unique_strings(
			(array) ( $session['requested'] ?? array() ),
			isset( $scan_stats['requested'] ) && is_array( $scan_stats['requested'] ) ? $scan_stats['requested'] : array()
		);
		$session['executed'] = self::merge_unique_strings(
			(array) ( $session['executed'] ?? array() ),
			isset( $scan_stats['executed'] ) && is_array( $scan_stats['executed'] ) ? $scan_stats['executed'] : array()
		);
		$session['results'] = array_merge(
			is_array( $session['results'] ?? null ) ? $session['results'] : array(),
			isset( $scan_stats['results'] ) && is_array( $scan_stats['results'] ) ? $scan_stats['results'] : array()
		);
		$session['findings'] = self::merge_findings(
			(array) ( $session['findings'] ?? array() ),
			is_array( $batch_findings ) ? $batch_findings : array()
		);
		$session['completed'] = count( $session['results'] );
		$session['dashboard_summary'] = self::build_session_dashboard_summary( $session );
		$session['last_batch_class'] = ! empty( $batch_classes ) ? (string) end( (array) $batch_classes ) : '';

		self::refresh_scan_lock( (int) ( $session['started_at'] ?? time() ) );

		return $session;
	}

	/**
	 * Finalize a completed deep scan session and persist results.
	 *
	 * @param array<string,mixed> $session Scan session state.
	 * @return array<string,mixed>
	 */
	private static function finalize_scan_session( array $session ): array {
		$findings               = array_values( is_array( $session['findings'] ?? null ) ? $session['findings'] : array() );
		$requested_diagnostics  = self::merge_unique_strings( array(), (array) ( $session['requested'] ?? array() ) );
		$executed_diagnostics   = self::merge_unique_strings( array(), (array) ( $session['executed'] ?? array() ) );
		$diagnostic_results     = is_array( $session['results'] ?? null ) ? $session['results'] : array();
		$total                  = (int) ( $session['total'] ?? count( $requested_diagnostics ) );
		$completed              = count( $diagnostic_results );
		$skipped                = max( 0, $total - $completed );
		$progress_by_category   = array();
		$findings_by_category   = array();

		foreach ( $findings as $finding ) {
			$category = isset( $finding['category'] ) ? (string) $finding['category'] : 'other';

			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Activity_Logger' ) ) {
				Activity_Logger::log(
					'diagnostic_finding',
					sprintf( 'Found issue: %s', $finding['title'] ?? $finding['id'] ?? 'Unknown' ),
					$category,
					array(
						'finding_id' => $finding['id'] ?? '',
						'scan_type'  => 'deep',
					)
				);
			}

			$severity = isset( $finding['severity'] ) ? (string) $finding['severity'] : 'medium';
			KPI_Tracker::log_finding_detected( $finding['id'] ?? 'unknown', $severity );

			if ( ! isset( $progress_by_category[ $category ] ) ) {
				$progress_by_category[ $category ] = array(
					'completed' => 0,
					'total'     => 0,
					'findings'  => 0,
				);
				$findings_by_category[ $category ] = array();
			}

			$progress_by_category[ $category ]['findings']++;
			$findings_by_category[ $category ][] = $finding['title'] ?? $finding['id'] ?? 'Unknown';
		}

		if ( $completed > 0 && 0 === count( $findings ) ) {
			$progress_by_category['clean'] = array(
				'completed' => $completed,
				'total'     => $total,
				'findings'  => 0,
			);
		}

		$previous_findings = Options_Manager::get_array( 'thisismyurl_shadow_site_findings', array() );
		$previous_findings = is_array( $previous_findings ) ? $previous_findings : array();
		$previous_ids      = array_keys( $previous_findings );
		$indexed_findings  = self::index_findings_by_id( $findings );
		$current_ids       = array_keys( $indexed_findings );
		$resolved_ids      = array_diff( $previous_ids, $current_ids );
		$resolved_count    = 0;

		foreach ( $resolved_ids as $resolved_id ) {
			$stored_finding = $previous_findings[ $resolved_id ] ?? array();
			$resolved_count++;

			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Activity_Logger' ) ) {
				Activity_Logger::log(
					'finding_resolved',
					sprintf( 'Issue resolved: %s', $stored_finding['title'] ?? $resolved_id ),
					$stored_finding['category'] ?? 'other',
					array( 'finding_id' => $resolved_id )
				);
			}
		}

		if ( function_exists( 'thisismyurl_shadow_store_gauge_data' ) ) {
			\thisismyurl_shadow_store_gauge_data( array_values( $indexed_findings ) );
		} else {
			update_option( 'thisismyurl_shadow_site_findings', $indexed_findings );
		}

		$completed_at = time();
		update_option( 'thisismyurl_shadow_last_quick_checks', $completed_at );
		if ( function_exists( 'thisismyurl_shadow_record_diagnostic_run_coverage' ) ) {
			\thisismyurl_shadow_record_diagnostic_run_coverage( $executed_diagnostics, $completed_at );
		}
		if ( function_exists( 'thisismyurl_shadow_record_diagnostic_test_states' ) ) {
			\thisismyurl_shadow_record_diagnostic_test_states( $diagnostic_results, $completed_at );
		}

		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Activity_Logger' ) ) {
			$activity_details = sprintf(
				'Deep Scan: Ran %d diagnostics, found %d issues, resolved %d, %d categories affected',
				$completed,
				count( $findings ),
				$resolved_count,
				count( $findings_by_category )
			);

			Activity_Logger::log(
				'scan_completed',
				$activity_details,
				'security',
				array(
					'scan_type'            => 'deep_scan',
					'warning_acknowledged' => true,
					'total_diagnostics'    => $total,
					'completed'            => $completed,
					'skipped'              => $skipped,
					'findings_count'       => count( $findings ),
					'resolved_count'       => $resolved_count,
					'findings_by_category' => $findings_by_category,
					'categories_affected'  => array_keys( $findings_by_category ),
					'run_by_user'          => wp_get_current_user()->display_name,
				)
			);
		}

		self::clear_scan_state();

		return array(
			'mode'                 => 'now',
			'running'              => false,
			'continue'             => false,
			'complete'             => true,
			'completed'            => $completed,
			'total'                => $total,
			'skipped'              => $skipped,
			'findings_count'       => count( $findings ),
			'progress_by_category' => $progress_by_category,
			'findings_by_category' => $findings_by_category,
			'dashboard_summary'    => self::build_dashboard_summary(),
			'message'              => sprintf(
						/* translators: 1: findings count, 2: completed diagnostics count, 3: affected categories count. */
				__( 'Deep Scan completed. Found %1$d findings from %2$d diagnostics (%3$d categories affected).', 'thisismyurl-shadow' ),
				count( $findings ),
				$completed,
				count( $findings_by_category )
			),
		);
	}

	/**
	 * Persist scan progress metadata for live UI polling.
	 *
	 * @param array<string,mixed> $state Progress payload.
	 * @return void
	 */
	private static function update_scan_progress_state( array $state ): void {
		set_transient( self::PROGRESS_TRANSIENT_KEY, $state, self::PROGRESS_TRANSIENT_TTL );
	}

	/**
	 * Persist scan session metadata between batch requests.
	 *
	 * @param array<string,mixed> $state Session payload.
	 * @return void
	 */
	private static function update_scan_session_state( array $state ): void {
		set_transient( self::SESSION_TRANSIENT_KEY, $state, self::PROGRESS_TRANSIENT_TTL );
	}

	/**
	 * Read stored scan session metadata.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_scan_session_state(): array {
		$state = get_transient( self::SESSION_TRANSIENT_KEY );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Check whether the stored session payload is usable.
	 *
	 * @param array<string,mixed> $session Session payload.
	 * @return bool
	 */
	private static function is_valid_scan_session( array $session ): bool {
		return isset( $session['started_at'], $session['remaining'], $session['results'] );
	}

	/**
	 * Clear all scan-related transients.
	 *
	 * @return void
	 */
	private static function clear_scan_state(): void {
		delete_transient( 'thisismyurl_shadow_scan_running' );
		delete_transient( self::PROGRESS_TRANSIENT_KEY );
		delete_transient( self::SESSION_TRANSIENT_KEY );
		delete_transient( self::REQUEST_LOCK_TRANSIENT_KEY );
	}

	/**
	 * Retrieve the current scan lock state.
	 *
	 * @return array<string,int>
	 */
	private static function get_scan_lock_state(): array {
		$scan_lock = get_transient( 'thisismyurl_shadow_scan_running' );

		if ( is_array( $scan_lock ) ) {
			return array(
				'started_at'  => (int) ( $scan_lock['started_at'] ?? 0 ),
				'heartbeat_at' => (int) ( $scan_lock['heartbeat_at'] ?? 0 ),
			);
		}

		if ( is_numeric( $scan_lock ) ) {
			return array(
				'started_at'  => (int) $scan_lock,
				'heartbeat_at' => (int) $scan_lock,
			);
		}

		return array();
	}

	/**
	 * Refresh the deep scan session lock heartbeat.
	 *
	 * @param int $started_at Scan start timestamp.
	 * @return void
	 */
	private static function refresh_scan_lock( int $started_at ): void {
		set_transient(
			'thisismyurl_shadow_scan_running',
			array(
				'started_at'  => $started_at,
				'heartbeat_at' => time(),
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Determine the number of diagnostics to process in each deep scan batch.
	 *
	 * @return int
	 */
	private static function get_scan_batch_size(): int {
		$batch_size = (int) apply_filters( 'thisismyurl_shadow_deep_scan_batch_size', self::DEFAULT_BATCH_SIZE );

		return max( 1, $batch_size );
	}

	/**
	 * Filter out diagnostics disabled by the user.
	 *
	 * @param array<int,string> $diagnostics Diagnostic class names.
	 * @return array<int,string>
	 */
	private static function filter_enabled_diagnostics( array $diagnostics ): array {
		$disabled = get_option( 'thisismyurl_shadow_disabled_diagnostic_classes', array() );
		$disabled = is_array( $disabled ) ? array_map( 'strval', $disabled ) : array();
		$disabled = array_map( array( __CLASS__, 'normalize_diagnostic_class' ), $disabled );

		return array_values(
			array_filter(
				$diagnostics,
				static function ( $class_name ) use ( $disabled ): bool {
					return ! in_array( self::normalize_diagnostic_class( (string) $class_name ), $disabled, true );
				}
			)
		);
	}

	/**
	 * Normalize a diagnostic class name for comparisons.
	 *
	 * @param string $class_name Diagnostic class name.
	 * @return string
	 */
	private static function normalize_diagnostic_class( string $class_name ): string {
		$class_name = ltrim( $class_name, '\\' );

		if ( 0 !== strpos( $class_name, 'ThisIsMyURL\\Shadow\\Diagnostics\\' ) ) {
			$class_name = 'ThisIsMyURL\\Shadow\\Diagnostics\\' . $class_name;
		}

		return $class_name;
	}

	/**
	 * Build the live progress payload from a scan session.
	 *
	 * @param array<string,mixed> $session   Session payload.
	 * @param array<string,mixed> $overrides Override values.
	 * @return array<string,mixed>
	 */
	private static function build_progress_state( array $session, array $overrides = array() ): array {
		$state = array(
			'phase'             => 'running',
			'current_class'     => '',
			'current_slug'      => '',
			'current_label'     => '',
			'completed'         => (int) ( $session['completed'] ?? 0 ),
			'total'             => (int) ( $session['total'] ?? 0 ),
			'dashboard_summary' => is_array( $session['dashboard_summary'] ?? null ) ? $session['dashboard_summary'] : array(),
			'updated_at'        => time(),
		);

		return array_merge( $state, $overrides );
	}

	/**
	 * Recalculate the dashboard summary from accumulated diagnostic results.
	 *
	 * @param array<string,mixed> $session Scan session data.
	 * @return array<string,int>
	 */
	private static function build_session_dashboard_summary( array $session ): array {
		$results = is_array( $session['results'] ?? null ) ? $session['results'] : array();
		$family_totals = is_array( $session['family_totals'] ?? null ) ? $session['family_totals'] : array();
		$diagnostic_families = is_array( $session['diagnostic_families'] ?? null ) ? $session['diagnostic_families'] : array();
		$passed  = 0;
		$failed  = 0;
		$families = self::build_family_summary_from_totals( $family_totals );

		foreach ( $results as $class_name => $result ) {
			$status = is_array( $result ) ? (string) ( $result['status'] ?? '' ) : '';
			$family = isset( $diagnostic_families[ (string) $class_name ] ) ? sanitize_key( (string) $diagnostic_families[ (string) $class_name ] ) : 'other';
			if ( '' === $family ) {
				$family = 'other';
			}
			if ( ! isset( $families[ $family ] ) ) {
				$families[ $family ] = array(
					'total'    => 0,
					'disabled' => 0,
					'active'   => 0,
					'passed'   => 0,
					'failed'   => 0,
					'pending'  => 0,
					'score'    => 100,
				);
			}
			if ( 'passed' === $status ) {
				$passed++;
				$families[ $family ]['passed']++;
			} elseif ( 'failed' === $status ) {
				$failed++;
				$families[ $family ]['failed']++;
			}
		}

		$active = (int) ( $session['total'] ?? 0 );
		foreach ( $families as $family_slug => $family_data ) {
			$family_active = (int) ( $family_data['active'] ?? 0 );
			$family_passed = (int) ( $family_data['passed'] ?? 0 );
			$family_failed = (int) ( $family_data['failed'] ?? 0 );
			$families[ $family_slug ]['pending'] = max( 0, $family_active - $family_passed - $family_failed );
			$families[ $family_slug ]['score'] = $family_active > 0 ? (int) round( ( $family_passed / $family_active ) * 100 ) : 100;
		}

		// Build attention items by joining failing results with activity row metadata.
		$failing_rows = array();
		if ( function_exists( 'thisismyurl_shadow_get_diagnostics_activity_rows' ) ) {
			$activity_rows = thisismyurl_shadow_get_diagnostics_activity_rows();
			if ( is_array( $activity_rows ) ) {
				$rows_by_class = array();
				foreach ( $activity_rows as $row ) {
					$row_class = isset( $row['class'] ) ? (string) $row['class'] : '';
					if ( '' !== $row_class ) {
						$rows_by_class[ $row_class ] = $row;
					}
				}
				foreach ( $results as $class_name => $result ) {
					$res_status = is_array( $result ) ? (string) ( $result['status'] ?? '' ) : '';
					if ( 'failed' === $res_status && isset( $rows_by_class[ (string) $class_name ] ) ) {
						$failing_rows[] = $rows_by_class[ (string) $class_name ];
					}
				}
			}
		}
		$attention = self::build_attention_items_from_rows( $failing_rows );

		return array(
			'total'           => (int) ( $session['all_total'] ?? $active ),
			'passed'          => $passed,
			'failed'          => $failed,
			'disabled'        => (int) ( $session['disabled_total'] ?? 0 ),
			'active'          => $active,
			'pending'         => max( 0, $active - $passed - $failed ),
			'families'        => $families,
			'score'           => $active > 0 ? (int) round( ( $passed / $active ) * 100 ) : 100,
			'attention_items' => $attention['items'],
			'extra_issues'    => $attention['extra_count'],
		);
	}

	/**
	 * Build top-8 attention items from a list of failing activity rows.
	 *
	 * @param array $failing_rows Activity rows whose status is failed.
	 * @return array{items: list<array>, extra_count: int}
	 */
	private static function build_attention_items_from_rows( array $failing_rows ): array {
		usort( $failing_rows, function ( $a, $b ) {
			return (int) ! empty( $b['is_core'] ) - (int) ! empty( $a['is_core'] );
		} );

		$top   = array_slice( $failing_rows, 0, 8 );
		$extra = max( 0, count( $failing_rows ) - 8 );

		$items = array();
		foreach ( $top as $row ) {
			$items[] = array(
				'name'           => isset( $row['name'] ) ? (string) $row['name'] : '',
				'failure_reason' => isset( $row['failure_reason'] ) ? (string) $row['failure_reason'] : '',
				'detail_url'     => isset( $row['detail_url'] ) ? esc_url_raw( (string) $row['detail_url'] ) : '',
				'is_core'        => ! empty( $row['is_core'] ),
				'family'         => isset( $row['family'] ) ? sanitize_key( (string) $row['family'] ) : '',
			);
		}

		return array(
			'items'       => $items,
			'extra_count' => $extra,
		);
	}

	/**
	 * Build total diagnostic counts per family from activity rows.
	 *
	 * @return array<string,array<string,int>>
	 */
	private static function build_family_totals(): array {
		if ( ! function_exists( 'thisismyurl_shadow_get_diagnostics_activity_rows' ) ) {
			return array();
		}

		$rows = thisismyurl_shadow_get_diagnostics_activity_rows();
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$families = array();

		foreach ( $rows as $row ) {
			$family = isset( $row['family'] ) ? sanitize_key( (string) $row['family'] ) : 'other';
			if ( '' === $family ) {
				$family = 'other';
			}

			if ( ! isset( $families[ $family ] ) ) {
				$families[ $family ] = array(
					'total'    => 0,
					'disabled' => 0,
				);
			}

			$families[ $family ]['total']++;

			$status = isset( $row['status_raw'] ) ? (string) $row['status_raw'] : '';
			$is_enabled = ! empty( $row['enabled'] );
			if ( 'disabled' === $status || ! $is_enabled ) {
				$families[ $family ]['disabled']++;
			}
		}

		return $families;
	}

	/**
	 * Build a class-to-family lookup for diagnostics shown on the dashboard.
	 *
	 * @return array<string,string>
	 */
	private static function build_diagnostic_family_map(): array {
		if ( ! function_exists( 'thisismyurl_shadow_get_diagnostics_activity_rows' ) ) {
			return array();
		}

		$rows = thisismyurl_shadow_get_diagnostics_activity_rows();
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$map = array();

		foreach ( $rows as $row ) {
			$class_name = isset( $row['class'] ) ? (string) $row['class'] : '';
			if ( '' === $class_name ) {
				continue;
			}

			$family = isset( $row['family'] ) ? sanitize_key( (string) $row['family'] ) : 'other';
			$map[ $class_name ] = '' !== $family ? $family : 'other';
		}

		return $map;
	}

	/**
	 * Build a default live family summary map from family totals.
	 *
	 * @param array<string,array<string,int>> $family_totals Base family totals.
	 * @return array<string,array<string,int>>
	 */
	private static function build_family_summary_from_totals( array $family_totals ): array {
		$families = array();

		foreach ( $family_totals as $family_slug => $family_data ) {
			$total = (int) ( $family_data['total'] ?? 0 );
			$disabled = (int) ( $family_data['disabled'] ?? 0 );
			$active = max( 0, $total - $disabled );

			$families[ $family_slug ] = array(
				'total'    => $total,
				'disabled' => $disabled,
				'active'   => $active,
				'passed'   => 0,
				'failed'   => 0,
				'pending'  => $active,
				'score'    => $active > 0 ? 0 : 100,
			);
		}

		return $families;
	}

	/**
	 * Merge arrays of diagnostic class names without duplicates.
	 *
	 * @param array<int,string> $existing Existing classes.
	 * @param array<int,string> $incoming Incoming classes.
	 * @return array<int,string>
	 */
	private static function merge_unique_strings( array $existing, array $incoming ): array {
		return array_values( array_unique( array_map( 'strval', array_merge( $existing, $incoming ) ) ) );
	}

	/**
	 * Merge findings arrays keyed by finding id when available.
	 *
	 * @param array<int,array<string,mixed>> $existing Existing findings.
	 * @param array<int,array<string,mixed>> $incoming Incoming findings.
	 * @return array<int,array<string,mixed>>
	 */
	private static function merge_findings( array $existing, array $incoming ): array {
		$indexed = self::index_findings_by_id( $existing );

		foreach ( $incoming as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}

			$finding_id = isset( $finding['id'] ) ? (string) $finding['id'] : '';
			if ( '' === $finding_id ) {
				$indexed[] = $finding;
				continue;
			}

			$indexed[ $finding_id ] = $finding;
		}

		return array_values( $indexed );
	}

	/**
	 * Index findings by id with a safe fallback for unknown ids.
	 *
	 * @param array<int,array<string,mixed>> $findings Findings to index.
	 * @return array<string|int,array<string,mixed>>
	 */
	private static function index_findings_by_id( array $findings ): array {
		if ( function_exists( 'thisismyurl_shadow_index_findings_by_id' ) ) {
			return \thisismyurl_shadow_index_findings_by_id( $findings );
		}

		$indexed = array();

		foreach ( $findings as $index => $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}

			$finding_id = isset( $finding['id'] ) ? (string) $finding['id'] : '';
			$indexed[ '' !== $finding_id ? $finding_id : $index ] = $finding;
		}

		return $indexed;
	}

	/**
	 * Calculate scan completion percentage.
	 *
	 * @param int $completed Completed diagnostics.
	 * @param int $total Total diagnostics.
	 * @return int
	 */
	private static function calculate_progress_percent( int $completed, int $total ): int {
		if ( $total <= 0 ) {
			return 100;
		}

		return min( 99, (int) floor( ( max( 0, $completed ) / max( 1, $total ) ) * 100 ) );
	}

	/**
	 * Build a human-readable label for the currently running diagnostic.
	 *
	 * @param string $class Diagnostic class.
	 * @param string $slug  Diagnostic slug.
	 * @return string
	 */
	private static function resolve_current_label( array $progress_state, array $session = array() ): string {
		$cursor = self::resolve_scan_cursor( $progress_state, $session );
		return $cursor['label'];
	}

	/**
	 * Resolve current class name from live progress state or persisted session.
	 *
	 * @param array<string,mixed> $progress_state Decoded progress transient.
	 * @return string
	 */
	private static function resolve_current_class( array $progress_state, array $session = array() ): string {
		$cursor = self::resolve_scan_cursor( $progress_state, $session );
		return $cursor['class'];
	}

	/**
	 * Build consistently-shaped scan cursor fields for API responses.
	 *
	 * @param array<string,mixed> $progress_state Progress transient payload.
	 * @param array<string,mixed> $session Optional session payload.
	 * @return array{current_class:string,current_slug:string,current_label:string,current_label_source:string}
	 */
	private static function build_scan_cursor_fields( array $progress_state, array $session = array() ): array {
		$cursor = self::resolve_scan_cursor( $progress_state, $session );

		return array(
			'current_class'        => $cursor['class'],
			'current_slug'         => $cursor['slug'],
			'current_label'        => $cursor['label'],
			'current_label_source' => $cursor['source'],
		);
	}

	/**
	 * Resolve the current scan cursor for UI status updates.
	 *
	 * @param array<string,mixed> $progress_state Progress transient payload.
	 * @param array<string,mixed> $session Optional session payload.
	 * @return array{class:string,slug:string,label:string,source:string}
	 */
	private static function resolve_scan_cursor( array $progress_state, array $session = array() ): array {
		$label = isset( $progress_state['current_label'] ) ? trim( (string) $progress_state['current_label'] ) : '';
		$slug  = isset( $progress_state['current_slug'] ) ? sanitize_key( (string) $progress_state['current_slug'] ) : '';
		$class = isset( $progress_state['current_class'] ) ? (string) $progress_state['current_class'] : '';
		$class_source = '' !== $class ? 'current_class' : '';

		if ( '' !== $label ) {
			return array(
				'class'  => $class,
				'slug'   => $slug,
				'label'  => $label,
				'source' => 'label',
			);
		}

		if ( '' !== $slug ) {
			return array(
				'class'  => $class,
				'slug'   => $slug,
				'label'  => self::build_scan_label( '', $slug ),
				'source' => 'slug',
			);
		}

		$session_state = ! empty( $session ) ? $session : self::get_scan_session_state();
		if ( '' === $class ) {
			$remaining = isset( $session_state['remaining'] ) && is_array( $session_state['remaining'] ) ? $session_state['remaining'] : array();
			$class = ! empty( $remaining ) ? (string) reset( $remaining ) : '';
			$class_source = '' !== $class ? 'next_remaining' : '';
		}
		if ( '' === $class ) {
			$class = isset( $session_state['last_batch_class'] ) ? (string) $session_state['last_batch_class'] : '';
			$class_source = '' !== $class ? 'last_batch_class' : '';
		}

		if ( '' !== $class ) {
			return array(
				'class'  => $class,
				'slug'   => '',
				'label'  => self::build_scan_label( $class, '' ),
				'source' => '' !== $class_source ? $class_source : 'last_batch_class',
			);
		}

		return array(
			'class'  => '',
			'slug'   => '',
			'label'  => '',
			'source' => 'none',
		);
	}

	private static function build_scan_label( string $class, string $slug ): string {
		if ( '' !== $slug ) {
			return ucfirst( str_replace( array( '-', '_' ), ' ', $slug ) );
		}

		$short = strrchr( $class, '\\' );
		$short = false === $short ? $class : ltrim( $short, '\\' );
		$short = preg_replace( '/^Diagnostic_/', '', $short );
		$short = preg_replace( '/(?<!^)([A-Z])/', ' $1', (string) $short );

		return trim( (string) $short );
	}

	/**
	 * Build a lightweight dashboard summary payload for live UI refresh.
	 *
	 * @return array<string,mixed>
	 */
	private static function build_dashboard_summary(): array {
		if ( ! function_exists( 'thisismyurl_shadow_get_diagnostics_activity_rows' ) ) {
			return array();
		}

		$rows = thisismyurl_shadow_get_diagnostics_activity_rows();
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$total        = count( $rows );
		$passed       = 0;
		$failed       = 0;
		$disabled     = 0;
		$pending      = 0;
		$families     = array();
		$failing_rows = array();

		foreach ( $rows as $row ) {
			$status = isset( $row['status_raw'] ) ? (string) $row['status_raw'] : '';
			$is_enabled = ! empty( $row['enabled'] );
			$last_run = isset( $row['last_run_ts'] ) ? (int) $row['last_run_ts'] : 0;
			$family = isset( $row['family'] ) ? sanitize_key( (string) $row['family'] ) : 'other';
			if ( '' === $family ) {
				$family = 'other';
			}
			if ( ! isset( $families[ $family ] ) ) {
				$families[ $family ] = array(
					'total'    => 0,
					'disabled' => 0,
					'active'   => 0,
					'passed'   => 0,
					'failed'   => 0,
					'pending'  => 0,
					'score'    => 100,
				);
			}

			$families[ $family ]['total']++;

			if ( 'disabled' === $status || ! $is_enabled ) {
				$disabled++;
				$families[ $family ]['disabled']++;
				continue;
			}

			$families[ $family ]['active']++;

			if ( 'passed' === $status ) {
				$passed++;
				$families[ $family ]['passed']++;
			} elseif ( 'failed' === $status ) {
				$failed++;
				$families[ $family ]['failed']++;
				if ( $is_enabled && $last_run > 0 ) {
					$failing_rows[] = $row;
				}
			} elseif ( $last_run <= 0 || ! in_array( $status, array( 'passed', 'failed' ), true ) ) {
				$pending++;
				$families[ $family ]['pending']++;
			}
		}

		foreach ( $families as $family_slug => $family_data ) {
			$family_active = (int) ( $family_data['active'] ?? 0 );
			$family_passed = (int) ( $family_data['passed'] ?? 0 );
			if ( 0 === (int) ( $family_data['pending'] ?? 0 ) ) {
				$families[ $family_slug ]['pending'] = max( 0, $family_active - $family_passed - (int) ( $family_data['failed'] ?? 0 ) );
			}
			$families[ $family_slug ]['score'] = $family_active > 0 ? (int) round( ( $family_passed / $family_active ) * 100 ) : 100;
		}

		$active    = max( 0, $total - $disabled );
		$score     = $active > 0 ? (int) round( ( $passed / $active ) * 100 ) : 100;
		$attention = self::build_attention_items_from_rows( $failing_rows );

		return array(
			'total'           => $total,
			'passed'          => $passed,
			'failed'          => $failed,
			'disabled'        => $disabled,
			'active'          => $active,
			'pending'         => $pending,
			'families'        => $families,
			'score'           => $score,
			'attention_items' => $attention['items'],
			'extra_issues'    => $attention['extra_count'],
		);
	}

	/**
	 * Schedule deep scan for weekly execution
	 *
	 * @return array Result data
	 */
	private static function schedule_deep_scan(): array {
		// Check if already scheduled
		$timestamp = wp_next_scheduled( 'thisismyurl_shadow_scheduled_deep_scan' );

		if ( ! $timestamp ) {
			// Schedule weekly deep scan on Sunday at 2 AM
			wp_schedule_event( strtotime( 'next Sunday 2:00 AM' ), 'weekly', 'thisismyurl_shadow_scheduled_deep_scan' );
		}

		// Log the activity
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Activity_Logger' ) ) {
			Activity_Logger::log(
				'scan_scheduled',
				'Deep Scan scheduled for weekly execution',
				'automation',
				array(
					'scan_type' => 'deep_scan',
					'frequency' => 'weekly',
					'time'      => '2:00 AM Sunday',
				)
			);
		}

		return array(
			'mode'      => 'schedule',
			'scheduled' => true,
			'next_run'  => wp_next_scheduled( 'thisismyurl_shadow_scheduled_deep_scan' ),
			'message'   => __( 'Deep Scan scheduled to run weekly on Sundays at 2:00 AM.', 'thisismyurl-shadow' ),
		);
	}
}

// Deep scan AJAX endpoint registration intentionally disabled while
// diagnostics/treatments execution flow is rebuilt from a clean baseline.
// Deep_Scan_Handler::register();
