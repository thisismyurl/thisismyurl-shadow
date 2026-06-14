<?php
/**
 * Comment Policy Diagnostic
 *
 * Checks whether WordPress comments are open by default without comment
 * moderation, which exposes the site to spam and unreviewed public content.
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
 * Diagnostic_Comment_Policy_Intentional Class
 *
 * Uses WP_Settings helpers to check whether comments are open by default with
 * no moderation gate, flagging the risky combination with a low-severity finding.
 *
 * @since 0.6095
 */
class Diagnostic_Comment_Policy_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'comment-policy-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Comment Policy';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress comments are open by default without comment moderation, which exposes the site to spam and unreviewed public content.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads default comment status and moderation settings via WP_Settings.
	 * Returns null when comments are globally disabled or when moderation is
	 * enabled. Returns a low-severity finding when comments are open by default
	 * but display without moderation review.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when unmoderated comments are open, null when healthy.
	 */
	public static function check() {
		$comments_open = WP_Settings::are_comments_open_by_default();
		$moderated     = WP_Settings::is_comment_moderation_enabled();

		// Comments disabled globally — no concern.
		if ( ! $comments_open ) {
			return null;
		}

		// Comments open but moderation is on — acceptable.
		if ( $moderated ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'New posts have comments open by default and comment moderation is disabled. This allows spam and abusive comments to appear on your site immediately without review. Enable comment moderation or disable comments on post types that do not need them.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 25,
			'details'      => array(
				'comments_open_by_default' => $comments_open,
				'moderation_enabled'       => $moderated,
				'max_links_before_hold'    => WP_Settings::get_max_links_in_comment(),
			),
		);
	}
}
