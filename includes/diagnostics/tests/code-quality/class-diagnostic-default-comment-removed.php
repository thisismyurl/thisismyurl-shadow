<?php
/**
 * Default Comment Removed Diagnostic
 *
 * WordPress installs a single approved comment by "A WordPress Commenter" on
 * the default "Hello world!" post. This comment should be deleted before the
 * site goes live.
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
 * Diagnostic_Default_Comment_Removed Class
 *
 * The canonical sample comment ships with the author "A WordPress Commenter",
 * the email address wapuu@wordpress.example, and the opening sentence
 * "Hi, this is a comment." Leaving it on a live site makes it appear that
 * the only engagement on the site came from a placeholder account.
 *
 * The check matches on the known email first (most reliable), then falls back
 * to the known author name + body text pattern so it still fires even if the
 * email address was edited but the comment was never properly deleted.
 *
 * @since 0.6095
 */
class Diagnostic_Default_Comment_Removed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'default-comment-removed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Default WordPress Sample Comment Not Removed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the sample comment that ships with every WordPress install — left by "A WordPress Commenter" — has been permanently deleted.';

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
	 * Queries the comments table for the canonical email address, then falls
	 * back to the author name + opening sentence for situations where the
	 * email has been changed but the comment was never deleted.
	 *
	 * @since  0.6095
	 * @return array|null Finding array if issue exists, null if healthy.
	 */
	public static function check() {
		// Primary: look up the canonical sample comment by its placeholder email address.
		$comments = get_comments(
			array(
				'author_email' => 'wapuu@wordpress.example',
				'number'       => 1,
				'status'       => 'all',
			)
		);
		$comment = $comments[0] ?? null;

		// Fallback: email was changed — match on the opening sentence and verify
		// the author name so we don't accidentally flag a real comment.
		if ( null === $comment ) {
			$results = get_comments(
				array(
					'search' => 'Hi, this is a comment.',
					'number' => 10,
					'status' => 'all',
				)
			);
			foreach ( $results as $c ) {
				if ( 'A WordPress Commenter' === $c->comment_author ) {
					$comment = $c;
					break;
				}
			}
		}

		if ( null === $comment ) {
			return null; // Sample comment not found — healthy.
		}

		$is_visible = '1' === (string) $comment->comment_approved;

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => $is_visible
				? __( 'The default comment left by "A WordPress Commenter" is still approved and publicly visible. Real visitors will see this placeholder comment alongside any genuine responses.', 'thisismyurl-shadow' )
				: __( 'The default comment left by "A WordPress Commenter" still exists in your database (pending, spam, or trash). Delete it permanently so it cannot be re-approved.', 'thisismyurl-shadow' ),
			'severity'     => $is_visible ? 'medium' : 'low',
			'threat_level' => $is_visible ? 25 : 10,
			'details'      => array(
				'comment_id'   => (int) $comment->comment_ID,
				'author'       => $comment->comment_author,
				'author_email' => $comment->comment_author_email,
				'is_visible'   => $is_visible,
				'fix'          => __( 'Go to Comments in the WordPress dashboard, find the comment by "A WordPress Commenter", and use Delete Permanently to remove it completely.', 'thisismyurl-shadow' ),
			),
		);
	}
}
