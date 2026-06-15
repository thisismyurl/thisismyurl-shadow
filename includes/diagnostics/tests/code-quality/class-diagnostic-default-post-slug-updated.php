<?php
/**
 * Default Post Slug Updated Diagnostic
 *
 * Checks whether the "Hello world!" sample post has been reused as real
 * content without updating its original slug or title.
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
 * Diagnostic_Default_Post_Slug_Updated Class
 *
 * Some sites repurpose the "Hello world!" post rather than deleting it —
 * they overwrite the body with real content but never update the slug
 * (hello-world) or the title ("Hello world!"). This leaves placeholder
 * identifiers in public URLs, browser tab titles, and social share previews.
 *
 * This diagnostic only fires when the body has already been customised.
 * If the original WordPress placeholder body is still present,
 * Diagnostic_Default_Post_Removed covers the issue instead.
 *
 * @since 0.6095
 */
class Diagnostic_Default_Post_Slug_Updated extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'default-post-slug-updated';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Default "Hello World" Post Slug or Title Not Updated';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the original "Hello world!" post slug or title was kept after the post was repurposed with new content.';

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
	 * Finds the hello-world post by slug first, then falls back to a title
	 * match via WP_Query. Only fires when the body has been changed — if the
	 * original placeholder text is still present the removed-check handles it.
	 *
	 * @since  0.6095
	 * @return array|null Finding array if issue exists, null if healthy.
	 */
	public static function check() {
		// Primary lookup: canonical slug from a fresh WordPress install.
		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );

		// Fallback: slug was changed but title may still be the default.
		if ( null === $post ) {
			$query = new \WP_Query(
				array(
					'post_type'      => 'post',
					'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
					'title'          => 'Hello world!',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
			$post = $query->have_posts() ? $query->posts[0] : null;
		}

		if ( null === $post ) {
			return null;
		}

		// If the default body is still present, Diagnostic_Default_Post_Removed
		// covers this post — avoid firing duplicate findings.
		if ( str_contains( (string) $post->post_content, 'Welcome to WordPress. This is your first post' ) ) {
			return null;
		}

		$has_default_slug  = ( 'hello-world' === $post->post_name );
		$has_default_title = ( 'Hello world!' === $post->post_title );

		if ( ! $has_default_slug && ! $has_default_title ) {
			return null; // Both identifiers were updated — healthy.
		}

		// Build a readable list of what still needs updating.
		$stale = array();
		if ( $has_default_slug ) {
			/* translators: %s: the post slug value */
			$stale[] = sprintf( __( 'slug (%s)', 'thisismyurl-shadow' ), $post->post_name );
		}
		if ( $has_default_title ) {
			/* translators: %s: the post title value */
			$stale[] = sprintf( __( 'title (%s)', 'thisismyurl-shadow' ), $post->post_title );
		}

		$permalink = get_permalink( $post->ID );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: comma-separated list of stale identifiers e.g. "slug (hello-world) and title (Hello world!)" */
				__( 'A post was repurposed with custom content but its original WordPress %s was not updated. Placeholder identifiers appear in public URLs, browser tabs, and social share cards.', 'thisismyurl-shadow' ),
				implode( _x( ' and ', 'list separator', 'thisismyurl-shadow' ), $stale )
			),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'post_id'           => $post->ID,
				'post_title'        => $post->post_title,
				'post_slug'         => $post->post_name,
				'post_status'       => $post->post_status,
				'post_url'          => $permalink ?: '',
				'has_default_slug'  => $has_default_slug,
				'has_default_title' => $has_default_title,
				'fix'               => __( 'Edit the post, update the title to reflect its real subject, then change the permalink slug to match and republish.', 'thisismyurl-shadow' ),
			),
		);
	}
}
