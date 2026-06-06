<?php
/**
 * Uploads PHP Execution Blocked Diagnostic
 *
 * Checks whether PHP execution is blocked in the WordPress uploads directory
 * to prevent uploaded malicious files from running as PHP scripts.
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
 * Uploads PHP Execution Blocked Diagnostic Class
 *
 * Inspects the uploads .htaccess file for PHP-denial directives and checks
 * for active security plugins that manage this protection automatically.
 *
 * @since 0.6095
 */
class Diagnostic_Uploads_Php_Execution_Blocked extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'uploads-php-execution-blocked';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Uploads PHP Execution Blocked';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether PHP execution is blocked in the WordPress uploads directory to prevent uploaded malicious files from executing as PHP scripts.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Looks for PHP-denial directives in the uploads .htaccess file and checks
	 * whether a known security plugin that handles this protection is active,
	 * returning a high-severity finding when neither is detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when PHP execution is unblocked, null when healthy.
	 */
	public static function check() {
		$upload_dir   = wp_upload_dir();
		$uploads_base = $upload_dir['basedir'];

		// Check for a .htaccess file in the uploads root that denies PHP execution.
		$htaccess_path = $uploads_base . '/.htaccess';

		if ( file_exists( $htaccess_path ) ) {
			$content = file_get_contents( $htaccess_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			// Look for typical deny-php directives.
			if (
				false !== stripos( $content, 'deny from all' )
				|| false !== stripos( $content, 'php_flag engine off' )
				|| false !== stripos( $content, 'AddType text/plain .php' )
				|| preg_match( '/\<Files\s+["\']?.*\.php/i', $content )
				|| false !== stripos( $content, 'php_admin_value engine Off' )
			) {
				return null; // PHP execution is blocked.
			}
		}

		// Check for a known security plugin that handles this server-side.
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$security_plugins = array(
			'wordfence/wordfence.php',
			'better-wp-security/better-wp-security.php', // iThemes Security
			'ithemes-security-pro/ithemes-security-pro.php',
			'all-in-one-wp-security-and-firewall/wp-security.php',
			'secupress/secupress.php',
			'sucuri-scanner/sucuri.php',
		);

		foreach ( $security_plugins as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				// Security plugin likely handles uploads protection — pass.
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'PHP execution in the uploads directory does not appear to be blocked. If an attacker uploads a PHP file disguised as an image (e.g., via a vulnerable plugin), they can execute arbitrary code on your server. Add a .htaccess file to wp-content/uploads/ that denies PHP execution, or use a security plugin that configures this automatically.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 80,
			'details'      => array(
				'htaccess_found'     => file_exists( $htaccess_path ),
				'uploads_path'       => $uploads_base,
			),
		);
	}
}
