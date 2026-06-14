<?php
/**
 * Search Page Indexing Diagnostic
 *
 * Checks whether WordPress internal search result pages are excluded from
 * search engine indexing to prevent duplicate or low-value pages from crawling.
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
 * Diagnostic_Search_Page_Indexing Class
 *
 * Checks Yoast SEO and Rank Math settings to verify search result pages are
 * set to noindex, and flags sites with no SEO plugin managing this setting.
 *
 * @since 0.6095
 */
class Diagnostic_Search_Page_Indexing extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'search-page-indexing';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Search Page Indexing';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress internal search result pages are excluded from search engine indexing to prevent duplicate or low-value pages from being crawled.';

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
	 * Verifies Yoast SEO noindex-search-wpseo or Rank Math noindex_search option
	 * is enabled. Returns a medium-severity finding if a recognised plugin has
	 * explicitly disabled noindex for search pages, or if no SEO plugin is active
	 * to manage this setting at all.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when search pages may be indexed, null when healthy.
	 */
	public static function check() {
		if ( (bool) get_option( 'thisismyurl_shadow_search_page_noindex_enabled', false ) ) {
			return null;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );

		$has_yoast    = in_array( 'wordpress-seo/wp-seo.php', $active_plugins, true )
		             || in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active_plugins, true );
		$has_rankmath = in_array( 'seo-by-rank-math/rank-math.php', $active_plugins, true )
		             || in_array( 'seo-by-rank-math-pro/rank-math-pro.php', $active_plugins, true );

		if ( $has_yoast ) {
			$titles = get_option( 'wpseo_titles', array() );
			// Yoast noindex-search-wpseo defaults to true (1); only flag if explicitly set to false.
			if ( isset( $titles['noindex-search-wpseo'] ) && ! $titles['noindex-search-wpseo'] ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => __( 'Yoast SEO has been configured to allow search result pages to be indexed. Search results are thin, duplicate-content pages that can harm your SEO. Re-enable the noindex setting for search pages in Yoast SEO → Search Appearance → Archives.', 'thisismyurl-shadow' ),
					'severity'     => 'medium',
					'threat_level' => 45,
					'details'      => array( 'noindex_search' => false, 'plugin' => 'Yoast SEO' ),
				);
			}
			return null;
		}

		if ( $has_rankmath ) {
			$general = get_option( 'rank_math_settings_general', array() );
			$noindex = isset( $general['noindex_search'] ) ? (bool) $general['noindex_search'] : true;
			if ( ! $noindex ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => __( 'Rank Math has been configured to allow search result pages to be indexed. Search result pages are thin-content pages that can dilute your site\'s SEO value. Enable the noindex option for search pages in Rank Math → Titles & Meta → Search Results.', 'thisismyurl-shadow' ),
					'severity'     => 'medium',
					'threat_level' => 45,
					'details'      => array( 'noindex_search' => false, 'plugin' => 'Rank Math' ),
				);
			}
			return null;
		}

		// No recognised SEO plugin managing search page indexing.
		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No SEO plugin or Shadow by Christopher Ross native rule is managing the noindex status of internal search result pages. Search result pages (/?s=...) are thin-content pages that should be excluded from search engine indexes. Install an SEO plugin such as Yoast SEO or Rank Math, or enable a native noindex rule, so search pages stay out of Google.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array( 'noindex_search' => null, 'plugin' => null, 'native_rule' => false ),
		);
	}
}
