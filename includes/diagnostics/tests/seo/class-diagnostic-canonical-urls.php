<?php
/**
 * Canonical URLs Diagnostic
 *
 * Checks whether an SEO plugin is active to manage canonical URL tags and
 * prevent duplicate content from harming search engine rankings.
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
 * Diagnostic_Canonical_Urls Class
 *
 * Checks for active SEO plugins that are known to output canonical link
 * tags, flagging sites that have no such plugin managing deduplication.
 *
 * @since 0.6095
 */
class Diagnostic_Canonical_Urls extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'canonical-urls';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Canonical URLs';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an SEO plugin is active to manage canonical URL tags and prevent duplicate content from harming search engine rankings.';

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
	 * Checks for active SEO plugins that output canonical tags and verifies
	 * that the rel_canonical action is still hooked in wp_head, flagging sites
	 * where canonical URL management is absent or incomplete.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when canonical management is absent, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$seo_plugins = array(
			'wordpress-seo/wp-seo.php',
			'wordpress-seo-premium/wp-seo-premium.php',
			'seo-by-rank-math/rank-math.php',
			'seo-by-rank-math-pro/rank-math-pro.php',
			'all-in-one-seo-pack/all_in_one_seo_pack.php',
			'all-in-one-seo-pack-pro/all_in_one_seo_pack.php',
			'wp-seopress/seopress.php',
			'wp-seopress-pro/seopress-pro.php',
			'the-seo-framework/the-seo-framework.php',
		);

		foreach ( $seo_plugins as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				// SEO plugin handles canonicals — assume coverage is good.
				return null;
			}
		}

		// No SEO plugin: check if WordPress core rel_canonical is still hooked.
		if ( ! has_action( 'wp_head', 'rel_canonical' ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'Canonical URL output has been removed from wp_head and no SEO plugin is replacing it. Without canonical tags, search engines cannot determine the preferred URL for each page, leading to duplicate-content dilution. Restore canonical output by re-adding rel_canonical to wp_head, or install an SEO plugin to manage canonical URLs.', 'thisismyurl-shadow' ),
				'severity'     => 'high',
				'threat_level' => 60,
				'details'      => array(
					'rel_canonical_hooked' => false,
					'seo_plugin_active'    => false,
				),
			);
		}

		// WordPress core canonical is present but offers limited scope (single posts/pages only).
		// Flag as low to recommend an SEO plugin for full coverage.
		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Canonical URLs are output by WordPress core but no SEO plugin is active to provide comprehensive canonical management. Core canonicals only cover single posts and pages; archive pages, paginated views, and cross-domain scenarios require an SEO plugin such as Yoast SEO or Rank Math for full coverage.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'rel_canonical_hooked' => true,
				'seo_plugin_active'    => false,
			),
		);
	}
}
