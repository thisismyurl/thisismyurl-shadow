<?php
/**
 * wp-content Write Scope Minimized Diagnostic
 *
 * Checks whether key wp-content subdirectories have overly permissive write
 * permissions that could allow unauthorized file modifications on the server.
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
 * wp-content Write Scope Minimized Diagnostic Class
 *
 * Uses is_writable() to check the plugins, active-theme, wp-admin, and
 * wp-includes directories, flagging any that are unexpectedly writable.
 *
 * @since 0.6095
 */
class Diagnostic_Wp_Content_Write_Scope_Minimized extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'wp-content-write-scope-minimized';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'wp-content Write Scope Minimized';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether key wp-content subdirectories have overly permissive write permissions that could allow unauthorized file modifications on the server.';

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
	 * Calls is_writable() on the plugins directory, active theme directory,
	 * wp-admin, and wp-includes, returning a high-severity finding that lists
	 * any that are excessively writable by the web server process.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when writable paths are found, null when healthy.
	 */
	public static function check() {
		$checks = array(
			array(
				'path'  => WP_PLUGIN_DIR,
				'label' => 'plugins directory (' . WP_PLUGIN_DIR . ')',
			),
			array(
				'path'  => get_template_directory(),
				'label' => 'active theme directory',
			),
			array(
				'path'  => ABSPATH . 'wp-admin',
				'label' => 'wp-admin directory',
			),
			array(
				'path'  => ABSPATH . WPINC,
				'label' => 'wp-includes directory',
			),
		);

		$writable = array();
		foreach ( $checks as $item ) {
			if ( is_writable( $item['path'] ) ) {
				$writable[] = $item['label'];
			}
		}

		if ( empty( $writable ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: list of writable directories */
				__( 'The following directories are world-writable or PHP has write access to them: %s. On a production server, only the uploads directory should typically be writable. Writable plugin, theme, or core directories allow an attacker with server access (via LFI, file upload, etc.) to inject malicious code. Set permissions to 755 for directories and 644 for files in these paths.', 'thisismyurl-shadow' ),
				implode( '; ', $writable )
			),
			'severity'     => 'high',
			'threat_level' => 75,
			'details'      => array(
				'writable_paths' => $writable,
			),
		);
	}
}
