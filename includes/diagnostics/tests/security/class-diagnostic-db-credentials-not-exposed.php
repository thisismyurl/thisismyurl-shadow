<?php
/**
 * DB Credentials Not Exposed Diagnostic
 *
 * Scans for conditions that could leak database credentials or connection
 * details to the public, such as debug mode exposing error output.
 *
 * @package    Christopher Ross Shadow
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
 * DB Credentials Not Exposed Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Db_Credentials_Not_Exposed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'db-credentials-not-exposed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'DB Credentials Not Exposed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether debug modes or misconfigured error display settings could expose database credentials or errors to the public.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Checks WP_DEBUG + WP_DEBUG_DISPLAY settings and scans publicly accessible
	 * paths for files that may contain exposed database constants.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when credentials may be exposed, null when healthy.
	 */
	public static function check() {
		$issues = array();

		// Check if PHP error display is on (could expose DB errors publicly).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$display = defined( 'WP_DEBUG_DISPLAY' ) ? WP_DEBUG_DISPLAY : true; // Default is true.
			if ( $display ) {
				$issues[] = 'WP_DEBUG and WP_DEBUG_DISPLAY are both enabled, which may expose PHP/DB errors to visitors';
			}
		}

		// Check if debug log is inside the webroot (publicly fetchable).
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ) {
			$log_path = realpath( WP_DEBUG_LOG );
			$webroot  = realpath( ABSPATH );
			if ( $log_path && $webroot && 0 === strpos( $log_path, $webroot ) ) {
				$issues[] = 'WP_DEBUG_LOG is set to a path inside the webroot (' . WP_DEBUG_LOG . '), potentially exposing log data';
			}
		} elseif ( defined( 'WP_DEBUG_LOG' ) && true === WP_DEBUG_LOG ) {
			// Default log path: wp-content/debug.log — inside webroot.
			if ( file_exists( WP_CONTENT_DIR . '/debug.log' ) ) {
				$issues[] = 'wp-content/debug.log exists and may be publicly readable';
			}
		}

		// Check for backup copies of wp-config.php in the webroot.
		$backup_patterns = array(
			ABSPATH . 'wp-config.php.bak',
			ABSPATH . 'wp-config.bak',
			ABSPATH . 'wp-config.old',
			ABSPATH . 'wp-config~',
			ABSPATH . 'wp-config.php.orig',
		);
		foreach ( $backup_patterns as $path ) {
			if ( file_exists( $path ) ) {
				$issues[] = basename( $path ) . ' (config backup containing DB credentials) found in webroot';
			}
		}

		if ( empty( $issues ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: list of exposure risks */
				__( 'Potential database credential exposure risks were detected: %s. Database credentials must never be publicly accessible. Remove backup config files, move debug logs outside the webroot, and disable WP_DEBUG_DISPLAY on production.', 'thisismyurl-shadow' ),
				implode( '; ', $issues )
			),
			'severity'     => 'high',
			'threat_level' => 85,
			'details'      => array(
				'exposure_risks' => $issues,
			),
		);
	}
}
