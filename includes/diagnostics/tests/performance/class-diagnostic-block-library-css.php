<?php
/**
 * Block Library CSS on Classic Themes Diagnostic
 *
 * Checks whether the WordPress block library CSS (wp-block-library) is being
 * loaded on a classic (non-FSE) theme's front end, where most block styles
 * are unused and represent wasted CSS weight.
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
 * Diagnostic_Block_Library_Css Class
 *
 * @since 0.6095
 */
class Diagnostic_Block_Library_Css extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'block-library-css';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Block Library CSS on Classic Theme';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress block library stylesheet is loading on a classic (non-block) theme, where most of its styles are unused and represent unnecessary CSS weight on every page.';

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
	 * Only relevant when the active theme is a classic PHP theme (not an FSE/
	 * block theme). On block themes, wp-block-library CSS is required. On
	 * classic themes, it is loaded by default but typically not needed for
	 * site content, and can be safely removed.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when unneeded block CSS is unmanaged, null when healthy.
	 */
	public static function check() {
		// If the active theme is a block/FSE theme, block library CSS is required.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return null;
		}

		// Older compatibility check for themes declaring block-templates support.
		if ( current_theme_supports( 'block-templates' ) ) {
			return null;
		}

		// Perfmatters has a dedicated option to remove block CSS on classic themes.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['assets']['remove_block_css'] ) ) {
			return null;
		}

		// WP Rocket's Remove Unused CSS feature would handle this.
		$rocket = get_option( 'wp_rocket_settings', array() );
		if ( is_array( $rocket ) && ! empty( $rocket['remove_unused_css'] ) ) {
			return null;
		}

		// WP Asset CleanUp can dequeue on a per-page basis.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// No known management plugin detected — flag that block CSS is loading
		// on a classic theme with no apparent management in place.
		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your site uses a classic (non-block) theme, but WordPress is loading the block library stylesheet (wp-block-library) on every front-end page by default. This stylesheet contains styles for the block editor\'s core blocks. On classic themes built with PHP templates, most of these styles are never used, yet visitors must download the file on every page load. Removing it reduces CSS weight with no visible impact on site appearance.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'fix' => __( 'Add to functions.php: add_action(\'wp_enqueue_scripts\', function() { wp_dequeue_style(\'wp-block-library\'); wp_dequeue_style(\'wp-block-library-theme\'); wp_dequeue_style(\'global-styles\'); }, 100); — or use Perfmatters\' "Remove Block CSS" option.', 'thisismyurl-shadow' ),
			),
		);
	}
}
