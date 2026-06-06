<?php
/**
 * Default Category Renamed Diagnostic
 *
 * Checks whether the WordPress default post category has been renamed from
 * "Uncategorized", as new posts inherit this category automatically and an
 * unnamed default creates low-quality URLs and category archive pages.
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
 * Diagnostic_Default_Category_Renamed Class
 *
 * Reads the default_category option and checks whether the term name or slug
 * is still the installer default ("Uncategorized" / "uncategorized").
 *
 * @since 0.6095
 */
class Diagnostic_Default_Category_Renamed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'default-category-renamed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Default Category Renamed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress default post category has been renamed from "Uncategorized". New posts inherit this category automatically, so an unnamed default creates low-quality URLs and category archive pages.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Severity of the finding.
	 *
	 * @var string
	 */
	protected static $severity = 'low';

	/**
	 * Estimated minutes to resolve.
	 *
	 * @var int
	 */
	protected static $time_to_fix_minutes = 5;

	/**
	 * Business impact statement.
	 *
	 * @var string
	 */
	protected static $impact = 'An "Uncategorized" default category pollutes category archives and post URLs with a meaningless label visible to search engines and visitors.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the default_category WordPress option and resolves the term. Returns
	 * null immediately when the term cannot be found (deleted or custom). If the
	 * term name (case-insensitive) is "Uncategorized" or the slug is
	 * "uncategorized", returns a low-severity finding prompting the admin to
	 * rename the category under Posts > Categories.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when default category is still uncategorized, null when healthy.
	 */
	public static function check() {
		$default_cat_id = (int) get_option( 'default_category', 1 );
		$term           = get_term( $default_cat_id, 'category' );

		if ( is_wp_error( $term ) || empty( $term ) ) {
			// Default category removed or custom — no problem detectable.
			return null;
		}

		if ( 'uncategorized' !== strtolower( $term->slug ) && 'uncategorized' !== strtolower( $term->name ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'The default post category is still named "Uncategorized". Every new post is automatically placed in this category, creating archive pages with the URL slug /category/uncategorized/ that expose poor content organisation to visitors and search engines. Rename the category under Posts → Categories.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'category_id'   => $default_cat_id,
				'category_name' => $term->name,
				'category_slug' => $term->slug,
			),
		);
	}
}
