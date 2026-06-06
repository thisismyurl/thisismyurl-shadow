<?php
/**
 * Shortlink Head Tag Diagnostic
 *
 * Checks whether WordPress is still injecting a <link rel="shortlink"> tag
 * into every page's <head>. Shortlinks were a sharing convenience before
 * URL shorteners became universal; they serve no modern purpose.
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
 * Diagnostic_Shortlink_Head_Tag Class
 *
 * @since 0.6095
 */
class Diagnostic_Shortlink_Head_Tag extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'shortlink-head-tag';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Shortlink Tag in Head';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is injecting a <link rel="shortlink"> tag into every page, a legacy sharing feature that predates modern URL shorteners and adds unnecessary overhead.';

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
	 * Checks whether wp_shortlink_wp_head is still hooked to wp_head at its
	 * default priority of 10.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when shortlink tag is still output, null when healthy.
	 */
	public static function check() {
		// Perfmatters handles this under misc cleanup options.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_shortlink'] ) ) {
			return null;
		}

		// WP Asset CleanUp handles this.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// Definitive check: hook removed means the tag is not output.
		if ( ! has_action( 'wp_head', 'wp_shortlink_wp_head' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress outputs a <link rel="shortlink" href="/?p=123"> tag in every page\'s <head>. Shortlinks were useful for sharing before URL shorteners like bit.ly existed. Today they offer no benefit to visitors, search engines, or social platforms, and they use the old numeric /?p= URL format that you likely moved away from when you set up meaningful permalinks. It can be safely removed.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 5,
			'details'      => array(
				'fix' => __( 'Add to functions.php: remove_action(\'wp_head\', \'wp_shortlink_wp_head\', 10); remove_action(\'template_redirect\', \'wp_shortlink_header\', 11); — or use Perfmatters to remove it.', 'thisismyurl-shadow' ),
			),
		);
	}
}
