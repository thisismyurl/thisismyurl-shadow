<?php
/**
 * Meta Titles Managed Diagnostic
 *
 * Checks whether an SEO plugin is active to manage meta title tags for posts
 * and pages, and whether core title templates have been configured.
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
 * Diagnostic_Meta_Titles_Managed Class
 *
 * Scans active plugins for recognised SEO tools and, when Yoast SEO is found,
 * verifies that the homepage title template has been configured.
 *
 * @since 0.6095
 */
class Diagnostic_Meta_Titles_Managed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'meta-titles-managed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Meta Titles Managed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an SEO plugin is active to manage meta title tags for posts and pages, rather than relying on the theme\'s default title output.';

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
	 * Inspects the active_plugins option for recognised SEO plugins. Returns a
	 * high-severity finding when none are found. When Yoast SEO is active,
	 * additionally checks the wpseo_titles option for a homepage title template
	 * and returns a low-severity finding if it is empty.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when an issue is detected, null when healthy.
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
				'description'  => __( 'No SEO plugin is managing meta title templates. Without controlled title templates, WordPress outputs the post title followed by the site name with no keyword optimisation or format control. Install an SEO plugin such as Yoast SEO or Rank Math to define title templates for posts, pages, archives, and the homepage.', 'thisismyurl-shadow' ),
				'severity'     => 'high',
				'threat_level' => 55,
				'details'      => array( 'seo_plugin_active' => false ),
			);
		}

		// Yoast: check if home title template has been customised.
		$has_yoast = in_array( 'wordpress-seo/wp-seo.php', $active_plugins, true )
		          || in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active_plugins, true );

		if ( $has_yoast ) {
			$titles = get_option( 'wpseo_titles', array() );
			if ( empty( $titles['title-home-wpseo'] ) ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => __( 'Yoast SEO is active but no homepage title template has been set. Configure the homepage title template in Yoast SEO → Search Appearance → General to control how your home page appears in search results.', 'thisismyurl-shadow' ),
					'severity'     => 'low',
					'threat_level' => 20,
					'details'      => array( 'seo_plugin_active' => true, 'home_title_template' => '' ),
				);
			}
		}

		return null;
	}
}
