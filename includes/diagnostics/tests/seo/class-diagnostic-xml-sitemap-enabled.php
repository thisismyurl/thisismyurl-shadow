<?php
/**
 * XML Sitemap Enabled Diagnostic
 *
 * Checks whether an XML sitemap is active and accessible to help search engines
 * discover and efficiently index all published content on the site.
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
 * Diagnostic_Xml_Sitemap_Enabled Class
 *
 * Verifies that the WordPress core sitemap server is active or that a
 * recognised SEO/sitemap plugin is installed and has registered its options.
 *
 * @since 0.6095
 */
class Diagnostic_Xml_Sitemap_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'xml-sitemap-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'XML Sitemap Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an XML sitemap is active and accessible to help search engines discover and efficiently index all published content on the site.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'seo';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * First checks whether the WordPress core sitemap server is available and
	 * the wp_sitemaps_enabled filter is passing. Falls back to checking well-
	 * known SEO plugin options (Yoast, Rank Math, SEOPress, AIOSEO, Squirrly,
	 * Google XML Sitemaps). Returns null when any sitemap source is found, or
	 * a medium-severity finding when none are detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no sitemap is found, null when healthy.
	 */
	public static function check() {
		// Check if WordPress core sitemap is active (WP 5.5+).
		// The core sitemap can be disabled by the wp_sitemaps_enabled filter.
		$core_sitemap_url = '';
		if ( function_exists( 'wp_sitemaps_get_server' ) ) {
			$server = wp_sitemaps_get_server();
			if ( $server instanceof \WP_Sitemaps && apply_filters( 'wp_sitemaps_enabled', true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reading a WordPress core filter's value for diagnostics, not defining a plugin hook.
				// Core sitemap is active.
				return null;
			}
		}

		// Check for known SEO plugins that provide XML sitemaps.
		$sitemap_plugin_options = array(
			'wpseo_xml'              => 'Yoast SEO',
			'rank_math_modules'      => 'Rank Math',
			'seopress_xml_sitemap'   => 'SEOPress',
			'squirrly_seo'           => 'Squirrly SEO',
			'all_in_one_seo_pack'    => 'All in One SEO',
		);

		foreach ( $sitemap_plugin_options as $option => $plugin_name ) {
			if ( false !== get_option( $option, false ) ) {
				return null;
			}
		}

		// Google XML Sitemaps plugin.
		if ( defined( 'GOOGLESITEMAP_VERSION' ) || get_option( 'sm_options', false ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No XML sitemap was detected. An XML sitemap helps search engines discover and index all pages on your site. WordPress includes a built-in sitemap since version 5.5, or you can use an SEO plugin such as Yoast SEO or Rank Math to generate a more detailed one.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'core_sitemap_active' => false,
				'seo_plugin_detected' => false,
			),
		);
	}
}
