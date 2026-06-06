<?php
/**
 * Draft Pages Not Accumulating Diagnostic
 *
 * Checks for pages sitting in draft status for more than 90 days. A build-up
 * of old drafts often signals incomplete or abandoned site work and can
 * create confusion about site structure.
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
 * Diagnostic_Draft_Pages_Accumulation Class
 *
 * Queries wp_posts for pages with post_status = 'draft' whose post_modified
 * date is older than 90 days. Returns a low-severity finding when stale draft
 * pages accumulate, or null when the draft count is manageable.
 *
 * @since 0.6095
 */
class Diagnostic_Draft_Pages_Accumulation extends Diagnostic_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'draft-pages-accumulation';

	/**
	 * @var string
	 */
	protected static $title = 'Draft Pages Not Accumulating';

	/**
	 * @var string
	 */
	protected static $description = 'Checks for pages sitting in draft status for more than 90 days. A build-up of old drafts often signals incomplete or abandoned site work.';

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
	 * Queries wp_posts for pages that have been in draft status and not modified
	 * in the last 90 days. Returns null when there are fewer than 3 such pages.
	 * Returns a low-severity finding listing the stale draft count and a sample
	 * of page titles when the threshold is exceeded.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when stale draft pages accumulate, null when healthy.
	 */
	public static function check() {
		$stale_drafts = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'draft',
				'posts_per_page'         => 20,
				'orderby'                => 'modified',
				'order'                  => 'ASC',
				'date_query'             => array(
					array(
						'column' => 'post_modified',
						'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) ),
					),
				),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $stale_drafts ) || count( $stale_drafts ) < 3 ) {
			return null;
		}

		$count = count( $stale_drafts );
		$list  = array_map( static function ( \WP_Post $p ) {
			return array(
				'id'       => (int) $p->ID,
				'title'    => $p->post_title,
				'modified' => $p->post_modified,
			);
		}, $stale_drafts );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of stale draft pages */
				_n(
					'%d page has been sitting in draft status for more than 90 days without being updated. Accumulated drafts often indicate abandoned work. Review and either publish, delete, or update these pages.',
					'%d pages have been sitting in draft status for more than 90 days without being updated. Accumulated drafts often indicate abandoned work. Review and either publish, delete, or update these pages.',
					$count,
					'thisismyurl-shadow'
				),
				$count
			),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'stale_draft_count' => $count,
				'stale_pages'       => $list,
			),
		);
	}
}
