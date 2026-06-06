<?php
/**
 * Schema Basics Diagnostic
 *
 * Checks whether a schema markup plugin is active to output structured data
 * that helps search engines display the site's content in rich results.
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
 * Diagnostic_Schema_Basics Class
 *
 * Iterates the active_plugins option against a list of well-known SEO and
 * schema-specific plugins, flagging sites where none are detected.
 *
 * @since 0.6095
 */
class Diagnostic_Schema_Basics extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'schema-basics';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Schema Basics';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a schema markup plugin is active to output structured data that helps search engines understand and display the site\'s content in rich results.';

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
	 * Checks active plugins against a curated list of tools known to output
	 * structured data (schema markup). Returns null immediately when any
	 * recognised plugin is found, otherwise returns a medium-severity finding.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no schema plugin is active, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		// Plugins that output schema markup automatically.
		$schema_plugins = array(
			'wordpress-seo/wp-seo.php'                              => 'Yoast SEO',
			'wordpress-seo-premium/wp-seo-premium.php'              => 'Yoast SEO Premium',
			'seo-by-rank-math/rank-math.php'                        => 'Rank Math',
			'seo-by-rank-math-pro/rank-math-pro.php'                => 'Rank Math Pro',
			'schema-and-structured-data-for-wp/index.php'           => 'Schema & Structured Data for WP',
			'wp-schema-pro/wp-schema-pro.php'                       => 'WP Schema Pro',
			'schema-pro/schema-pro.php'                             => 'Schema Pro',
			'the-seo-framework/the-seo-framework.php'               => 'The SEO Framework',
			'all-in-one-seo-pack/all_in_one_seo_pack.php'           => 'All in One SEO',
			'all-in-one-seo-pack-pro/all_in_one_seo_pack.php'       => 'All in One SEO Pro',
		);

		foreach ( $schema_plugins as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No plugin providing structured data (schema markup) is active. Schema markup helps search engines understand your content and enables rich results such as star ratings, breadcrumbs, and FAQ panels. Install an SEO plugin such as Yoast SEO or Rank Math, or a dedicated schema plugin such as Schema & Structured Data for WP.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'schema_plugin_detected' => false,
			),
		);
	}
}
