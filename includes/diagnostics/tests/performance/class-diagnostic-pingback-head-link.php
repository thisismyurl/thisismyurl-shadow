<?php
/**
 * Pingback Endpoint Disclosure Diagnostic
 *
 * Checks whether WordPress is still injecting a <link rel="pingback"> tag
 * into every page's <head> and/or adding an X-Pingback HTTP response header.
 * Both outputs advertise your xmlrpc.php endpoint URL to every visitor and
 * automated scanner, regardless of whether pingbacks are actually enabled.
 *
 * Note: the existing pingbacks-trackbacks diagnostic checks whether pings are
 * enabled by default for new posts. This is a distinct, complementary check —
 * WordPress outputs the head tag and HTTP header unconditionally, even when
 * default_ping_status is set to "closed".
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
 * Diagnostic_Pingback_Head_Link Class
 *
 * @since 0.6095
 */
class Diagnostic_Pingback_Head_Link extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'pingback-head-link';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Pingback Endpoint Disclosed in Head and Headers';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is advertising your xmlrpc.php endpoint via a <link rel="pingback"> tag in every page\'s <head> and via an X-Pingback HTTP response header — both active even when pingbacks are disabled.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Checks whether pingback_url is still hooked to wp_head (head link) and
	 * whether wp_headers_pingback is still hooked to wp_headers (HTTP header).
	 * Returns null if both outputs have already been suppressed by Shadow by Christopher Ross,
	 * the existing treatment, or a third-party plugin.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when disclosure endpoints are present, null when healthy.
	 */
	public static function check() {
		// Perfmatters' XML-RPC disable option removes pingback head and headers.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_xmlrpc'] ) ) {
			return null;
		}

		// WP Asset CleanUp handles head tag cleanup.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		$has_head_link  = (bool) has_action( 'wp_head', 'pingback_url' );

		// The X-Pingback header is only added when default_ping_status is open.
		// Check whether the filter is registered AND pings are open.
		$has_header = has_filter( 'wp_headers', 'wp_headers_pingback' )
			&& ( 'open' === get_option( 'default_ping_status', 'open' ) );

		if ( ! $has_head_link && ! $has_header ) {
			return null;
		}

		$details = array(
			'pingback_head_link_present'  => $has_head_link,
			'x_pingback_header_present'   => $has_header,
		);

		$details['fix'] = __( 'Add to functions.php: remove_action(\'wp_head\', \'pingback_url\'); remove_filter(\'wp_headers\', \'wp_headers_pingback\'); — or use Perfmatters\' "Disable XML-RPC" option.', 'thisismyurl-shadow' );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress outputs a <link rel="pingback"> tag in every page\'s <head> and adds an X-Pingback: HTTP response header. Both advertise the full URL of your xmlrpc.php endpoint to any visitor or automated scanner — even when pingbacks are turned off for new posts. Removing them does not disable the xmlrpc.php endpoint itself; it only stops WordPress from broadcasting its location on every page.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 25,
			'details'      => $details,
		);
	}
}
