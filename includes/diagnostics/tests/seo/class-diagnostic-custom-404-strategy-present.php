<?php
/**
 * Custom 404 Strategy Present Diagnostic
 *
 * Checks whether the active theme includes a custom 404 template to deliver
 * a helpful, branded experience for visitors hitting missing pages.
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
 * Custom 404 Strategy Present Diagnostic Class
 *
 * Checks for a 404.php template in the active theme directory and falls
 * back to known 404-management plugins before flagging the issue.
 *
 * @since 0.6095
 */
class Diagnostic_Custom_404_Strategy_Present extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'custom-404-strategy-present';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Custom 404 Strategy Present';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the active theme includes a custom 404 template to deliver a helpful, branded experience for visitors hitting missing pages.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'seo';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Checks whether a 404.php template exists in the active theme directory,
	 * then scans for active 404-management plugins before flagging the issue.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no 404 strategy is present, null when healthy.
	 */
	public static function check() {
		$theme_dir     = get_template_directory();
		$has_404_file  = file_exists( $theme_dir . '/404.php' );

		if ( $has_404_file ) {
			return null;
		}

		// Check for plugins that provide a 404 page strategy.
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$plugins_404    = array(
			'404page/404page.php',
			'custom-404-pro/custom-404-pro.php',
			'smart-custom-404-error-page/index.php',
			'all-404-redirect-to-homepage/all-404-redirect-to-homepage.php',
			'redirection/redirection.php', // Redirection also handles 404s.
		);

		foreach ( $plugins_404 as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No custom 404 page strategy is in place. The active theme does not include a 404.php template and no 404 management plugin is active. A well-designed 404 page keeps visitors engaged by offering navigation options and a search bar, rather than leaving them stranded. Add a 404.php template to your theme or install a 404 management plugin.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'theme_404_template' => false,
				'active_theme'       => get_template(),
			),
		);
	}
}
