<?php
/**
 * Category Strategy Diagnostic
 *
 * Checks whether the category taxonomy is organized and free of empty or
 * near-empty categories that fragment crawl budget and dilute site structure.
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
 * Diagnostic_Category_Strategy Class
 *
 * Inspects category count, uncategorized usage, and post distribution to
 * flag category structures that harm crawl efficiency or topical authority.
 *
 * @since 0.6095
 */
class Diagnostic_Category_Strategy extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'category-strategy';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Category Strategy';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the category taxonomy is organized and free of empty or near-empty categories that fragment crawl budget and dilute site structure.';

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
	 * Inspects category count, uncategorized usage, and post-per-category
	 * distribution to flag sites with only the default category or with more
	 * than 60 % of categories containing 1 or fewer posts.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when category strategy is poor, null when healthy.
	 */
	public static function check() {
		$categories = get_terms( array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'fields'     => 'all',
			'number'     => 0,
		) );

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return null;
		}

		$total       = count( $categories );
		$default_cat = (int) get_option( 'default_category', 1 );

		// Single category scenario: only Uncategorized exists.
		if ( 1 === $total ) {
			$only_cat = $categories[0];
			if ( 'uncategorized' === $only_cat->slug ) {
				$post_count = (int) wp_count_posts( 'post' )->publish;
				if ( $post_count > 0 ) {
					return array(
						'id'           => self::$slug,
						'title'        => self::$title,
						'description'  => __( 'All posts are assigned to the default "Uncategorized" category. A meaningful category structure helps search engines understand your content topics and improves internal linking and navigation. Create focused categories that reflect the main topics of your site.', 'thisismyurl-shadow' ),
						'severity'     => 'medium',
						'threat_level' => 35,
						'details'      => array(
							'total_categories'    => 1,
							'only_uncategorized'  => true,
							'published_posts'     => $post_count,
						),
					);
				}
			}
			return null;
		}

		// Over-fragmented: many categories with 0 or 1 posts.
		$thin_count = 0;
		foreach ( $categories as $cat ) {
			if ( (int) $cat->count <= 1 ) {
				$thin_count++;
			}
		}

		if ( $total >= 10 && ( $thin_count / $total ) >= 0.6 ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: 1: thin category count 2: total category count */
					__( '%1$d of %2$d categories contain 0 or 1 posts. Over-fragmented categories create many thin archive pages with little content, which dilute crawl budget and topical authority. Consolidate thin categories into broader, more meaningful topic groups.', 'thisismyurl-shadow' ),
					$thin_count,
					$total
				),
				'severity'     => 'low',
				'threat_level' => 20,
				'details'      => array(
					'total_categories' => $total,
					'thin_categories'  => $thin_count,
					'thin_ratio'       => round( $thin_count / $total, 2 ),
				),
			);
		}

		return null;
	}
}
