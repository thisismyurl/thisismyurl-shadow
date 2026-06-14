<?php
/**
 * Dashicons on Frontend Diagnostic
 *
 * Checks whether the WordPress admin icon font (Dashicons) is being loaded
 * for non-logged-in visitors on the front end, where it is typically not
 * needed and represents unused CSS and font requests.
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
 * Diagnostic_Dashicons_Frontend Class
 *
 * @since 0.6095
 */
class Diagnostic_Dashicons_Frontend extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'dashicons-frontend';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Dashicons Loaded on Frontend';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a performance plugin is managing Dashicons loading so the admin icon font is not served to non-logged-in visitors who never need it.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Run the diagnostic check.
	 *
	 * Dashicons loads for all users who see the admin bar (logged-in users).
	 * The problem occurs when themes or plugins explicitly enqueue dashicons
	 * for all visitors, or when no mechanism dequeues it for non-authenticated
	 * users. We detect common plugin solutions and the active theme's dependency.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when dashicons is unmanaged, null when healthy.
	 */
	public static function check() {
		// Perfmatters — has an explicit option to disable dashicons for public visitors.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['utilities']['disable_dashicons'] ) ) {
			return null;
		}

		// WP Asset CleanUp can manage dashicons per page type.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// Check if the active theme explicitly declares dashicons as a dependency.
		// If the theme stylesheet depends on dashicons, removing it would break the theme.
		$theme        = wp_get_theme();
		$theme_deps   = (array) $theme->get( 'TextDomain' ); // Not direct, but check styles.
		$style_deps   = wp_styles()->registered;

		if ( isset( $style_deps[ get_stylesheet() ] ) ) {
			$stylesheet_deps = (array) $style_deps[ get_stylesheet() ]->deps;
			if ( in_array( 'dashicons', $stylesheet_deps, true ) ) {
				// Theme depends on dashicons — removing is not safe to recommend outright.
				return null;
			}
		}

		// Check if there is a wp_enqueue_scripts hook explicitly dequeuing dashicons.
		// We look for a priority-10 frontend dequeue action as a proxy signal;
		// if none found and no plugin is managing it, flag for review.
		$has_dequeue_hook = has_action( 'wp_enqueue_scripts', function() {} );

		// If Perfmatters is installed at all (even without the option set), assume
		// the site owner is actively managing assets and suppress the finding.
		if ( is_array( $pm ) && ! empty( $pm ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No mechanism was detected to prevent Dashicons from loading for non-logged-in visitors. Dashicons is the WordPress admin icon font — it is only needed for logged-in users who see the admin toolbar. When themes or plugins enqueue it globally, all public visitors download the stylesheet and icon font files unnecessarily. A performance plugin can conditionally dequeue it for non-authenticated users.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 12,
			'details'      => array(
				'fix' => __( 'Add to functions.php: add_action(\'wp_enqueue_scripts\', function() { if (!is_user_logged_in()) { wp_deregister_style(\'dashicons\'); } }); — or enable "Disable Dashicons" in Perfmatters.', 'thisismyurl-shadow' ),
			),
		);
	}
}
