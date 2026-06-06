<?php
/**
 * Post-Scan Treatments AJAX Handler
 *
 * After a diagnostic scan pass completes, this handler:
 *   1. Fetches which treatments are available for current failed findings.
 *   2. Auto-applies any 'safe' treatments (and those the user has previously
 *      approved with "always apply").
 *   3. Lets the caller apply a single treatment (moderate/high-risk) once the
 *      user has explicitly approved via the UI.
 *
 * **Flow:**
 * - `mode: 'fetch'`       → returns treatment list grouped by risk level.
 * - `mode: 'apply_safe'`  → auto-applies all safe + always-approved treatments.
 * - `mode: 'apply_one'`   → applies one treatment; optionally saves "always apply".
 *
 * **Always-Apply Storage:**
 * Option `thisismyurl_shadow_auto_apply_treatments` (array of finding IDs). When a
 * finding ID is in this list, repeat scans apply its treatment automatically
 * without prompting.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Admin\Ajax
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Admin\Ajax;

use ThisIsMyURL\Shadow\Core\AJAX_Handler_Base;
use ThisIsMyURL\Shadow\Treatments\Treatment_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post_Scan_Treatments_Handler Class
 *
 * Handles all three modes of post-scan treatment application.
 *
 * @since 0.6095
 */
class Post_Scan_Treatments_Handler extends AJAX_Handler_Base {

	/**
	 * WordPress option key for storing the user's "always apply" preferences.
	 *
	 * @var string
	 */
	const ALWAYS_APPLY_OPTION = 'thisismyurl_shadow_auto_apply_treatments';

