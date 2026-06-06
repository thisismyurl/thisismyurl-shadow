<?php
/**
 * Recent Posts Have Featured Images Diagnostic
 *
 * Checks that recently published posts have a featured image set. Missing
 * featured images look broken in blog listings and social sharing previews.
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
 * Diagnostic_Posts_Have_Featured_Images Class
 *
 * Checks the 20 most recently published posts to see how many have a
 * _thumbnail_id post meta entry. Returns a low-severity finding when more
 * than 30% of recent posts are missing featured images.
 *
 * @since 0.6095
 */
class Diagnostic_Posts_Have_Featured_Images extends Diagnostic_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'posts-have-featured-images';

	/**
	 * @var string
	 */
	protected static $title = 'Recent Posts Have Featured Images';

	/**
	 * @var string
	 */
	protected static $description = 'Checks that recently published posts have a featured image set. Missing featured images look broken in blog listings and social sharing previews.';

	/**
	 * @var string
	 */
	protected static $family = 'design';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Fetches the 20 most recently published posts and checks each one for a
	 * _thumbnail_id meta value. Returns null when all or most have a featured
	 * image. Returns a low-severity finding when more than 30% are missing a
	 * featured image, listing the count and the affected post IDs.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when recent posts lack featured images, null when healthy.
	 */
	public static function check() {
		$recent_posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		) );

		if ( empty( $recent_posts ) ) {
			return null; // No published posts to check.
		}

		$missing = array();
		foreach ( $recent_posts as $post_id ) {
			if ( ! has_post_thumbnail( $post_id ) ) {
				$missing[] = (int) $post_id;
			}
		}

		$total         = count( $recent_posts );
		$missing_count = count( $missing );
		$missing_pct   = (int) round( ( $missing_count / $total ) * 100 );

		if ( $missing_count === 0 || $missing_pct < 30 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: number missing, 2: total checked, 3: percentage */
				__( '%1$d of the %2$d most recently published posts (%3$d%%) are missing a featured image. Featured images appear in blog listing pages, RSS feeds, and social sharing previews. Posts without them look broken and unprofessional.', 'thisismyurl-shadow' ),
				$missing_count,
				$total,
				$missing_pct
			),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'missing_count'   => $missing_count,
				'total_checked'   => $total,
				'missing_percent' => $missing_pct,
				'missing_post_ids' => $missing,
			),
		);
	}
}
