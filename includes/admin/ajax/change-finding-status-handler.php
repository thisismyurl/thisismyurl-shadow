<?php

/**
 * Change Finding Status AJAX Handler
 *
 * @package ThisIsMyURL\Shadow
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Admin\Ajax;

use ThisIsMyURL\Shadow\Core\AJAX_Handler_Base;
use ThisIsMyURL\Shadow\Core\Options_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Change_Finding_Status_Handler extends AJAX_Handler_Base {

	/**
	 * Register AJAX hooks for finding status changes.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_thisismyurl_shadow_change_finding_status', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Handle finding status change requests.
	 *
	 * @since 0.6095
	 * @return void Sends JSON response and exits.
	 */
	public static function handle(): void {
		self::verify_request( 'thisismyurl_shadow_findings', 'manage_options', 'nonce' );

		$finding_id = self::get_post_param( 'finding_id', 'key', '', true );
		$new_status = self::get_post_param( 'new_status', 'key', '', true );

		$valid_statuses = array( 'detected', 'ignored', 'manual', 'automated', 'fixed' );
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			self::send_error( __( 'Invalid status.', 'thisismyurl-shadow' ) );
		}

		$status_manager = new \ThisIsMyURL\Shadow\Core\Finding_Status_Manager();
		$status_manager->set_finding_status( $finding_id, $new_status );

		// Log activity (Issue #565)
		\ThisIsMyURL\Shadow\Core\Activity_Logger::log(
			'finding_status_change',
			"Finding {$finding_id} moved to {$new_status}",
			'workflows',
			array(
				'finding_id' => $finding_id,
				'status'     => $new_status,
			)
		);

		// Smart Actions (Issue #567)
		$smart_action_result = self::execute_smart_action( $finding_id, $new_status );

		self::send_success(
			array(
				'message'           => __( 'Finding status updated.', 'thisismyurl-shadow' ),
				'finding_id'        => $finding_id,
				'new_status'        => $new_status,
				'smart_action'      => $smart_action_result['action'],
				'smart_action_desc' => $smart_action_result['description'],
			)
		);
	}

	/**
	 * Execute smart action based on column move (Issue #567)
	 *
	 * @param string $finding_id Finding identifier
	 * @param string $status New status/column
	 * @return array Action details
	 */
	private static function execute_smart_action( string $finding_id, string $status ): array {
		switch ( $status ) {
			case 'ignored':
				// Exclude from future scans, log reason
				$exclusions                = Options_Manager::get_array( 'thisismyurl_shadow_excluded_findings', array() );
				$exclusions[ $finding_id ] = array(
					'reason'    => 'user_ignored',
					'timestamp' => current_time( 'timestamp' ),
					'user'      => get_current_user_id(),
				);
				update_option( 'thisismyurl_shadow_excluded_findings', $exclusions );

				\ThisIsMyURL\Shadow\Core\Activity_Logger::log( 'finding_excluded', "Finding excluded from scans: {$finding_id}", '', array( 'finding_id' => $finding_id ) );

				return array(
					'action'      => 'excluded',
					'description' => __( 'This finding has been excluded from future scans. Move back to any other column to re-include it.', 'thisismyurl-shadow' ),
				);

			case 'manual':
				// User will fixOptions_Manager::get_array( 'thisismyurl_shadow_manual_fixes', []
				$manual_fixes                = get_option( 'thisismyurl_shadow_manual_fixes', array() );
				$manual_fixes[ $finding_id ] = array(
					'assigned' => current_time( 'timestamp' ),
					'user'     => get_current_user_id(),
				);
				update_option( 'thisismyurl_shadow_manual_fixes', $manual_fixes );

				return array(
					'action'      => 'manual_assigned',
					'description' => __( 'Logged as manual fix. Shadow by Christopher Ross will track your progress but won\'t auto-remind.', 'thisismyurl-shadow' ),
				);

			case 'automated':
				// Schedule aOptions_Manager::get_array( 'thisismyurl_shadow_scheduled_automated_fixes', []
				$scheduled                = get_option( 'thisismyurl_shadow_scheduled_automated_fixes', array() );
				$scheduled[ $finding_id ] = array(
					'queued' => current_time( 'timestamp' ),
					'user'   => get_current_user_id(),
					'status' => 'pending',
				);
				update_option( 'thisismyurl_shadow_scheduled_automated_fixes', $scheduled );

				// Schedule cron if not already scheduled
				if ( ! wp_next_scheduled( 'thisismyurl_shadow_run_automated_fixes' ) ) {
					wp_schedule_event( time() + 300, 'hourly', 'thisismyurl_shadow_run_automated_fixes' );
				}

				\ThisIsMyURL\Shadow\Core\Activity_Logger::log( 'workflow_created', "Automated fix queued: {$finding_id}", '', array( 'finding_id' => $finding_id ) );

				return array(
					'action'      => 'auto_scheduled',
					'description' => __( 'Automated fix scheduled. Will run within the next hour. Check Activity History for results.', 'thisismyurl-shadow' ),
				);

			case 'fixed':
				// Clear from all queues, mark complete
				self::clear_finding_from_queues( $finding_id );

				// Track KPI - Phase 3: Record finding resolution
				if ( class_exists( '\ThisIsMyURL\Shadow\Core\KPI_Tracker' ) ) {
					\ThisIsMyURL\Shadow\Core\KPI_Tracker::record_finding_resolved( $finding_id, 'fixed' );
					\ThisIsMyURL\Shadow\Core\Trend_Chart::record_finding_resolved( $finding_id, 'fixed' );
				}

				\ThisIsMyURL\Shadow\Core\Activity_Logger::log( 'finding_resolved', "Finding marked as fixed: {$finding_id}", '', array( 'finding_id' => $finding_id ) );

				return array(
					'action'      => 'completed',
					'description' => __( 'Marked as fixed! This finding will no longer appear in scans.', 'thisismyurl-shadow' ),
				);

			case 'detected':
			default:
				// Moved back to detected - re-include in scans
				self::clear_finding_from_queues( $finding_id );

				return array(
					'action'      => 'reactivated',
					'description' => __( 'Finding reactivated. Will appear in regular scans again.', 'thisismyurl-shadow' ),
				);
		}
	}

	/**
	 * Clear finding from all action queues
	 *
	 * @param string $finding_id Finding identifier
	 */
	private static function clear_finding_from_queues( string $finding_id ): void {
		// Batch-load all options once (reduces 6 DB queries to 3)
		$exclusions = Options_Manager::get_array( 'thisismyurl_shadow_excluded_findings', array() );
		$manual     = Options_Manager::get_array( 'thisismyurl_shadow_manual_fixes', array() );
		$automated  = Options_Manager::get_array( 'thisismyurl_shadow_scheduled_automated_fixes', array() );

		// Clear from exclusions
		if ( isset( $exclusions[ $finding_id ] ) ) {
			unset( $exclusions[ $finding_id ] );
			update_option( 'thisismyurl_shadow_excluded_findings', $exclusions );
		}

		// Clear from manual fixes
		if ( isset( $manual[ $finding_id ] ) ) {
			unset( $manual[ $finding_id ] );
			update_option( 'thisismyurl_shadow_manual_fixes', $manual );
		}

		// Clear from automated queue
		if ( isset( $automated[ $finding_id ] ) ) {
			unset( $automated[ $finding_id ] );
			update_option( 'thisismyurl_shadow_scheduled_automated_fixes', $automated );
		}
	}
}
