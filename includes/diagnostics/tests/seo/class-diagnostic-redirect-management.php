<?php
/**
 * Redirect Management Diagnostic
 *
 * Checks whether a dedicated redirect management plugin is active to handle
 * 301 redirects properly and prevent link rot when URLs change.
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
 * Diagnostic_Redirect_Management Class
 *
 * Scans active plugins for recognised redirect management tools and returns
 * a medium-severity finding when none are detected.
 *
 * @since 0.6095
 */
class Diagnostic_Redirect_Management extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'redirect-management';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Redirect Management';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a dedicated redirect management plugin is active to handle 301 redirects properly and prevent link rot when URLs change.';

	/**
	 * Gauge family/category.
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
	 * Iterates the active_plugins option against a list of well-known redirect
	 * management plugins (including Redirection, Safe Redirect Manager, and SEO
	 * plugins with built-in redirect features). Returns null immediately when any
	 * recognised plugin is found, otherwise returns a medium-severity finding.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no redirect plugin is active, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$redirect_plugins = array(
			'redirection/redirection.php'                         => 'Redirection',
			'safe-redirect-manager/safe-redirect-manager.php'     => 'Safe Redirect Manager',
			'simple-301-redirects/wp-simple-301-redirects.php'    => 'Simple 301 Redirects',
			'301-redirects/301-redirects.php'                     => '301 Redirects',
			'wordpress-seo-premium/wp-seo-premium.php'            => 'Yoast SEO Premium (Redirects)',
			'seo-by-rank-math/rank-math.php'                      => 'Rank Math (Redirect Manager)',
			'seo-by-rank-math-pro/rank-math-pro.php'              => 'Rank Math Pro (Redirect Manager)',
			'permalink-manager/permalink-manager.php'             => 'Permalink Manager',
		);

		foreach ( $redirect_plugins as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No redirect management plugin is active. Without a redirect tool, changed or deleted URLs return 404 errors rather than forwarding visitors and search engines to the correct destination. This causes link-equity loss and a poor user experience. Install a plugin such as Redirection or Safe Redirect Manager to manage 301 redirects.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'redirect_plugin_detected' => false,
			),
		);
	}
}
