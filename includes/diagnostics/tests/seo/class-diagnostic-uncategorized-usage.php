<?php
/**
 * Uncategorized Usage Diagnostic
 *
 * Checks whether any published posts are still using the WordPress default
 * Uncategorized category, which signals poor content organisation to visitors
 * and search engines.
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
 * Diagnostic_Uncategorized_Usage Class
 *
 * Checks the default category option. When the category slug is still
 * "uncategorized" and at least one post uses it, returns a low-severity finding.
 *
 * @since 0.6095
 */
class Diagnostic_Uncategorized_Usage extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'uncategorized-usage';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Uncategorized Usage';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether any published posts are still using the WordPress default Uncategorized category, which signals poor content organization to visitors and search engines.';

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
	 * Reads the default_category option and resolves the term. Passes immediately
	 * when the category no longer exists, has been renamed, or has zero assigned
	 * posts. Returns a low-severity finding with post count details when posts
	 * are still using the default "uncategorized" slug.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when uncategorized posts are found, null when healthy.
	 */
	public static function check() {
		$default_cat_id = (int) get_option( 'default_category', 1 );

		// If the default category term no longer exists, nothing to flag.
		$default_cat = get_term( $default_cat_id, 'category' );
		if ( is_wp_error( $default_cat ) || empty( $default_cat ) ) {
			return null;
		}

		// If the category has been renamed away from "uncategorized", it's been reviewed.
		if ( 'uncategorized' !== $default_cat->slug ) {
			return null;
		}

		// Count published posts that are ONLY in the default (Uncategorized) category.
		$posts_in_uncategorized = (int) $default_cat->count;
		if ( $posts_in_uncategorized === 0 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of posts in uncategorized */
				_n(
					'%d published post is in the default "Uncategorized" category. The slug "uncategorized" appears in URLs and signals to search engines that content has not been organised intentionally. Rename the category or reassign posts to meaningful categories.',
					'%d published posts are in the default "Uncategorized" category. The slug "uncategorized" appears in URLs and signals to search engines that content has not been organised intentionally. Rename the category or reassign posts to meaningful categories.',
					$posts_in_uncategorized,
					'thisismyurl-shadow'
				),
				$posts_in_uncategorized
			),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'category_slug'  => $default_cat->slug,
				'category_name'  => $default_cat->name,
				'post_count'     => $posts_in_uncategorized,
			),
		);
	}
}
