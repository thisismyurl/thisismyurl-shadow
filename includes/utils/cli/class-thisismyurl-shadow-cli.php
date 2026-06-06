<?php
/**
 * WP-CLI commands for Christopher Ross Shadow.
 *
 * @package ThisIsMyURL\Shadow
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Christopher Ross Shadow commands with WP-CLI.
 */
class thisismyurl_shadow_CLI_Command {
	/**
	 * Register all Christopher Ross Shadow WP-CLI commands.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( '\\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'thisismyurl-shadow diagnostics list', array( __CLASS__, 'diagnostics_list' ) );
		\WP_CLI::add_command( 'thisismyurl-shadow diagnostics run', array( __CLASS__, 'diagnostics_run' ) );
		\WP_CLI::add_command( 'thisismyurl-shadow scan run', array( __CLASS__, 'scan_run' ) );
		\WP_CLI::add_command( 'thisismyurl-shadow treatments list', array( __CLASS__, 'treatments_list' ) );
		\WP_CLI::add_command( 'thisismyurl-shadow treatments apply', array( __CLASS__, 'treatments_apply' ) );
		\WP_CLI::add_command( 'thisismyurl-shadow readiness export', array( __CLASS__, 'readiness_export' ) );
	}

	/**
	 * List available diagnostics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv, yaml, ids, count.
	 *
	 * ## EXAMPLES
	 *
	 *     wp thisismyurl-shadow diagnostics list
	 *     wp thisismyurl-shadow diagnostics list --format=json
	 *
	 * @param array<int,string> $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function diagnostics_list( array $args, array $assoc_args ): void {
		$definitions = function_exists( 'thisismyurl_shadow_get_diagnostic_definitions' ) ? \thisismyurl_shadow_get_diagnostic_definitions() : array();
		$items       = array_map(
			static function ( array $definition ): array {
				return array(
					'id'        => (string) ( $definition['run_key'] ?? '' ),
					'title'     => (string) ( $definition['title'] ?? '' ),
					'family'    => (string) ( $definition['family'] ?? '' ),
					'severity'  => (string) ( $definition['severity'] ?? '' ),
					'readiness' => (string) ( $definition['readiness'] ?? '' ),
					'enabled'   => ! empty( $definition['enabled'] ) ? 'yes' : 'no',
					'class'     => (string) ( $definition['class'] ?? '' ),
				);
			},
			$definitions
		);

		self::render_items( $items, array( 'id', 'title', 'family', 'severity', 'readiness', 'enabled', 'class' ), $assoc_args );
	}

	/**
	 * Run one diagnostic by ID.
	 *
	 * ## OPTIONS
	 *
	 * <diagnostic>
	 * : Diagnostic run key or class alias.
	 *
	 * [--force]
	 * : Bypass diagnostic schedule throttling.
	 *
	 * [--format=<format>]
	 * : Output format: table, json, yaml.
	 *
	 * ## EXAMPLES
	 *
	 *     wp thisismyurl-shadow diagnostics run ssl-certificate-valid
	 *     wp thisismyurl-shadow diagnostics run homepage-meta --force --format=json
	 *
	 * @param array<int,string> $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function diagnostics_run( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'A diagnostic identifier is required.' );
		}

		$result = function_exists( 'thisismyurl_shadow_run_diagnostic' )
			? \thisismyurl_shadow_run_diagnostic( (string) $args[0], ! empty( $assoc_args['force'] ) )
			: array(
				'success' => false,
				'message' => 'Diagnostic runtime wrapper is unavailable.',
			);

		self::render_single_result( $result, $assoc_args );
	}

	/**
	 * Run the full Christopher Ross Shadow scan.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Force diagnostics regardless of saved scan settings.
	 *
	 * [--format=<format>]
	 * : Output format: table, json, yaml.
	 *
	 * ## EXAMPLES
	 *
	 *     wp thisismyurl-shadow scan run
	 *     wp thisismyurl-shadow scan run --force --format=json
	 *
	 * @param array<int,string> $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function scan_run( array $args, array $assoc_args ): void {
		$result = function_exists( 'thisismyurl_shadow_run_diagnostic_scan' )
			? \thisismyurl_shadow_run_diagnostic_scan( ! empty( $assoc_args['force'] ) )
			: array(
				'success' => false,
				'message' => 'Scan runtime wrapper is unavailable.',
			);

		self::render_single_result( $result, $assoc_args );
	}

	/**
	 * List executable treatments.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv, yaml, ids, count.
	 *
	 * ## EXAMPLES
	 *
	 *     wp thisismyurl-shadow treatments list
	 *     wp thisismyurl-shadow treatments list --format=json
	 *
	 * @param array<int,string> $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function treatments_list( array $args, array $assoc_args ): void {
		$definitions = function_exists( 'thisismyurl_shadow_get_treatment_definitions' ) ? \thisismyurl_shadow_get_treatment_definitions() : array();
		$items       = array_map(
			static function ( array $definition ): array {
				return array(
					'finding_id' => (string) ( $definition['finding_id'] ?? '' ),
					'risk_level' => (string) ( $definition['risk_level'] ?? '' ),
					'readiness'  => (string) ( $definition['readiness'] ?? '' ),
					'enabled'    => ! empty( $definition['enabled'] ) ? 'yes' : 'no',
					'can_apply'  => ! empty( $definition['can_apply'] ) ? 'yes' : 'no',
					'class'      => (string) ( $definition['class'] ?? '' ),
				);
			},
			$definitions
		);

		self::render_items( $items, array( 'finding_id', 'risk_level', 'readiness', 'enabled', 'can_apply', 'class' ), $assoc_args );
	}

	/**
	 * Apply one treatment by finding ID.
	 *
	 * ## OPTIONS
	 *
	 * <finding>
	 * : Finding identifier.
	 *
	 * [--dry-run]
	 * : Simulate the treatment without making changes.
	 *
	 * [--format=<format>]
	 * : Output format: table, json, yaml.
	 *
	 * ## EXAMPLES
	 *
	 *     wp thisismyurl-shadow treatments apply ssl-certificate-valid
	 *     wp thisismyurl-shadow treatments apply browser-caching-headers --dry-run --format=json
	 *
	 * @param array<int,string> $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function treatments_apply( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'A finding identifier is required.' );
		}

		$result = function_exists( 'thisismyurl_shadow_attempt_autofix' )
			? \thisismyurl_shadow_attempt_autofix( (string) $args[0], ! empty( $assoc_args['dry-run'] ) )
			: array(
				'success' => false,
				'message' => 'Treatment runtime wrapper is unavailable.',
			);

		self::render_single_result( $result, $assoc_args );
	}

	/**
	 * Export readiness inventory.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Export format: json or csv.
	 *
	 * ## EXAMPLES
	 *
	 *     wp thisismyurl-shadow readiness export
	 *     wp thisismyurl-shadow readiness export --format=csv
	 *
	 * @param array<int,string> $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function readiness_export( array $args, array $assoc_args ): void {
		$inventory = function_exists( 'thisismyurl_shadow_get_readiness_inventory' ) ? \thisismyurl_shadow_get_readiness_inventory() : array();
		$format    = isset( $assoc_args['format'] ) ? strtolower( (string) $assoc_args['format'] ) : 'json';

		if ( 'csv' === $format ) {
			\WP_CLI::line( self::inventory_to_csv( $inventory ) );
			return;
		}

		\WP_CLI::line( self::encode_json( $inventory ) );
	}

	/**
	 * Render a list result.
	 *
	 * @param array<int,array<string,mixed>> $items Items to render.
	 * @param array<int,string>              $fields Fields for structured output.
	 * @param array<string,mixed>            $assoc_args CLI options.
	 * @return void
	 */
	private static function render_items( array $items, array $fields, array $assoc_args ): void {
		$format = isset( $assoc_args['format'] ) ? strtolower( (string) $assoc_args['format'] ) : 'table';

		if ( 'count' === $format ) {
			\WP_CLI::line( (string) count( $items ) );
			return;
		}

		if ( 'ids' === $format ) {
			$id_field = $fields[0] ?? 'id';
			foreach ( $items as $item ) {
				if ( isset( $item[ $id_field ] ) ) {
					\WP_CLI::line( (string) $item[ $id_field ] );
				}
			}
			return;
		}

		if ( 'json' === $format ) {
			\WP_CLI::line( self::encode_json( $items ) );
			return;
		}

		if ( 'csv' === $format ) {
			\WP_CLI::line( self::rows_to_csv( $items, $fields ) );
			return;
		}

		if ( 'yaml' === $format && class_exists( '\\WP_CLI' ) && method_exists( '\\WP_CLI', 'print_value' ) ) {
			\WP_CLI::print_value( $items, array( 'format' => 'yaml' ) );
			return;
		}

		if ( function_exists( '\\WP_CLI\\Utils\\format_items' ) ) {
			\WP_CLI\Utils\format_items( 'table', $items, $fields );
			return;
		}

		\WP_CLI::line( self::encode_json( $items ) );
	}

