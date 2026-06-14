<?php
/**
 * Runtime helper functions for scans, diagnostics, and readiness inventory.
 *
 * These wrappers give extensions and WP-CLI a stable top-level API that sits
 * above the underlying registries and page controllers.
 *
 * @package ThisIsMyURL\Shadow
 */

declare(strict_types=1);

use ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager;
use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Core\Readiness_Registry;
use ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry;
use ThisIsMyURL\Shadow\Treatments\Treatment_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'thisismyurl_shadow_get_diagnostic_definitions' ) ) {
	/**
	 * Return the display-ready diagnostic list used by UI and CLI surfaces.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function thisismyurl_shadow_get_diagnostic_definitions(): array {
		if ( ! class_exists( Diagnostic_Registry::class ) ) {
			return array();
		}

		$definitions = Diagnostic_Registry::get_diagnostic_definitions();

		/**
		 * Filter the top-level diagnostic definitions list.
		 *
		 * @param array<int,array<string,mixed>> $definitions Diagnostic definitions.
		 */
		$definitions = apply_filters( 'thisismyurl_shadow_diagnostic_definitions', $definitions );

		return is_array( $definitions ) ? array_values( $definitions ) : array();
	}
}

if ( ! function_exists( 'thisismyurl_shadow_resolve_diagnostic_class' ) ) {
	/**
	 * Resolve a diagnostic class from a run key, short class, or FQCN.
	 *
	 * @param string $diagnostic_id Diagnostic identifier.
	 * @return string|null
	 */
	function thisismyurl_shadow_resolve_diagnostic_class( string $diagnostic_id ): ?string {
		$diagnostic_id = sanitize_key( $diagnostic_id );
		if ( '' === $diagnostic_id ) {
			return null;
		}

		$resolved = null;
		foreach ( thisismyurl_shadow_get_diagnostic_definitions() as $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['class'] ) ) {
				continue;
			}

			$class       = (string) $definition['class'];
			$short_class = isset( $definition['short_class'] ) ? sanitize_key( str_replace( '_', '-', strtolower( (string) $definition['short_class'] ) ) ) : '';
			$run_key     = isset( $definition['run_key'] ) ? sanitize_key( (string) $definition['run_key'] ) : '';

			if ( $diagnostic_id === $run_key || $diagnostic_id === $short_class || $diagnostic_id === sanitize_key( str_replace( '\\', '-', strtolower( $class ) ) ) ) {
				$resolved = $class;
				break;
			}
		}

		/**
		 * Filter the diagnostic class resolved for a given identifier.
		 *
		 * @param string|null $resolved      Resolved class or null.
		 * @param string      $diagnostic_id Sanitized diagnostic identifier.
		 */
		$resolved = apply_filters( 'thisismyurl_shadow_diagnostic_class', $resolved, $diagnostic_id );

		return is_string( $resolved ) && '' !== $resolved ? $resolved : null;
	}
}

