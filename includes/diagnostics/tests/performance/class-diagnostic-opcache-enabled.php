<?php
/**
 * OPcache Enabled Diagnostic
 *
 * Checks whether PHP OPcache is enabled and returning a live cache status.
 * OPcache is one of the highest-impact PHP runtime improvements because it
 * avoids reparsing and recompiling PHP files on every request.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OPcache Enabled Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Opcache_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'opcache-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'OPcache Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether PHP OPcache is enabled and actively caching compiled bytecode. OPcache eliminates the overhead of parsing and compiling PHP files on every request, typically reducing PHP execution time by 30–70%. It is the single highest-impact PHP configuration change available on most shared and managed hosts.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * Uses the OPcache status/configuration APIs when available, then falls back
	 * to ini directives. A healthy result requires both the extension and the
	 * runtime cache to be enabled for web requests.
	 *
	 * @since  0.6095
	 * @return array|null Return finding array when issue exists, null when healthy.
	 */
	public static function check() {
		$status        = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false;
		$configuration = function_exists( 'opcache_get_configuration' ) ? @opcache_get_configuration() : false;
		$directive_map = is_array( $configuration ) && isset( $configuration['directives'] ) && is_array( $configuration['directives'] )
			? $configuration['directives']
			: array();

		$ini_enabled     = self::normalize_ini_bool( ini_get( 'opcache.enable' ) );
		$ini_enable_cli  = self::normalize_ini_bool( ini_get( 'opcache.enable_cli' ) );
		$extension_alive = function_exists( 'opcache_get_status' )
			|| function_exists( 'opcache_get_configuration' )
			|| extension_loaded( 'Zend OPcache' );

		if ( is_array( $status ) && ! empty( $status['opcache_enabled'] ) ) {
			return null;
		}

		$memory_usage = is_array( $status ) && isset( $status['memory_usage'] ) && is_array( $status['memory_usage'] )
			? $status['memory_usage']
			: array();
		$stats        = is_array( $status ) && isset( $status['opcache_statistics'] ) && is_array( $status['opcache_statistics'] )
			? $status['opcache_statistics']
			: array();

		$severity     = ( ! $extension_alive || false === $ini_enabled ) ? 'high' : 'medium';
		$threat_level = 'high' === $severity ? 55 : 35;
		$description  = ( ! $extension_alive || false === $ini_enabled )
			? __( 'PHP OPcache does not appear to be enabled for web requests. Without OPcache, PHP has to parse and compile plugin, theme, and WordPress core files on every request, which increases CPU usage and slows response times.', 'thisismyurl-shadow' )
			: __( 'PHP OPcache is installed but does not appear to be active for this web runtime. This usually means the extension is loaded but disabled in configuration, or the status API is reporting no active opcode cache for requests hitting the site.', 'thisismyurl-shadow' );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => $description,
			'severity'     => $severity,
			'threat_level' => $threat_level,
			'details'      => array(
				'opcache_extension_loaded' => $extension_alive,
				'opcache_enabled_ini'      => $ini_enabled,
				'opcache_enable_cli_ini'   => $ini_enable_cli,
				'memory_usage'             => $memory_usage,
				'opcache_statistics'       => $stats,
				'directives'               => array(
					'opcache.enable'     => $directive_map['opcache.enable'] ?? ini_get( 'opcache.enable' ),
					'opcache.enable_cli' => $directive_map['opcache.enable_cli'] ?? ini_get( 'opcache.enable_cli' ),
				),
				'explanation_sections'     => array(
					'summary' => __( 'Shadow by Christopher Ross checked the live PHP runtime for OPcache support and did not find an active opcode cache for web requests. OPcache keeps precompiled PHP bytecode in shared memory so WordPress does not need to recompile the same files on every hit.', 'thisismyurl-shadow' ),
					'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross inspected the runtime using opcache_get_status() and opcache_get_configuration() when available, then cross-checked the opcache.enable and opcache.enable_cli directives reported by PHP. A healthy result requires the web runtime to report OPcache as enabled.', 'thisismyurl-shadow' ),
					'why_it_matters' => __( 'Without OPcache, PHP spends more CPU time parsing and compiling WordPress core, plugin, and theme files on every request. That increases server load, slows uncached responses, and reduces the number of requests your hosting environment can handle efficiently.', 'thisismyurl-shadow' ),
					'how_to_fix_it' => __( 'Enable OPcache in your PHP configuration or hosting control panel, then restart PHP-FPM or Apache if required by your host. After enabling it, rerun this diagnostic to confirm the live runtime reports OPcache as active for web requests.', 'thisismyurl-shadow' ),
				),
			),
		);
	}

	/**
	 * Normalize ini-style booleans to true/false/null.
	 *
	 * @param mixed $value Raw ini value.
	 * @return bool|null
	 */
	private static function normalize_ini_bool( $value ) {
		if ( null === $value || false === $value ) {
			return null;
		}

		$normalized = strtolower( trim( (string) $value ) );
		if ( '' === $normalized ) {
			return null;
		}

		if ( in_array( $normalized, array( '1', 'on', 'yes', 'true' ), true ) ) {
			return true;
		}

		if ( in_array( $normalized, array( '0', 'off', 'no', 'false' ), true ) ) {
			return false;
		}

		return null;
	}
}
