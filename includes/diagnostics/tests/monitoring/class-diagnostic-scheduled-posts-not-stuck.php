<?php
/**
 * Scheduled Posts Not Stuck Diagnostic
 *
 * Checks for posts that were scheduled to publish automatically but are still
 * in 'future' status past their scheduled date. This indicates a broken
 * WP-Cron setup where the publish_future_post action never fired.
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
 * Diagnostic_Scheduled_Posts_Not_Stuck Class
 *
 * Queries wp_posts for posts in 'future' status whose post_date_gmt is in the
 * past. Returns a medium-severity finding listing stuck post IDs and titles
 * when any are found, or null when the scheduled queue is healthy.
 *
 * @since 0.6095
 */
class Diagnostic_Scheduled_Posts_Not_Stuck extends Diagnostic_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'scheduled-posts-not-stuck';

	/**
	 * @var string
	 */
	protected static $title = 'Scheduled Posts Not Stuck';

	/**
	 * @var string
	 */
	protected static $description = 'Checks for posts scheduled to publish automatically that were never published, which indicates a broken WP-Cron setup.';

	/**
	 * @var string
	 */
	protected static $family = 'monitoring';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Queries wp_posts for rows with post_status = 'future' and a post_date_gmt
	 * earlier than the current UTC time, meaning the post should already have
	 * published. Returns null when no stuck posts exist. Returns a medium-severity
	 * finding with the stuck post count and a sample of titles when any are found.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when stuck scheduled posts are found, null when healthy.
	 */
	public static function check() {
		$stuck_posts = get_posts(
			array(
				'post_type'              => 'any',
				'post_status'            => 'future',
				'posts_per_page'         => 20,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'date_query'             => array(
					array(
						'column' => 'post_date_gmt',
						'before' => gmdate( 'Y-m-d H:i:s' ),
					),
				),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $stuck_posts ) ) {
			return null;
		}

		$count = count( $stuck_posts );
		$list  = array_map( static function ( \WP_Post $p ) {
			return array(
				'id'             => (int) $p->ID,
				'title'          => $p->post_title,
				'scheduled_gmt'  => $p->post_date_gmt,
			);
		}, $stuck_posts );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of stuck posts */
				_n(
					'%d post was scheduled to publish automatically but is still in draft/future status past its publish date. This indicates that WP-Cron is not firing reliably and the publish_future_post action never ran.',
					'%d posts were scheduled to publish automatically but are still in draft/future status past their publish dates. This indicates that WP-Cron is not firing reliably and the publish_future_post action never ran.',
					$count,
					'thisismyurl-shadow'
				),
				$count
			),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'stuck_count' => $count,
				'stuck_posts' => $list,
			),
		);
	}
}
