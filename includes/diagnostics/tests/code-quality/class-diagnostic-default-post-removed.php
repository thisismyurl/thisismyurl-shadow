<?php
/**
 * Default Post Removed Diagnostic
 *
 * Checks whether the "Hello world!" sample post that WordPress installs
 * on every new site has been removed or replaced with intentional content.
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
 * Diagnostic_Default_Post_Removed Class
 *
 * WordPress ships with a published post (slug: hello-world, title: "Hello
 * world!") as sample content. Sites that leave it live signal to visitors
 * that setup is incomplete, and it is typically accompanied by the default
 * sample comment from "A WordPress Commenter".
 *
 * @since 0.6095
 */
class Diagnostic_Default_Post_Removed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'default-post-removed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Default "Hello World!" Post Not Removed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the sample post included in every fresh WordPress installation has been deleted or replaced with intentional content.';

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
	 * Looks for the default post by its well-known slug (hello-world) and
	 * falls back to matching on the default title and body text so the check
	 * still fires even if only the slug was changed.
	 *
	 * @since  0.6095
	 * @return array|null Finding array if issue exists, null if healthy.
	 */
	public static function check() {
		// Primary lookup: canonical slug from a fresh WordPress install.
		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );

		// Fallback: slug changed but title or body still match the defaults.
		if ( null === $post ) {
			// WP_Query handles title matching without a direct DB call.
			$title_query = new \WP_Query(
				array(
					'post_type'      => 'post',
					'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
					'title'          => 'Hello world!',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'fields'         => 'ids',
				)
			);
			$post_id = $title_query->have_posts() ? (int) $title_query->posts[0] : 0;

			// Fall back to a distinctive excerpt from the stock post body.
			if ( ! $post_id ) {
				$content_query = new \WP_Query(
					array(
						'post_type'              => 'post',
						'post_status'            => array( 'publish', 'draft', 'private', 'future' ),
						's'                      => 'Welcome to WordPress. This is your first post',
						'posts_per_page'         => 1,
						'no_found_rows'          => true,
						'fields'                 => 'ids',
						'ignore_sticky_posts'    => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);
				$post_id = $content_query->have_posts() ? (int) $content_query->posts[0] : 0;
			}

			if ( ! $post_id ) {
				return null; // No trace of the default post — healthy.
			}

			$post = get_post( $post_id );
		}

		if ( null === $post ) {
			return null;
		}

		$has_default_body = str_contains(
			(string) $post->post_content,
			'Welcome to WordPress. This is your first post'
		);

		// If the content was customised, the slug/title test handles the rest.
		if ( ! $has_default_body ) {
			return null;
		}

		$permalink = get_permalink( $post->ID );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'The default "Hello world!" post that WordPress installs on every new site is still live with its original placeholder text. Any visitor who reaches it will see unfinished content.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'post_id'     => $post->ID,
				'post_title'  => $post->post_title,
				'post_status' => $post->post_status,
				'post_url'    => $permalink ?: '',
				'fix'         => __( 'Go to Posts &rsaquo; All Posts, then delete this post before launch.', 'thisismyurl-shadow' ),
			),
		);
	}
}
