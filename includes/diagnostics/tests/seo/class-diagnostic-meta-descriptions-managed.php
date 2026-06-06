<?php
/**
 * Meta Descriptions Managed Diagnostic
 *
 * Checks whether an SEO plugin is active to manage meta description tags,
 * which directly influence click-through rates from search result pages.
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
 * Diagnostic_Meta_Descriptions_Managed Class
 *
 * Scans active plugins for well-known SEO tools that provide meta description
 * template management, flagging sites where no such plugin is active.
 *
 * @since 0.6095
 */
class Diagnostic_Meta_Descriptions_Managed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'meta-descriptions-managed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Meta Descriptions Managed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an SEO plugin is active to manage meta description tags, which directly influence click-through rates from search result pages.';

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
	 * Checks the active_plugins option for recognised SEO plugins that are
	 * known to manage meta description templates, returning a medium-severity
	 * finding when none are detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no SEO plugin is active, null when healthy.
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

		$seo_plugin_active = false;
		foreach ( $seo_plugins as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$seo_plugin_active = true;
				break;
			}
		}

		if ( ! $seo_plugin_active ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'No SEO plugin is managing meta description templates. Without a strategy for meta descriptions, search engines generate their own snippet text, which is often poorly matched to the page\'s purpose and reduces click-through rates. Install an SEO plugin such as Yoast SEO or Rank Math to define and manage meta description templates.', 'thisismyurl-shadow' ),
				'severity'     => 'medium',
				'threat_level' => 45,
				'details'      => array( 'seo_plugin_active' => false ),
			);
		}

		return null;
	}
}
