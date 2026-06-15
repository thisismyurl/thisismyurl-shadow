<?php
/**
 * Treatment Base Class
 *
 * Abstract base class for automated fixes. All treatment implementations extend this class
 * to inherit security, backup, multisite, and logging capabilities.
 *
 * **Architecture Pattern:**
 * Treatment classes inherit:
 * - `can_apply()` - Capability checks (single-site and multisite)
 * - `backup_database()` - Automatic backup before changes
 * - `execute()` - Wrapper with hooks and logging
 * - `log_activity()` - KPI tracking for analytics
 *
 * **Philosophy Alignment:**
 * - #1 (Helpful Neighbor): Abstraction layer handles complexity invisibly
 * - #8 (Inspire Confidence): Built-in safety (backups, verification)
 * - #9 (Show Value): Activity logging built-in, not added later
 *
 * **Extension Pattern:**
 * When creating new treatments:
 * 1. Extend Treatment_Base
 * 2. Implement get_finding_id() - link to diagnostic
 * 3. Implement apply() - the actual fix logic
 * 4. Override can_apply() if custom permissions needed
 * 5. Treatments automatically get: backups, logging, hooks, multisite support
 *
 * **Example:**
 * ```php
 * class Treatment_My_Fix extends Treatment_Base {
 *     public static function get_finding_id() { return 'my-diagnostic'; }
 *     public static function apply() { return [ 'success' => true ]; }
 * }
 * ```
 *
 * @package ThisIsMyURL\Shadow
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define the execution model for automated fixes.
 *
 * Treatments are the write-capable half of the plugin. A diagnostic explains
 * what is wrong; a treatment explains how Shadow by Christopher Ross can change the site to fix
 * it. This base class standardizes that behavior so individual treatments only
 * need to implement their site-specific logic rather than re-solving backup,
 * permission, logging, or hook orchestration every time.
 *
 * From a training perspective, this is the best place to learn the lifecycle
 * of a fix: discovery points to a finding ID, execute() applies policy gates,
 * apply() performs the mutation, and surrounding hooks expose the action to
 * backup, activity, and analytics subsystems.
 *
 * @since 0.6095
 */
abstract class Treatment_Base implements Treatment_Interface {
	/**
	 * Default finding ID slug.
	 *
	 * Concrete treatments should override this with a diagnostic slug or
	 * implement get_finding_id() directly. This default prevents generated
	 * placeholder treatments from causing fatal errors before they are
	 * fully implemented.
	 *
	 * @since 0.6095
	 * @var string
	 */
	protected static $slug = '';

