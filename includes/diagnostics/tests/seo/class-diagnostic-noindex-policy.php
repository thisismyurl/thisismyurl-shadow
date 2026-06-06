<?php
/**
 * Noindex Policy Diagnostic
 *
 * Checks whether an SEO plugin is configured to apply noindex directives to
 * low-value pages such as date archives, author archives, and search results.
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
 * Diagnostic_Noindex_Policy Class
 *
 * Verifies that a recognised SEO plugin is active and, when Yoast SEO is
 * found, confirms that date archives are set to noindex.
 *
 * @since 0.6095
 */
class Diagnostic_Noindex_Policy extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'noindex-policy';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Noindex Policy';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an SEO plugin is configured to control which pages receive a noindex directive, preventing search engines from indexing low-value content.';

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
	 * Inspects active plugins for recognised SEO tools. Returns a medium-severity
	 * finding when none are active. When Yoast SEO is present, checks the
	 * wpseo_titles option for date archive noindex settings and reports any
	 * misconfigured indexable areas at low severity.
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

		if ( ! $has_yoast && ! $has_rankmath && ! $has_aioseo && ! $has_seopress ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'No SEO plugin is active to manage noindex policies for low-value pages such as date archives, author archives, and search results. Without a noindex policy, thin or duplicate content pages can dilute your site\'s crawl budget and SEO authority. Install an SEO plugin such as Yoast SEO or Rank Math to apply noindex rules to low-value areas.', 'thisismyurl-shadow' ),
				'severity'     => 'medium',
				'threat_level' => 40,
				'details'      => array( 'seo_plugin_active' => false ),
			);
		}

		if ( $has_yoast ) {
			$titles         = get_option( 'wpseo_titles', array() );
			$indexable_areas = array();

			// Check date archives (default noindex in Yoast is true).
			if ( isset( $titles['noindex-archive-wpseo'] ) && ! $titles['noindex-archive-wpseo'] ) {
				$indexable_areas[] = 'date archives';
			}

			if ( ! empty( $indexable_areas ) ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => sprintf(
						/* translators: %s: list of indexable areas */
						__( 'Yoast SEO has been configured to allow the following low-value areas to be indexed: %s. These pages typically contain thin or duplicate content and should be set to noindex in Yoast SEO → Search Appearance → Archives.', 'thisismyurl-shadow' ),
						implode( ', ', $indexable_areas )
					),
					'severity'     => 'low',
					'threat_level' => 25,
					'details'      => array( 'indexable_areas' => $indexable_areas ),
				);
			}
		}

		return null;
	}
}
