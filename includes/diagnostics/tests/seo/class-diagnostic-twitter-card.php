<?php
/**
 * Twitter Card Diagnostic
 *
 * Checks whether Twitter Card meta tags are being output so links shared on
 * X (Twitter) display with a proper image and summary.
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
 * Diagnostic_Twitter_Card Class
 *
 * Checks active plugins for tools known to output twitter:card meta tags,
 * flagging sites where no capable plugin is detected.
 *
 * @since 0.6095
 */
class Diagnostic_Twitter_Card extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'twitter-card';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Twitter Card';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether Twitter Card meta tags are being output so links shared on Twitter and social media display with a proper image and summary.';

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
	 * Scans active_plugins for recognised SEO plugins (Yoast SEO, AIOSEO, Rank
	 * Math, SEOPress) and Jetpack, all of which can output Twitter/X card meta
	 * tags. Returns null immediately when any match is found. Returns a
	 * low-severity finding when none are detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no Twitter card plugin is active, null when healthy.
	 */
	public static function check() {
		// Twitter/X card tags are almost always provided by an SEO plugin.
		// Detect known plugins that output twitter:card meta tags.
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$seo_plugins_with_twitter = array(
			'wordpress-seo/wp-seo.php'                => 'wpseo_social',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'aioseo_social_networks',
			'rank-math/rank-math.php'                  => 'rank_math_modules',
			'seopress/seopress.php'                    => 'seopress_social_option_name',
			'seopress-pro/seopress-pro.php'            => 'seopress_social_option_name',
		);

		foreach ( $seo_plugins_with_twitter as $plugin_file => $option_key ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				// Plugin is active — assume it manages Twitter cards.
				return null;
			}
		}

		// Check if JetPack sharing/publicise is active (handles OG/Twitter metadata).
		if ( in_array( 'jetpack/jetpack.php', $active_plugins, true ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No SEO plugin capable of generating Twitter/X card metadata was detected. Without twitter:card, twitter:title, and twitter:image tags, links shared on X display with minimal formatting and no image preview. Install an SEO plugin such as Yoast SEO or Rank Math and enable its social metadata features.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'seo_plugin_detected' => false,
			),
		);
	}
}