	/**
	 * Register AJAX hooks.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_thisismyurl_shadow_post_scan_treatments', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Dispatch incoming AJAX requests to the appropriate mode handler.
	 *
	 * @since  0.6095
	 * @return void Sends JSON response and exits.
	 */
	public static function handle(): void {
		self::verify_request( 'thisismyurl_shadow_dashboard_nonce', 'manage_options', 'nonce' );

		$mode          = self::get_post_param( 'mode', 'text', 'fetch' );
		$allowed_modes = array( 'fetch', 'apply_safe', 'apply_one' );

		if ( ! in_array( $mode, $allowed_modes, true ) ) {
			self::send_error( __( 'Invalid treatment mode.', 'thisismyurl-shadow' ) );
		}

		switch ( $mode ) {
			case 'apply_safe':
				self::handle_apply_safe();
				break;
			case 'apply_one':
				self::handle_apply_one();
				break;
			case 'fetch':
			default:
				self::handle_fetch();
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Mode handlers
	// -------------------------------------------------------------------------

	/**
	 * Return the list of available treatments for current failed findings.
	 *
	 * Returns an object with three arrays keyed by risk level:
	 *   - `safe`     — will be auto-applied silently
	 *   - `moderate` — user will be prompted (unless always-approved)
	 *   - `high`     — user will always be prompted
	 *   - `always_approved` — finding IDs the user has pre-approved
	 *
	 * @since  0.6095
	 * @return void
	 */
	private static function handle_fetch(): void {
		$treatments     = self::get_available_treatments();
		$always_approve = self::get_always_apply_list();

		$grouped = array(
			'safe'            => array(),
			'moderate'        => array(),
			'high'            => array(),
			'always_approved' => $always_approve,
		);

		foreach ( $treatments as $item ) {
			$level = $item['risk_level'];
			if ( ! isset( $grouped[ $level ] ) ) {
				$level = 'moderate';
			}
			$grouped[ $level ][] = $item;
		}

		self::send_success( $grouped );
	}

	/**
	 * Auto-apply all 'safe' treatments plus any 'moderate' treatments
	 * the user has previously flagged as "always apply".
	 *
	 * Results are returned for each attempted treatment so the UI can
	 * summarise what happened.
	 *
	 * @since  0.6095
	 * @return void
	 */
	private static function handle_apply_safe(): void {
		$treatments = self::get_available_treatments();

		$applied = array();
		$skipped = array();

		foreach ( $treatments as $item ) {
			$finding_id = $item['finding_id'];
			$risk       = $item['risk_level'];

			// Auto-apply only low-risk fixes; moderate/high always require user review.
			$should_apply = ( 'safe' === $risk );

			if ( ! $should_apply ) {
				$skipped[] = $finding_id;
				continue;
			}

			$result = \thisismyurl_shadow_attempt_autofix( $finding_id );

			$applied[] = array(
				'finding_id' => $finding_id,
				'title'      => $item['title'],
				'success'    => ! empty( $result['success'] ),
				'message'    => $result['message'] ?? '',
				'risk_level' => $risk,
			);
		}

		self::send_success(
			array(
				'applied' => $applied,
				'skipped' => $skipped,
			)
		);
	}

	/**
	 * Apply a single treatment by finding ID.
	 *
	 * Optionally records the user's "always apply" preference for this
	 * finding so future scans do not prompt again.
	 *
	 * @since  0.6095
	 * @return void
	 */
	private static function handle_apply_one(): void {
		$finding_id          = self::get_post_param( 'finding_id', 'text', '', true );
		$always_apply        = self::get_post_param( 'always_apply', 'bool', false );
		$treatment           = self::get_treatment_descriptor( $finding_id );
		$remember_preference = $always_apply && self::can_remember_always_apply( $treatment );

		if ( empty( $finding_id ) ) {
			self::send_error( __( 'No finding ID provided.', 'thisismyurl-shadow' ) );
		}

		if ( $remember_preference ) {
			self::save_always_apply( $finding_id );
		}

		$result = \thisismyurl_shadow_attempt_autofix( $finding_id );

		if ( is_array( $result ) ) {
			$result['always_apply_saved'] = $remember_preference;

			if ( $always_apply && ! $remember_preference ) {
				$result['always_apply_message'] = __( 'This fix will still require manual review each time because only moderate-risk treatments can be remembered for automatic application.', 'thisismyurl-shadow' );
			}
		}

		if ( is_array( $result ) && ! empty( $result['success'] ) ) {
			self::send_success( $result );
		} else {
			self::send_error( $result['message'] ?? __( 'Treatment could not be applied.', 'thisismyurl-shadow' ), $result );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a list of treatments available for the site's current failed findings.
	 *
	 * Only includes findings that:
	 *   - have not been dismissed
	 *   - have a registered treatment class
	 *   - pass `can_apply()` for the current user
	 *
	 * @since  0.6095
	 * @return array[] Array of treatment descriptor arrays.
	 */
	private static function get_available_treatments(): array {
		if ( ! class_exists( Treatment_Registry::class ) ) {
			return array();
		}

		$findings = function_exists( 'thisismyurl_shadow_get_cached_findings' )
			? thisismyurl_shadow_get_cached_findings()
			: array();

		if ( empty( $findings ) ) {
			$findings = get_option( 'thisismyurl_shadow_site_findings', array() );
		}

		if ( ! is_array( $findings ) ) {
			return array();
		}

		$registry = new Treatment_Registry();
		$items    = array();

		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}

			// Skip dismissed findings.
			if ( ! empty( $finding['dismissed'] ) ) {
				continue;
			}

			$finding_id = $finding['id'] ?? '';
			if ( empty( $finding_id ) ) {
				continue;
			}

			$treatment_class = $registry->get_treatment( $finding_id );
			if ( empty( $treatment_class ) ) {
				continue;
			}

			if ( ! $treatment_class::can_apply() ) {
				continue;
			}

			$items[] = array(
				'finding_id'  => $finding_id,
				'title'       => $finding['title'] ?? $finding_id,
				'description' => $finding['description'] ?? '',
				'severity'    => $finding['severity'] ?? 'medium',
				'risk_level'  => $treatment_class::get_risk_level(),
				'class'       => $treatment_class,
			);
		}

		return $items;
	}

	/**
	 * Get the list of finding IDs the user has approved for automatic application.
	 *
	 * @since  0.6095
	 * @return string[] Array of finding IDs.
	 */
	private static function get_always_apply_list(): array {
		$list = get_option( self::ALWAYS_APPLY_OPTION, array() );
		if ( ! is_array( $list ) ) {
			return array();
		}

		$list = array_map( 'sanitize_key', $list );
		$list = array_filter(
			$list,
			function ( $item ) {
				return is_string( $item ) && '' !== $item;
			}
		);

		return array_values( array_unique( $list ) );
	}

	/**
	 * Save a finding ID to the "always apply" preference list.
	 *
	 * @since  0.6095
	 * @param  string $finding_id Finding ID to save.
	 * @return void
	 */
	private static function save_always_apply( string $finding_id ): void {
		$finding_id = sanitize_key( $finding_id );
		if ( '' === $finding_id ) {
			return;
		}

		$list = self::get_always_apply_list();
		if ( ! in_array( $finding_id, $list, true ) ) {
			$list[] = $finding_id;
			update_option( self::ALWAYS_APPLY_OPTION, $list, false );
		}
	}

	/**
	 * Resolve a treatment descriptor for the supplied finding.
	 *
	 * @since  0.6095
	 * @param  string $finding_id Finding identifier.
	 * @return array<string,mixed>|null
	 */
	private static function get_treatment_descriptor( string $finding_id ): ?array {
		$finding_id = sanitize_key( $finding_id );
		if ( '' === $finding_id ) {
			return null;
		}

		foreach ( self::get_available_treatments() as $item ) {
			if ( isset( $item['finding_id'] ) && sanitize_key( (string) $item['finding_id'] ) === $finding_id ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Determine whether a treatment can be remembered for future auto-apply.
	 *
	 * @since  0.6095
	 * @param  array<string,mixed>|null $treatment Treatment descriptor.
	 * @return bool
	 */
	private static function can_remember_always_apply( ?array $treatment ): bool {
		if ( ! is_array( $treatment ) ) {
			return false;
		}

		$risk_level = isset( $treatment['risk_level'] ) ? (string) $treatment['risk_level'] : 'moderate';

		return 'moderate' === $risk_level;
	}
}
