<?php
/**
 * Sample Content Removed Diagnostic
 *
 * Scans published posts and pages for Lorem Ipsum and other well-known
 * Latin placeholder phrases that indicate template content was never replaced.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Diagnostics
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Sample_Content_Removed Class
 *
 * @since 0.6095
 */
class Diagnostic_Sample_Content_Removed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'sample-content-removed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Placeholder Text Detected in Published Content';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Scans published posts and pages for Lorem Ipsum and other well-known placeholder phrases that indicate template content was never replaced.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'code-quality';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Known Latin placeholder phrases used by page builders, themes, and demo packs.
	 *
	 * @var string[]
	 */
	private const PLACEHOLDER_PHRASES = array(
		'Lorem ipsum dolor sit amet',
		'consectetur adipiscing elit',
		'Pellentesque habitant morbi',
		'Quisque velit nisi',
		'Curabitur aliquet quam',
		'Nulla quis lorem ut libero',
	);

	/**
	 * Run the diagnostic check.
	 *
	 * Queries published posts and pages for any of the known Latin placeholder
	 * phrases. Reports every affected piece of content so the user knows
	 * exactly where to go.
	 *
	 * @since  0.6095
	 * @return array|null Finding array if issue exists, null if healthy.
	 */
	public static function check() {
		global $wpdb;

		/*
		 * Build a placeholder-only LIKE OR clause. $where_or contains nothing
		 * but `post_content LIKE %s OR …`, so it is safe to interpolate into the
		 * query; every search phrase is bound through $values via prepare().
		 */
		$like     = array_fill( 0, count( self::PLACEHOLDER_PHRASES ), 'post_content LIKE %s' );
		$where_or = implode( ' OR ', $like );
		$values   = array_map(
			static fn( string $p ) => '%' . $wpdb->esc_like( $p ) . '%',
			self::PLACEHOLDER_PHRASES
		);

		/*
		 * The statement is fully prepared: $where_or is placeholder-only
		 * ("post_content LIKE %s OR …") and every search value is bound through
		 * $values. WPCS cannot statically prove the interpolated $where_or is
		 * placeholder-only, so the InterpolatedNotPrepared sniff is scoped off
		 * for this single query only.
		 */
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- read-only diagnostic; no caching layer.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_or is placeholder-only; all values bound via $values.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where_or is placeholder-only; all values bound via $values.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_modified
				 FROM   {$wpdb->posts}
				 WHERE  post_status = 'publish'
				 AND    post_type   IN ('post', 'page')
				 AND    ( {$where_or} )
				 ORDER  BY post_modified DESC
				 LIMIT  200",
				$values
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( empty( $rows ) ) {
			return null;
		}

		$affected = array();
		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			$affected[] = array(
				'post_id'    => (int) $row->ID,
				'post_title' => $row->post_title,
				'post_type'  => $row->post_type,
				'edit_url'   => get_edit_post_link( (int) $row->ID, 'raw' ),
			);
		}

		$total = count( $rows );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => 1 === $total
				? sprintf(
					/* translators: %s: post type (post or page) */
					__( 'One published %s still contains Lorem Ipsum or similar Latin placeholder text. Replace it with your real content before visitors arrive.', 'thisismyurl-shadow' ),
					esc_html( $affected[0]['post_type'] )
				)
				: sprintf(
					/* translators: %d: number of posts/pages affected */
					_n(
						'%d published post or page still contains Lorem Ipsum or similar placeholder text.',
						'%d published posts and pages still contain Lorem Ipsum or similar placeholder text.',
						$total,
						'thisismyurl-shadow'
					),
					$total
				),
			'severity'     => $total > 3 ? 'medium' : 'low',
			'threat_level' => $total > 3 ? 30 : 15,
			'details'      => array(
				'affected_count'  => $total,
				'affected_posts'  => $affected,
				'phrases_checked' => self::PLACEHOLDER_PHRASES,
				'fix'             => __( 'Open each affected post or page in the editor and replace every placeholder paragraph with your actual content.', 'thisismyurl-shadow' ),
			),
		);
	}
}
