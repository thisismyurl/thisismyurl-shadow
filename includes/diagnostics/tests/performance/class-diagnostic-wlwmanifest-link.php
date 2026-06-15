<?php
/**
 * Windows Live Writer Manifest Link Diagnostic
 *
 * Checks whether WordPress is still injecting a <link rel="wlwmanifest">
 * tag into every page's <head>. Windows Live Writer was discontinued by
 * Microsoft in 2017 and the tag serves no purpose on modern sites.
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
 * Diagnostic_Wlwmanifest_Link Class
 *
 * @since 0.6095
 */
class Diagnostic_Wlwmanifest_Link extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'wlwmanifest-link';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Windows Live Writer Manifest Link';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is injecting a <link rel="wlwmanifest"> tag into every page that was designed for Windows Live Writer, a blogging client Microsoft discontinued in 2017.';

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
	 * Checks whether the wlwmanifest_link hook is still registered on wp_head
	 * at its default priority, indicating the legacy tag is being output.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when link is still output, null when healthy.
	 */
	public static function check() {
		// Perfmatters can remove this under its "Extras" cleanup options.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_wlwmanifest_link'] ) ) {
			return null;
		}

		// WP Asset CleanUp handles this.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// Definitive check: if the hook has been removed, the link is not output.
		if ( ! has_action( 'wp_head', 'wlwmanifest_link' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress outputs a <link rel="wlwmanifest"> tag in every page\'s <head>. This link was designed for Windows Live Writer, a desktop blogging client that Microsoft discontinued in 2017. No modern blogging workflow uses it. It is dead weight in your HTML that can be safely removed.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 5,
			'details'      => array(
				'fix' => __( 'Add to functions.php: remove_action(\'wp_head\', \'wlwmanifest_link\'); — or use Perfmatters / WP Asset CleanUp to remove legacy head tags.', 'thisismyurl-shadow' ),
			),
		);
	}
}
