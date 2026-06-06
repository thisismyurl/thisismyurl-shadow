<?php
/**
 * SEO Plugin Configuration Diagnostic
 *
 * Checks whether an SEO plugin is installed and configured on the site, as
 * managing meta tags, schema, and canonical URLs requires dedicated SEO tooling.
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
 * Diagnostic_SEO_Plugin_Config_Intentional Class
 *
 * Scans active plugins for recognised SEO tools and checks whether the detected
 * plugin has received at least minimal configuration (setup wizard completion).
 *
 * @since 0.6095
 */
class Diagnostic_SEO_Plugin_Config_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'seo-plugin-config-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'SEO Plugin Configuration';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an SEO plugin is installed on the site, as managing meta tags, schema, and canonical URLs requires dedicated SEO tooling.';

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
	 * Iterates active_plugins against a curated list of recognised SEO tools. When
	 * no plugin is found, returns a high-severity finding. When a plugin is found,
	 * checks for basic configuration signals: Yoast's home title template, Rank
	 * Math module list, or AIOSEO settings. Returns a medium finding if the plugin
	 * appears unconfigured, null when an SEO plugin is active and configured.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when an issue is detected, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$seo_plugins = array(
			'wordpress-seo/wp-seo.php'                    => 'Yoast SEO',
			'wordpress-seo-premium/wp-seo-premium.php'    => 'Yoast SEO Premium',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
			'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' => 'All in One SEO Pro',
			'seo-by-rank-math/rank-math.php'              => 'Rank Math',
			'seo-by-rank-math-pro/rank-math-pro.php'      => 'Rank Math Pro',
			'wp-seopress/seopress.php'                    => 'SEOPress',
			'wp-seopress-pro/seopress-pro.php'            => 'SEOPress Pro',
			'squirrly-seo/squirrly.php'                   => 'Squirrly SEO',
			'the-seo-framework/the-seo-framework.php'     => 'The SEO Framework',
		);

		$found_plugin = null;
		foreach ( $seo_plugins as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$found_plugin = $plugin_name;
				break;
			}
		}

		if ( null === $found_plugin ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'No recognised SEO plugin is active. Without an SEO plugin, key elements such as meta titles, meta descriptions, Open Graph tags, XML sitemaps, and schema markup are unmanaged. Install and configure a plugin such as Yoast SEO or Rank Math.', 'thisismyurl-shadow' ),
				'severity'     => 'high',
				'threat_level' => 55,
				'details'      => array(
					'active_seo_plugin' => null,
				),
			);
		}

		// Plugin found — check for a minimal configuration signal.
		$configured = false;
		if ( 'Yoast SEO' === $found_plugin || 'Yoast SEO Premium' === $found_plugin ) {
			$titles = get_option( 'wpseo_titles', array() );
			$configured = ! empty( $titles['title-home-wpseo'] );
		} elseif ( strpos( $found_plugin, 'Rank Math' ) !== false ) {
			$modules = get_option( 'rank_math_modules', array() );
			$configured = ! empty( $modules );
		} elseif ( strpos( $found_plugin, 'All in One SEO' ) !== false ) {
			$aioseo = get_option( 'aioseo_settings', array() );
			$configured = ! empty( $aioseo );
		} else {
			// For other plugins assume active = configured.
			$configured = true;
		}

		if ( ! $configured ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %s: plugin name */
					__( '%s is active but does not appear to have been configured. Complete the plugin\'s setup wizard to ensure meta titles, descriptions, and sitemaps are properly managed.', 'thisismyurl-shadow' ),
					$found_plugin
				),
				'severity'     => 'medium',
				'threat_level' => 40,
				'details'      => array(
					'active_seo_plugin' => $found_plugin,
					'configured'        => false,
				),
			);
		}

		return null;
	}
}
