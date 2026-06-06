<?php
/**
 * RSD (Really Simple Discovery) Link Diagnostic
 *
 * Checks whether WordPress is still injecting a <link rel="EditURI"> RSD
 * tag into every page's <head>. RSD was used by desktop blogging clients
 * from the early 2000s and is obsolete on all modern sites.
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
 * Diagnostic_Rsd_Link Class
 *
 * @since 0.6095
 */
class Diagnostic_Rsd_Link extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'rsd-link';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'RSD (Really Simple Discovery) Link';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is injecting a <link rel="EditURI"> Really Simple Discovery tag into every page, a protocol designed for mid-2000s desktop blogging clients that no longer exist.';

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
	 * Checks whether the rsd_link hook is still registered on wp_head at its
	 * default priority the tag is still being output.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when link is still output, null when healthy.
	 */
	public static function check() {
		// Perfmatters can remove legacy head tags.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_rsd_link'] ) ) {
			return null;
		}

		// WP Asset CleanUp handles this.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// Definitive check: if the hook has been removed, the link is not output.
		if ( ! has_action( 'wp_head', 'rsd_link' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress outputs a <link rel="EditURI" type="application/rsd+xml"> tag in every page\'s <head>. Really Simple Discovery (RSD) was a protocol for desktop blogging applications from the early 2000s. None of those applications are in common use today and the tag adds unnecessary bytes to every page load. It can be safely removed.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 5,
			'details'      => array(
				'fix' => __( 'Add to functions.php: remove_action(\'wp_head\', \'rsd_link\'); — or use Perfmatters / WP Asset CleanUp to remove legacy head tags.', 'thisismyurl-shadow' ),
			),
		);
	}
}
