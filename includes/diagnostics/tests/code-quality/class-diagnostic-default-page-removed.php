<?php
/**
 * Default Page Removed Diagnostic
 *
 * Checks whether the "Sample Page" that WordPress installs on every new site
 * has been removed or replaced with intentional content.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Default_Page_Removed Class
 *
 * WordPress ships with a published page (slug: sample-page, title: "Sample
 * Page") as placeholder content. Leaving it live pollutes the site navigation
 * with an irrelevant page and signals incomplete setup.
 *
 * @since 0.6095
 */
class Diagnostic_Default_Page_Removed extends Diagnostic_Base {

	/** @var string */
	protected static $slug = 'default-page-removed';

	/** @var string */
	protected static $title = 'Default "Sample Page" Not Removed';

	/** @var string */
	protected static $description = 'Checks whether the sample page included in every fresh WordPress installation has been deleted or replaced with intentional content.';

	/** @var string */
	protected static $family = 'code-quality';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * @since  0.6095
	 * @return array|null
	 */
	public static function check() {
		$page = get_page_by_path( 'sample-page', OBJECT, 'page' );

		if ( null === $page ) {
			// WP_Query handles title matching without a direct DB call.
			$title_query = new \WP_Query(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
					'title'          => 'Sample Page',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'fields'         => 'ids',
				)
			);
			$page_id = $title_query->have_posts() ? (int) $title_query->posts[0] : 0;

			// Fall back to a distinctive excerpt from the stock page body.
			if ( ! $page_id ) {
				$content_query = new \WP_Query(
					array(
						'post_type'              => 'page',
						'post_status'            => array( 'publish', 'draft', 'private', 'future' ),
						's'                      => 'This is an example page',
						'posts_per_page'         => 1,
						'no_found_rows'          => true,
						'fields'                 => 'ids',
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);
				$page_id = $content_query->have_posts() ? (int) $content_query->posts[0] : 0;
			}

			if ( ! $page_id ) {
				return null;
			}
			$page = get_post( $page_id );
		}

		if ( null === $page ) {
			return null;
		}

		$has_default_body = str_contains( (string) $page->post_content, 'This is an example page' );

		// If the content was customised, the slug/title test handles the rest.
		if ( ! $has_default_body ) {
			return null;
		}

		$permalink = get_permalink( $page->ID );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'The default "Sample Page" that WordPress installs on every new site is still live with its original placeholder text. Visitors who land on it will see template wording and may question whether the site is ready.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'page_id'     => $page->ID,
				'page_title'  => $page->post_title,
				'page_status' => $page->post_status,
				'page_url'    => $permalink ?: '',
				'fix'         => __( 'Go to Pages &rsaquo; All Pages, then delete this page before launch.', 'thisismyurl-shadow' ),
			),
		);
	}
}
