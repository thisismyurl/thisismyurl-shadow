<?php
/**
 * Tag Archives Diagnostic
 *
 * Checks whether post tag archives are bloated with single-use tags that
 * generate thin archive pages and fragment crawl budget.
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
 * Diagnostic_Tag_Archives_Intentional Class
 *
 * Queries the post_tag taxonomy for tags assigned to only one post to detect
 * URL bloat from thin archive pages, flagging when over half are singletons.
 *
 * @since 0.6095
 */
class Diagnostic_Tag_Archives_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'tag-archives-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Tag Archives';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether post tag archives are bloated with single-use tags that generate thin archive pages and fragment crawl budget.';

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
	 * Retrieves up to 200 non-empty post_tag terms and counts how many have only
	 * one assigned post. If the site has fewer than five tags, or the singleton
	 * ratio is below 50%, the site passes. Otherwise returns a low-severity
	 * finding with the singleton count, total count, and ratio.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when tag bloat is detected, null when healthy.
	 */
	public static function check() {
		// Count tags that have only one post — these create thin archive pages
		// that add URL bloat and can dilute crawl budget.
		$single_post_tags = get_terms( array(
			'taxonomy'   => 'post_tag',
			'number'     => 0,
			'hide_empty' => true,
			'count'      => false,
			'fields'     => 'ids',
			'meta_query' => array(), // suppress unneeded meta query
		) );

		if ( is_wp_error( $single_post_tags ) || empty( $single_post_tags ) ) {
			return null;
		}

		// Manually count tags with only 1 assigned post.
		$all_tags = get_terms( array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => true,
			'fields'     => 'all',
			'number'     => 200,
		) );

		if ( is_wp_error( $all_tags ) || empty( $all_tags ) ) {
			return null;
		}

		$total     = count( $all_tags );
		$singleton = 0;

		foreach ( $all_tags as $tag ) {
			if ( (int) $tag->count === 1 ) {
				$singleton++;
			}
		}

		if ( $total < 5 ) {
			// Too few tags to assess. Pass.
			return null;
		}

		$ratio = $singleton / $total;
		if ( $ratio < 0.5 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: number of single-post tags, 2: total tag count */
				__( '%1$d of your %2$d tags are assigned to only one post. Each tag generates a public archive page. Single-post tag archives are thin, low-value pages that add URL bloat and may dilute crawl budget. Consider deleting unused tags or using fewer, more meaningful ones.', 'thisismyurl-shadow' ),
				$singleton,
				$total
			),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'total_tags'        => $total,
				'single_post_tags'  => $singleton,
				'singleton_ratio'   => round( $ratio, 2 ),
			),
		);
	}
}