	/**
	 * Get the risk level for this treatment.
	 *
	 * Risk level controls whether a treatment is auto-applied after a scan or
	 * requires explicit user approval. Subclasses override this when their
	 * `apply()` performs file writes, database migrations, or other changes
	 * that may be hard to reverse without manual intervention.
	 *
	 * - 'safe'     — Read-only checks or trivially reversible option updates.
	 *                Auto-applied silently after a scan pass (default).
	 * - 'moderate' — Writes to theme files, creates mu-plugins, or touches
	 *                post content. Requires one-time user confirmation unless
	 *                the user has chosen "always apply" for this finding.
	 * - 'high'     — Edits core config files (wp-config.php), modifies
	 *                .htaccess, or performs wide database changes.
	 *                Always requires explicit user confirmation.
	 *
	 * @since  0.6095
	 * @return string Risk level: 'safe', 'moderate', or 'high'.
	 */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Check if this treatment can be applied in the current environment.
	 *
	 * Handles both single-site and multisite capability checks.
	 * Treatments can override this if they need custom logic.
	 *
	 * @return bool True if treatment can be applied.
	 */
	public static function can_apply() {
		// Multisite network admin requires network options capability.
		if ( is_multisite() && is_network_admin() ) {
			return current_user_can( 'manage_network_options' );
		}

		// Single site or multisite sub-site requires options capability.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the finding ID this treatment addresses.
	 *
	 * Concrete treatments should override this when the finding ID differs
	 * from the class slug.
	 *
	 * @return string Finding ID.
	 */
	public static function get_finding_id() {
		if ( ! empty( static::$slug ) ) {
			return static::$slug;
		}

		$class_name = get_called_class();
		$parts      = explode( '\\', $class_name );
		$class_name = end( $parts );

		if ( 0 === strpos( $class_name, 'Treatment_' ) ) {
			$class_name = substr( $class_name, strlen( 'Treatment_' ) );
		}

		return strtolower( str_replace( '_', '-', $class_name ) );
	}

	/**
	 * Apply the treatment to fix the finding.
	 *
	 * Placeholder treatments can inherit this safe fallback until a real
	 * automated fix is implemented.
	 *
	 * @return array Result array with 'success' and 'message' keys.
	 */
	public static function apply() {
		return array(
			'success' => false,
			'message' => __( 'This fix is not available automatically yet. You can still review the finding details and make the change manually.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Undo the treatment.
	 *
	 * Placeholder treatments can inherit this safe fallback until a real
	 * revert path is implemented.
	 *
	 * @return array Result array with 'success' and 'message' keys.
	 */
	public static function undo() {
		return array(
			'success' => false,
			'message' => __( 'There is no automatic revert path available for this fix yet.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Apply an option change while storing the previous value for undo.
	 *
	 * @param string $option_key       Option to update.
	 * @param mixed  $target_value     Value to set.
	 * @param string $backup_option    Option key used to store previous value.
	 * @param string $already_message  Message when target value is already set.
	 * @param string $updated_message  Message after successful update.
	 * @return array{success:bool,message:string}
	 */
	protected static function apply_option_with_backup(
		string $option_key,
		$target_value,
		string $backup_option,
		string $already_message,
		string $updated_message
	): array {
		$current = get_option( $option_key, $target_value );

		if ( $current === $target_value ) {
			return array(
				'success' => true,
				'message' => $already_message,
			);
		}

		update_option( $backup_option, $current, false );
		update_option( $option_key, $target_value );

		return array(
			'success' => true,
			'message' => $updated_message,
		);
	}

	/**
	 * Restore a previously backed-up option value.
	 *
	 * @param string        $option_key       Option to restore.
	 * @param string        $backup_option    Backup option key.
	 * @param string        $missing_message  Message when backup does not exist.
	 * @param callable|null $message_builder  Builder callback receiving previous value.
	 * @return array{success:bool,message:string}
	 */
	protected static function restore_option_from_backup(
		string $option_key,
		string $backup_option,
		string $missing_message,
		?callable $message_builder = null
	): array {
		$previous = get_option( $backup_option );

		if ( false === $previous ) {
			return array(
				'success' => false,
				'message' => $missing_message,
			);
		}

		update_option( $option_key, $previous );
		delete_option( $backup_option );

		$message = null;
		if ( is_callable( $message_builder ) ) {
			$message = (string) call_user_func( $message_builder, $previous );
		}

		if ( '' === (string) $message ) {
			$message = __( 'Setting restored successfully.', 'thisismyurl-shadow' );
		}

		return array(
			'success' => true,
			'message' => $message,
		);
	}

	/**
	 * Save arbitrary backup value under an option key.
	 *
	 * @param string $backup_option Backup option key.
	 * @param mixed  $value         Value to store.
	 * @return void
	 */
	protected static function save_backup_value( string $backup_option, $value ): void {
		update_option( $backup_option, $value, false );
	}

	/**
	 * Load and optionally consume a backup value.
	 *
	 * @param string $backup_option Backup option key.
	 * @param bool   $consume       Whether to delete the option after reading.
	 * @return array{found: bool, value: mixed}
	 */
	protected static function load_backup_value( string $backup_option, bool $consume = false ): array {
		$sentinel = '__thisismyurl_shadow_backup_missing__';
		$value    = get_option( $backup_option, $sentinel );

		$found = $sentinel !== $value;

		if ( $consume && $found ) {
			delete_option( $backup_option );
		}

		return array(
			'found' => $found,
			'value' => $found ? $value : null,
		);
	}

	/**
	 * Load a backup array that must contain required keys.
	 *
	 * @param string        $backup_option Backup option key.
	 * @param array<int,string> $required_keys Required array keys.
	 * @param bool          $consume       Whether to delete the option after reading.
	 * @return array{found: bool, value: array<string,mixed>|null}
	 */
	protected static function load_backup_array( string $backup_option, array $required_keys, bool $consume = false ): array {
		$loaded = static::load_backup_value( $backup_option, $consume );

		if ( ! $loaded['found'] || ! is_array( $loaded['value'] ) ) {
			return array(
				'found' => false,
				'value' => null,
			);
		}

		foreach ( $required_keys as $key ) {
			if ( ! array_key_exists( $key, $loaded['value'] ) ) {
				return array(
					'found' => false,
					'value' => null,
				);
			}
		}

		return array(
			'found' => true,
			'value' => $loaded['value'],
		);
	}

	/**
	 * Proxy a treatment check to an existing diagnostic check implementation.
	 *
	 * @param  string $diagnostic_class Fully-qualified diagnostic class name.
	 * @return array|null Diagnostic finding array, or null if unavailable.
	 */
	protected static function proxy_diagnostic_check( string $diagnostic_class ) {
		if ( ! class_exists( $diagnostic_class ) ) {
			return null;
		}

		if ( ! method_exists( $diagnostic_class, 'check' ) ) {
			return null;
		}

		return call_user_func( array( $diagnostic_class, 'check' ) );
	}

	/**
	 * Execute a treatment through the full Shadow by Christopher Ross treatment pipeline.
	 *
	 * This wrapper exists so every treatment follows the same lifecycle:
	 * - confirm the treatment has not been disabled,
	 * - fire before hooks so backup and observers can react,
	 * - optionally simulate the change in dry-run mode,
	 * - apply the real mutation when allowed,
	 * - clear caches and log the outcome.
	 *
	 * Subclasses should almost never override this method. They should implement
	 * apply() and let the shared pipeline handle cross-cutting concerns.
	 *
	 * @param bool $dry_run Whether to run in dry-run mode (check only, don't apply).
	 * @return array Result array.
	 */
	public static function execute( $dry_run = false ) {
		$class      = get_called_class();
		$finding_id = static::get_finding_id();

		// Allow admin to disable specific treatments via settings.
		$disabled = get_option( 'thisismyurl_shadow_disabled_treatment_classes', array() );
		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}

		$enabled = ! in_array( $class, $disabled, true );
		/**
		 * Filters whether a treatment is enabled.
		 *
		 * @since 0.6095
		 *
		 * @param bool   $enabled Whether the treatment is enabled.
		 * @param string $class   Fully-qualified treatment class name.
		 */
		$enabled = apply_filters( 'thisismyurl_shadow_treatment_enabled', $enabled, $class );

		if ( ! $enabled ) {
			return array(
				'success' => false,
				'message' => __( 'This treatment has been disabled by the site administrator.', 'thisismyurl-shadow' ),
			);
		}

		/**
		 * Fires before a treatment is applied.
		 *
		 * @param string $class      Treatment class name.
		 * @param string $finding_id Finding identifier.
		 * @param bool   $dry_run    Whether this is a dry run.
		 */
		do_action( 'thisismyurl_shadow_before_treatment_apply', $class, $finding_id, $dry_run );

		if ( $dry_run ) {
			// In dry-run mode, check if treatment can be applied but don't execute.
			$can_apply = static::can_apply();
			$result    = array(
				'success'     => $can_apply,
				'message'     => $can_apply
					? 'Treatment can be applied (dry run - no changes made)'
					: 'Treatment cannot be applied at this time',
				'dry_run'     => true,
				'would_apply' => $can_apply,
			);
		} else {
			$result = static::apply();

			// Clear findings cache after treatment is applied.
			if ( function_exists( 'thisismyurl_shadow_clear_findings_cache' ) ) {
				thisismyurl_shadow_clear_findings_cache();
			}

		}

		// Log every treatment execution to the activity log.
		if ( class_exists( Activity_Logger::class ) ) {
			$log_success = ! empty( $result['success'] );
			$log_message = $result['message'] ?? '';
			if ( isset( $result['dry_run'] ) && $result['dry_run'] ) {
				$log_details = sprintf(
					/* translators: %s: finding ID */
					__( 'Treatment dry-run checked: %s', 'thisismyurl-shadow' ),
					$finding_id
				);
			} elseif ( $log_success ) {
				$log_details = sprintf(
					/* translators: %s: finding ID */
					__( 'Treatment applied successfully: %s', 'thisismyurl-shadow' ),
					$finding_id
				);
			} else {
				$log_details = sprintf(
					/* translators: 1: finding ID, 2: error message */
					__( 'Treatment failed for %1$s: %2$s', 'thisismyurl-shadow' ),
					$finding_id,
					! empty( $log_message ) ? $log_message : __( 'Unknown error', 'thisismyurl-shadow' )
				);
			}
			Activity_Logger::log(
				'treatment_applied',
				$log_details,
				'',
				array(
					'class'      => $class,
					'finding_id' => $finding_id,
					'success'    => $log_success,
					'message'    => $log_message,
					'dry_run'    => $dry_run,
				)
			);
		}

		/**
		 * Fires after a treatment is applied.
		 *
		 * @param string $class      Treatment class name.
		 * @param string $finding_id Finding identifier.
		 * @param array  $result     Treatment result.
		 */
		do_action( 'thisismyurl_shadow_after_treatment_apply', $class, $finding_id, $result );

		/**
		 * Filter treatment result.
		 *
		 * @param array  $result     Result array.
		 * @param string $class      Treatment class name.
		 * @param string $finding_id Finding identifier.
		 */
		return apply_filters( 'thisismyurl_shadow_treatment_result', $result, $class, $finding_id );
	}

	/**
	 * Execute undo with hooks.
	 *
	 * Wraps undo() with before/after actions for extensibility.
	 *
	 * @return array Result array.
	 */
	public static function execute_undo() {
		$class = get_called_class();

		// Get finding_id if method exists, otherwise use class name.
		$finding_id = method_exists( $class, 'get_finding_id' ) ? static::get_finding_id() : $class;

		/**
		 * Fires before a treatment is undone.
		 *
		 * @param string $class      Treatment class name.
		 * @param string $finding_id Finding identifier.
		 */
		do_action( 'thisismyurl_shadow_before_treatment_undo', $class, $finding_id );

		$result = static::undo();

		// Clear findings cache after undo.
		if ( !
		empty( $result['success'] ) ) {
			if ( function_exists( 'thisismyurl_shadow_clear_findings_cache' ) ) {
				thisismyurl_shadow_clear_findings_cache();
			}
		}

		/**
		 * Fires after a treatment is undone.
		 *
		 * @param string $class      Treatment class name.
		 * @param string $finding_id Finding identifier.
		 * @param array  $result     Undo result.
		 */
		do_action( 'thisismyurl_shadow_after_treatment_undo', $class, $finding_id, $result );

		/**
		 * Filter treatment undo result.
		 *
		 * @param array  $result     Result array.
		 * @param string $class      Treatment class name.
		 * @param string $finding_id Finding identifier.
		 */
		return apply_filters( 'thisismyurl_shadow_treatment_undo_result', $result, $class, $finding_id );
	}
}
