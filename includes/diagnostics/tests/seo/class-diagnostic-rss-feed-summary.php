<?php
/**
 * RSS Feed Summary Diagnostic
 *
 * Checks whether the RSS feed is configured to output excerpts rather than
 * full post content, reducing content scraping and preserving reader engagement.
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
 * Diagnostic_Rss_Feed_Summary Class
 *
 * Reads the rss_use_excerpt option and flags sites outputting full post content
 * in their feeds, which can lead to duplicate-content SEO issues.
 *
 * @since 0.6095
 */
class Diagnostic_Rss_Feed_Summary extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'rss-feed-summary';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'RSS Feed Summary';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the RSS feed is configured to output excerpts rather than full post content, reducing content scraping and preserving reader engagement on the site.';

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
	 * Reads the rss_use_excerpt WordPress option (0 = full text, 1 = excerpt).
	 * Returns a low-severity finding when full-text output is enabled, as this
	 * allows scrapers to republish content verbatim and creates duplicate-content
	 * risks. Returns null when the feed is already set to summary/excerpt mode.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when full-text feeds are enabled, null when healthy.
	 */
	public static function check() {
		$rss_use_excerpt = (int) get_option( 'rss_use_excerpt', 0 );

		// 0 = full text, 1 = summary/excerpt.
		if ( 0 === $rss_use_excerpt ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'RSS feeds are set to output the full text of each post. This allows content scrapers to republish your content verbatim, creating duplicate-content issues that can dilute your SEO rankings. Consider switching to summaries under Settings → Reading → "For each article in a feed, include" → Summary.', 'thisismyurl-shadow' ),
				'severity'     => 'low',
				'threat_level' => 20,
				'details'      => array(
					'rss_use_excerpt' => 0,
				),
			);
		}

		return null;
	}
}
