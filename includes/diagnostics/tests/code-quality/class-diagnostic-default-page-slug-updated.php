<?php
/**
 * Default Page Slug Updated Diagnostic
 *
 * Checks whether the "Sample Page" that ships with WordPress has been reused
 * as real content without updating its original slug or title.
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
 * Diagnostic_Default_Page_Slug_Updated Class
 *
 * Some sites convert the "Sample Page" into a real page by replacing the
 * body text but never updating the slug (sample-page) or the title
 * ("Sample Page"). Both identifiers are then exposed in navigation menus,
 * browser tabs, accessibility tools, and social share previews.
 *
 * This diagnostic only fires when the body has been customised. If the
 * original WordPress placeholder text is still present, Diagnostic_Default_Page_Removed
 * covers the issue instead.
 *
 * @since 0.6095
 */
class Diagnostic_Default_Page_Slug_Updated extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'default-page-slug-updated';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Default "Sample Page" Slug or Title Not Updated';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the original "Sample Page" slug or title was kept after the page was repurposed with custom content.';

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
	 * Run the diagnostic check.
	 *
	 * Finds the sample-page by slug first, then falls back to a title match
	 * via WP_Query. Only fires when the body has been changed — if the
	 * original placeholder text is still present the removed-check handles it.
	 *
	 * @since  0.6095
	 * @return array|null Finding array if issue exists, null if healthy.
	 */
	public static function check() {
		// Primary lookup: canonical slug from a fresh WordPress install.
		$page = get_page_by_path( 'sample-page', OBJECT, 'page' );

		// Fallback: slug was changed but the title may still be the default.
		if ( null === $page ) {
			$query = new \WP_Query(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
					'title'          => 'Sample Page',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
			$page = $query->have_posts() ? $query->posts[0] : null;
		}

		if ( null === $page ) {
			return null;
		}

		// If the default body is still present, Diagnostic_Default_Page_Removed
		// covers this page — avoid firing duplicate findings.
		if ( str_contains( (string) $page->post_content, 'This is an example page' ) ) {
			return null;
		}

		$has_default_slug  = ( 'sample-page' === $page->post_name );
		$has_default_title = ( 'Sample Page' === $page->post_title );

		if ( ! $has_default_slug && ! $has_default_title ) {
			return null; // Both identifiers were updated — healthy.
		}

		// Build a readable list of what still needs updating.
		$stale = array();
		if ( $has_default_slug ) {
			/* translators: %s: the page slug value */
			$stale[] = sprintf( __( 'slug (%s)', 'thisismyurl-shadow' ), $page->post_name );
		}
		if ( $has_default_title ) {
			/* translators: %s: the page title value */
			$stale[] = sprintf( __( 'title (%s)', 'thisismyurl-shadow' ), $page->post_title );
		}

		$permalink = get_permalink( $page->ID );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: comma-separated list of stale identifiers e.g. "slug (sample-page) and title (Sample Page)" */
				__( 'A page was repurposed with custom content but its original WordPress %s was not updated. Placeholder identifiers appear in navigation menus, browser tabs, and social share cards.', 'thisismyurl-shadow' ),
				implode( _x( ' and ', 'list separator', 'thisismyurl-shadow' ), $stale )
			),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'page_id'           => $page->ID,
				'page_title'        => $page->post_title,
				'page_slug'         => $page->post_name,
				'page_status'       => $page->post_status,
				'page_url'          => $permalink ?: '',
				'has_default_slug'  => $has_default_slug,
				'has_default_title' => $has_default_title,
				'fix'               => __( 'Edit the page, update the title to reflect its real purpose, then change the permalink slug to match and republish.', 'thisismyurl-shadow' ),
			),
		);
	}
}
