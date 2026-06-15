<?php
/**
 * Open Graph Defaults Set Diagnostic
 *
 * Checks whether Open Graph meta tags are being output so links shared on
 * social media display with proper titles, descriptions, and preview images.
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
 * Diagnostic_Open_Graph_Defaults_Set Class
 *
 * Verifies that a recognised SEO plugin is active with Open Graph enabled and,
 * for Yoast SEO, checks that a fallback social image has been configured.
 *
 * @since 0.6095
 */
class Diagnostic_Open_Graph_Defaults_Set extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'open-graph-defaults-set';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Open Graph Defaults Set';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether Open Graph meta tags are being output so links shared on social media display with proper titles, descriptions, and preview images.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Checks whether a recognised SEO plugin is active with Open Graph support.
	 * For Yoast SEO, verifies that Open Graph is enabled and that a default
	 * fallback image is configured. Rank Math, AIOSEO, and SEOPress enable OG
	 * by default and are treated as healthy if active. Returns a medium-severity
	 * finding when no OG-capable plugin is active, a medium finding when OG is
	 * disabled in Yoast, or a low finding when OG is enabled but has no fallback image.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when an issue is detected, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$has_yoast    = in_array( 'wordpress-seo/wp-seo.php', $active_plugins, true )
		             || in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active_plugins, true );
		$has_rankmath = in_array( 'seo-by-rank-math/rank-math.php', $active_plugins, true )
		             || in_array( 'seo-by-rank-math-pro/rank-math-pro.php', $active_plugins, true );
		$has_aioseo   = in_array( 'all-in-one-seo-pack/all_in_one_seo_pack.php', $active_plugins, true )
		             || in_array( 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php', $active_plugins, true );
		$has_seopress = in_array( 'wp-seopress/seopress.php', $active_plugins, true )
		             || in_array( 'wp-seopress-pro/seopress-pro.php', $active_plugins, true );

		if ( $has_yoast ) {
			$social  = get_option( 'wpseo_social', array() );
			$og_on   = ! empty( $social['opengraph'] );
			$og_img  = ! empty( $social['og_default_image'] );

			if ( ! $og_on ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => __( 'Open Graph is disabled in Yoast SEO. Without Open Graph tags, shared links on Facebook, LinkedIn, and other platforms will not display a title, description, or image. Enable Open Graph in Yoast SEO → Social → Facebook.', 'thisismyurl-shadow' ),
					'severity'     => 'medium',
					'threat_level' => 40,
					'details'      => array( 'opengraph_enabled' => false, 'default_image_set' => false ),
				);
			}

			if ( ! $og_img ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => __( 'Open Graph is enabled in Yoast SEO but no default fallback image has been set. Posts and pages without a featured image will share without a social image, appearing as link-only previews. Set a default image in Yoast SEO → Social → Facebook → Default image.', 'thisismyurl-shadow' ),
					'severity'     => 'low',
					'threat_level' => 20,
					'details'      => array( 'opengraph_enabled' => true, 'default_image_set' => false ),
				);
			}

			return null;
		}

		if ( $has_rankmath || $has_aioseo || $has_seopress ) {
			// These plugins enable OG by default; assume configured if active.
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No plugin managing Open Graph meta tags is active. Without Open Graph (og:title, og:description, og:image), links shared on social media will display generic or missing previews. Install an SEO plugin such as Yoast SEO or Rank Math to manage Open Graph tags.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array( 'opengraph_enabled' => null, 'default_image_set' => null ),
		);
	}
}
