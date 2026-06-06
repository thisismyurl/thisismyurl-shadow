<?php
/**
 * REST API Head Link Diagnostic
 *
 * Checks whether WordPress is injecting a <link rel="https://api.w.org/">
 * discovery tag into every page's <head>, which publicly advertises the
 * REST API endpoint URL to anyone reading page source.
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
 * Diagnostic_Rest_Api_Head_Link Class
 *
 * @since 0.6095
 */
class Diagnostic_Rest_Api_Head_Link extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'rest-api-head-link';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'REST API Discovery Link in Head';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is injecting a REST API discovery link into every page that publicly advertises the REST API endpoint URL in page source code to scanners and bots.';

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
	 * Checks whether the rest_output_link_wp_head hook is still registered on
	 * wp_head at its default priority of 10, indicating the discovery tag is
	 * still being output on every page.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when link is still output, null when healthy.
	 */
	public static function check() {
		// Perfmatters has a disable_rest_api option that also removes the head link.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_rest_api'] ) ) {
			return null;
		}

		// WP Asset CleanUp handles this.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// Definitive check: hook removed means the link is not output.
		if ( ! has_action( 'wp_head', 'rest_output_link_wp_head' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress injects a <link rel="https://api.w.org/"> discovery tag into every page\'s <head>. This tag broadcasts your REST API endpoint URL to anyone viewing the page source. Most front-end visitors and search engines have no use for it, and it makes automated REST API probing slightly easier for bots. Removing it does not disable the REST API — it simply stops advertising the endpoint in HTML.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 8,
			'details'      => array(
				'fix' => __( 'Add to functions.php: remove_action(\'wp_head\', \'rest_output_link_wp_head\', 10); — or use Perfmatters to manage REST API exposure.', 'thisismyurl-shadow' ),
			),
		);
	}
}