if ( ! function_exists( 'thisismyurl_shadow_run_diagnostic' ) ) {
	/**
	 * Execute a single diagnostic through the shared runtime wrapper.
	 *
	 * @param string $diagnostic_id Diagnostic identifier.
	 * @param bool   $force         Whether to bypass schedule throttling.
	 * @return array<string, mixed>
	 */
	function thisismyurl_shadow_run_diagnostic( string $diagnostic_id, bool $force = false ): array {
		$diagnostic_id = sanitize_key( $diagnostic_id );
		if ( '' === $diagnostic_id ) {
			return array(
				'success' => false,
				'message' => __( 'No diagnostic was supplied.', 'thisismyurl-shadow' ),
			);
		}

		$class_name = thisismyurl_shadow_resolve_diagnostic_class( $diagnostic_id );

		/**
		 * Filter a single diagnostic run before Shadow by Christopher Ross executes the default flow.
		 *
		 * Return a result array to short-circuit execution.
		 *
		 * @param array<string,mixed>|null $pre_result    Precomputed result or null.
		 * @param string                   $diagnostic_id Sanitized diagnostic identifier.
		 * @param string|null              $class_name    Fully-qualified diagnostic class when resolvable.
		 * @param bool                     $force         Whether the run is forced.
		 */
		$pre_result = apply_filters( 'thisismyurl_shadow_pre_run_diagnostic', null, $diagnostic_id, $class_name, $force );
		if ( is_array( $pre_result ) ) {
			return apply_filters( 'thisismyurl_shadow_diagnostic_run_result', $pre_result, $diagnostic_id, $class_name, $force );
		}

		if ( ! is_string( $class_name ) || '' === $class_name ) {
			return array(
				'success'      => false,
				'diagnostic'   => $diagnostic_id,
				'message'      => __( 'The requested diagnostic could not be resolved.', 'thisismyurl-shadow' ),
			);
		}

		/**
		 * Fires before a top-level diagnostic run starts.
		 *
		 * @param string $diagnostic_id Sanitized diagnostic identifier.
		 * @param string $class_name    Fully-qualified diagnostic class.
		 * @param bool   $force         Whether the run is forced.
		 */
		do_action( 'thisismyurl_shadow_before_run_diagnostic', $diagnostic_id, $class_name, $force );

		try {
			if ( ! class_exists( $class_name ) || ! is_subclass_of( $class_name, Diagnostic_Base::class ) || ! method_exists( $class_name, 'execute' ) ) {
				$result = array(
					'success'    => false,
					'diagnostic' => $diagnostic_id,
					'class'      => $class_name,
					'message'    => __( 'This diagnostic is not executable in the current environment.', 'thisismyurl-shadow' ),
				);
			} else {
				$finding = $class_name::execute( $force );
				$result  = array(
					'success'     => true,
					'diagnostic'  => $diagnostic_id,
					'class'       => $class_name,
					'has_finding' => null !== $finding,
					'finding'     => $finding,
					'message'     => null === $finding
						? __( 'Diagnostic completed without a finding.', 'thisismyurl-shadow' )
						: __( 'Diagnostic completed with a finding.', 'thisismyurl-shadow' ),
				);
			}
		} catch ( \Throwable $exception ) {
			$result = array(
				'success'    => false,
				'diagnostic' => $diagnostic_id,
				'class'      => $class_name,
				'message'    => sprintf(
					/* translators: %s: exception message */
					__( 'Diagnostic failed: %s', 'thisismyurl-shadow' ),
					$exception->getMessage()
				),
			);
		}

		/**
		 * Fires after a top-level diagnostic run finishes.
		 *
		 * @param string              $diagnostic_id Sanitized diagnostic identifier.
		 * @param string              $class_name    Fully-qualified diagnostic class.
		 * @param bool                $force         Whether the run was forced.
		 * @param array<string,mixed> $result        Result payload.
		 */
		do_action( 'thisismyurl_shadow_after_run_diagnostic', $diagnostic_id, $class_name, $force, $result );

		/**
		 * Filter the final single-diagnostic result.
		 *
		 * @param array<string,mixed> $result        Result payload.
		 * @param string              $diagnostic_id Sanitized diagnostic identifier.
		 * @param string              $class_name    Fully-qualified diagnostic class.
		 * @param bool                $force         Whether the run was forced.
		 */
		return apply_filters( 'thisismyurl_shadow_diagnostic_run_result', $result, $diagnostic_id, $class_name, $force );
	}
}

if ( ! function_exists( 'thisismyurl_shadow_run_diagnostic_scan' ) ) {
	/**
	 * Execute the full scan flow through a hookable wrapper.
	 *
	 * @param bool $force_diagnostics Whether to force diagnostics.
	 * @return array<string, mixed>
	 */
	function thisismyurl_shadow_run_diagnostic_scan( bool $force_diagnostics = false ): array {
		/**
		 * Filter a full scan before Shadow by Christopher Ross executes the default flow.
		 *
		 * Return a result array to short-circuit execution.
		 *
		 * @param array<string,mixed>|null $pre_result         Precomputed result or null.
		 * @param bool                     $force_diagnostics Whether diagnostics are forced.
		 */
		$pre_result = apply_filters( 'thisismyurl_shadow_pre_run_scan', null, $force_diagnostics );
		if ( is_array( $pre_result ) ) {
			return apply_filters( 'thisismyurl_shadow_scan_result', $pre_result, $force_diagnostics );
		}

		/**
		 * Fires before a top-level scan starts.
		 *
		 * @param bool $force_diagnostics Whether diagnostics are forced.
		 */
		do_action( 'thisismyurl_shadow_before_run_scan', $force_diagnostics );

		if ( ! class_exists( Scan_Frequency_Manager::class ) ) {
			$result = array(
				'success' => false,
				'message' => __( 'The scan manager is not available in this environment.', 'thisismyurl-shadow' ),
			);
		} else {
			try {
				$result            = Scan_Frequency_Manager::run_diagnostic_scan( $force_diagnostics );
				$result['success'] = true;
			} catch ( \Throwable $exception ) {
				$result = array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: exception message */
						__( 'Scan failed: %s', 'thisismyurl-shadow' ),
						$exception->getMessage()
					),
				);
			}
		}

		/**
		 * Fires after a top-level scan finishes.
		 *
		 * @param array<string,mixed> $result            Scan result payload.
		 * @param bool                $force_diagnostics Whether diagnostics were forced.
		 */
		do_action( 'thisismyurl_shadow_after_run_scan', $result, $force_diagnostics );

		/**
		 * Filter the final scan result.
		 *
		 * @param array<string,mixed> $result            Scan result payload.
		 * @param bool                $force_diagnostics Whether diagnostics were forced.
		 */
		return apply_filters( 'thisismyurl_shadow_scan_result', $result, $force_diagnostics );
	}
}