	/**
	 * Render a single command result.
	 *
	 * @param array<string,mixed> $result Result payload.
	 * @param array<string,mixed> $assoc_args CLI options.
	 * @return void
	 */
	private static function render_single_result( array $result, array $assoc_args ): void {
		$format = isset( $assoc_args['format'] ) ? strtolower( (string) $assoc_args['format'] ) : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( self::encode_json( $result ) );
			return;
		}

		if ( 'yaml' === $format && class_exists( '\\WP_CLI' ) && method_exists( '\\WP_CLI', 'print_value' ) ) {
			\WP_CLI::print_value( $result, array( 'format' => 'yaml' ) );
			return;
		}

		$rows = array();
		foreach ( $result as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::encode_json( $value );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( null === $value ) {
				$value = '';
			}

			$rows[] = array(
				'field' => (string) $key,
				'value' => (string) $value,
			);
		}

		self::render_items( $rows, array( 'field', 'value' ), array( 'format' => 'table' ) );

		if ( empty( $result['success'] ) ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Encode data to pretty JSON.
	 *
	 * @param mixed $data Data to encode.
	 * @return string
	 */
	private static function encode_json( $data ): string {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * Convert rows to CSV.
	 *
	 * @param array<int,array<string,mixed>> $rows Row data.
	 * @param array<int,string>              $fields Field order.
	 * @return string
	 */
	private static function rows_to_csv( array $rows, array $fields ): string {
		$lines = array();
		$lines[] = self::build_csv_line( $fields );

		foreach ( $rows as $row ) {
			$values = array();
			foreach ( $fields as $field ) {
				$value = $row[ $field ] ?? '';
				if ( is_array( $value ) ) {
					$value = self::encode_json( $value );
				} elseif ( is_bool( $value ) ) {
					$value = $value ? 'true' : 'false';
				}

				$values[] = (string) $value;
			}

			$lines[] = self::build_csv_line( $values );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build a CSV line from scalar values.
	 *
	 * @param array<int,string> $values CSV values.
	 * @return string
	 */
	private static function build_csv_line( array $values ): string {
		$escaped = array_map(
			static function ( string $value ): string {
				if ( strpbrk( $value, ",\"\n\r" ) !== false ) {
					return '"' . str_replace( '"', '""', $value ) . '"';
				}

				return $value;
			},
			$values
		);

		return implode( ',', $escaped );
	}

	/**
	 * Convert readiness inventory to CSV.
	 *
	 * @param array<string,mixed> $inventory Inventory payload.
	 * @return string
	 */
	private static function inventory_to_csv( array $inventory ): string {
		$rows = array();

		foreach ( array( 'diagnostics', 'treatments' ) as $type ) {
			if ( empty( $inventory[ $type ] ) || ! is_array( $inventory[ $type ] ) ) {
				continue;
			}

			foreach ( $inventory[ $type ] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$rows[] = array(
					'type'  => $type,
					'class' => (string) ( $item['class'] ?? '' ),
					'state' => (string) ( $item['state'] ?? '' ),
					'file'  => (string) ( $item['file'] ?? '' ),
				);
			}
		}

		return self::rows_to_csv( $rows, array( 'type', 'class', 'state', 'file' ) );
	}
}

thisismyurl_shadow_CLI_Command::register();