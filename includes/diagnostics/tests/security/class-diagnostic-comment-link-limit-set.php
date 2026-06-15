<?php
/**
 * Comment Link Limit Set Diagnostic
 *
 * Checks whether WordPress limits the number of links allowed per comment,
 * reducing the appeal of the comment form to spammers and link-droppers.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_WP_Settings_Helper as WP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Comment_Link_Limit_Set Class
 *
 * @since 0.6095
 */
class Diagnostic_Comment_Link_Limit_Set extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'comment-link-limit-set';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Comment Link Limit Set';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress limits the number of links allowed in a comment submission, which reduces the appeal of the comment form for spam bots.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the comment_max_links option and flags when it is set to 0 (unlimited)
	 * or a high value that provides no spam deterrence.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when link limit is absent or excessive, null when healthy.
	 */
	public static function check() {
		// If comments are globally disabled, the link limit is irrelevant.
		if ( ! WP_Settings::are_comments_open_by_default() ) {
			return null;
		}

		$limit = WP_Settings::get_max_links_in_comment();

		// 0 means WordPress will NOT hold comments based on link count.
		// Any positive value means comments with that many links are held for review.
		if ( $limit > 0 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No limit is set for the number of links allowed in a comment before it is held for moderation. Comments with many links are a classic spam pattern. Set a low limit (1–2) in Settings > Discussion.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'comment_max_links'  => $limit,
				'recommended_limit'  => 2,
			),
		);
	}
}