if ( ! function_exists( 'thisismyurl_shadow_get_treatment_definitions' ) ) {
	/**
	 * Return a display-ready list of executable treatments.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function thisismyurl_shadow_get_treatment_definitions(): array {
		if ( ! class_exists( Treatment_Registry::class ) || ! class_exists( Readiness_Registry::class ) ) {
			return array();
		}

		$definitions = array();
		foreach ( Treatment_Registry::get_all() as $class_name ) {
			if ( ! is_string( $class_name ) || '' === $class_name || ! class_exists( $class_name ) ) {
				continue;
			}

			$parts       = explode( '\\', $class_name );
			$short_class = (string) end( $parts );
			$finding_id  = method_exists( $class_name, 'get_finding_id' ) ? sanitize_key( (string) $class_name::get_finding_id() ) : '';
			$risk_level  = method_exists( $class_name, 'get_risk_level' ) ? (string) $class_name::get_risk_level() : 'safe';

			$definitions[] = array(
				'class'       => $class_name,
				'short_class' => $short_class,
				'finding_id'  => $finding_id,
				'readiness'   => Readiness_Registry::get_treatment_state( $class_name ),
				'enabled'     => '' !== $finding_id ? thisismyurl_shadow_is_treatment_enabled( $finding_id ) : false,
				'can_apply'   => '' !== $finding_id ? thisismyurl_shadow_can_apply_treatment( $finding_id ) : false,
				'risk_level'  => $risk_level,
			);
		}

		usort(
			$definitions,
			static function ( array $left, array $right ): int {
				return strcmp( (string) ( $left['finding_id'] ?? '' ), (string) ( $right['finding_id'] ?? '' ) );
			}
		);

		/**
		 * Filter the top-level treatment definitions list.
		 *
		 * @param array<int,array<string,mixed>> $definitions Treatment definitions.
		 */
		$definitions = apply_filters( 'thisismyurl_shadow_treatment_definitions', $definitions );

		return is_array( $definitions ) ? array_values( $definitions ) : array();
	}
}

if ( ! function_exists( 'thisismyurl_shadow_get_readiness_inventory' ) ) {
	/**
	 * Return the readiness inventory through a hookable wrapper.
	 *
	 * @return array<string, mixed>
	 */
	function thisismyurl_shadow_get_readiness_inventory(): array {
		/**
		 * Filter readiness inventory before Shadow by Christopher Ross builds the default payload.
		 *
		 * Return an array to short-circuit execution.
		 *
		 * @param array<string,mixed>|null $pre_inventory Precomputed inventory or null.
		 */
		$pre_inventory = apply_filters( 'thisismyurl_shadow_pre_readiness_inventory', null );
		if ( is_array( $pre_inventory ) ) {
			return apply_filters( 'thisismyurl_shadow_readiness_inventory', $pre_inventory );
		}

		if ( ! class_exists( Readiness_Registry::class ) ) {
			$inventory = array(
				'generated_at' => time(),
				'diagnostics'  => array(),
				'treatments'   => array(),
			);
		} else {
			$inventory = Readiness_Registry::get_inventory();
		}

		/**
		 * Fires after Shadow by Christopher Ross builds the readiness inventory.
		 *
		 * @param array<string,mixed> $inventory Inventory payload.
		 */
		do_action( 'thisismyurl_shadow_readiness_inventory_generated', $inventory );

		/**
		 * Filter the final readiness inventory payload.
		 *
		 * @param array<string,mixed> $inventory Inventory payload.
		 */
		return apply_filters( 'thisismyurl_shadow_readiness_inventory', $inventory );
	}
}