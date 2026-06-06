<?php
/**
 * RSS Feed Autodiscovery Links Diagnostic
 *
 * Checks whether WordPress is still injecting RSS autodiscovery <link> tags
 * into every page's <head> via the feed_links() and feed_links_extra()
 * core functions. These tags tell browsers and feed readers where to find
 * your main post feed, comments feed, category feeds, and author feeds.
 *
 * Most modern sites have no need to broadcast these endpoints in HTML.
 * Removing them reduces head bloat and limits passive fingerprinting of
 * the blog's feed structure.
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
 * Diagnostic_Rss_Head_Links Class
 *
 * @since 0.6095
 */
class Diagnostic_Rss_Head_Links extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'rss-head-links';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'RSS Feed Autodiscovery Links in Head';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is injecting RSS autodiscovery <link> tags into every page that broadcast your feed URLs to browsers and feed readers — unnecessary for sites that do not actively promote an RSS subscription audience.';

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
	 * Checks whether feed_links and/or feed_links_extra are still hooked to
	 * wp_head at their default priorities (2 and 3), indicating that RSS
	 * autodiscovery tags are being injected into every page's <head>.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when links are still output, null when healthy.
	 */
	public static function check() {
		// Perfmatters can suppress feed links under its "Extras" cleanup options.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_feeds'] ) ) {
			return null;
		}

		// WP Asset CleanUp handles head tag cleanup.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// If both feed_links and feed_links_extra have been deregistered, the
		// autodiscovery tags are no longer output — consider this healthy.
		$feed_links_present       = (bool) has_action( 'wp_head', 'feed_links' );
		$feed_links_extra_present = (bool) has_action( 'wp_head', 'feed_links_extra' );

		if ( ! $feed_links_present && ! $feed_links_extra_present ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress outputs RSS autodiscovery <link> tags in every page\'s <head>. These tags advertise your main post feed, comments feed, and category feeds to browsers and feed readers. For most business and brochure sites with no active blog subscriber strategy, they serve no practical purpose. They add unnecessary bytes to every page response and reveal part of your site\'s content structure to automated scanners.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 5,
			'details'      => array(
				'fix' => __( 'Add to functions.php: remove_action(\'wp_head\', \'feed_links\', 2); remove_action(\'wp_head\', \'feed_links_extra\', 3); — or use Perfmatters / WP Asset CleanUp to remove head tags.', 'thisismyurl-shadow' ),
			),
		);
	}
}
