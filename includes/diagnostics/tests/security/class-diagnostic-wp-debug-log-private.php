<?php
/**
 * WP Debug Log Private Diagnostic
 *
 * Checks whether the WordPress debug log file is publicly accessible via a
 * direct URL when WP_DEBUG_LOG is enabled, which could expose sensitive data.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Server_Environment_Helper as Server_Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Wp_Debug_Log_Private Class
 *
 * Checks the WP_DEBUG_LOG path via the Server_Env helper and tests whether
 * the log file is located inside the public webroot and reachable by HTTP.
 *
 * @since 0.6095
 */
class Diagnostic_Wp_Debug_Log_Private extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'wp-debug-log-private';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'WP Debug Log Private';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress debug log file is publicly accessible via a direct URL when WP_DEBUG_LOG is enabled, which could expose sensitive server details.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

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
	 * Confirms WP_DEBUG_LOG is active, then delegates to Server_Env to test
	 * whether the log file is publicly accessible, returning a high-severity
	 * finding when the log can be fetched directly from a browser.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when the debug log is public, null when healthy.
	 */
	public static function check() {
		if ( ! Server_Env::is_wp_debug_log_enabled() ) {
			return null;
		}

		if ( ! Server_Env::is_debug_log_publicly_accessible() ) {
			return null;
		}

		$log_value = Server_Env::get_wp_debug_log();
		$log_path  = is_string( $log_value ) ? $log_value : WP_CONTENT_DIR . '/debug.log';

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'The WordPress debug log appears to be stored inside the web root and may be publicly accessible. The debug log can contain file paths, database credentials, and application errors. Move it outside the web root or protect it with an .htaccess rule.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 65,
			'details'      => array(
				'log_path'          => $log_path,
				'inside_web_root'   => true,
				'htaccess_present'  => file_exists( WP_CONTENT_DIR . '/.htaccess' ),
			),
		);
	}
}
