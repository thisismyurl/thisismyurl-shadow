<?php
/**
 * Discussion Defaults Diagnostic
 *
 * Checks whether WordPress discussion settings—comments, pings, and
 * moderation—have been intentionally configured to prevent spam.
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
 * Diagnostic_Discussion_Defaults Class
 *
 * Uses WP_Settings helpers to inspect comment open/moderation/ping defaults,
 * returning a low-severity finding when the site is running on install defaults.
 *
 * @since 0.6095
 */
class Diagnostic_Discussion_Defaults extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'discussion-defaults';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Discussion Defaults';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress discussion settings—comments, pings, and moderation—have been intentionally configured to prevent spam and unmoderated content.';

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
	 * Reads comment open, ping open, moderation, and max-links-in-comment settings
	 * via WP_Settings helpers. If moderation is on or comments are closed by
	 * default, returns null (conscious decision). Otherwise collects specific
	 * issues and returns a low-severity finding.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when defaults are unreviewed, null when healthy.
	 */
	public static function check() {
		$comments_open    = WP_Settings::are_comments_open_by_default();
		$pings_open       = WP_Settings::are_pings_open_by_default();
		$moderation_on    = WP_Settings::is_comment_moderation_enabled();
		$max_links        = WP_Settings::get_max_links_in_comment();

		// If at least moderation is on OR comments are closed by default, the
		// site owner has made a conscious decision — pass.
		if ( $moderation_on || ! $comments_open ) {
			return null;
		}

		$issues = array();
		if ( $comments_open ) {
			$issues[] = __( 'New posts are open to comments by default', 'thisismyurl-shadow' );
		}
		if ( $pings_open ) {
			$issues[] = __( 'Pingbacks are enabled by default', 'thisismyurl-shadow' );
		}
		if ( ! $moderation_on ) {
			$issues[] = __( 'Comment moderation is disabled — new comments post immediately', 'thisismyurl-shadow' );
		}
		if ( $max_links >= 2 ) {
			$issues[] = sprintf(
				/* translators: %d: max links allowed in comment */
				__( 'Comments can contain up to %d links before being held (a common spam tactic)', 'thisismyurl-shadow' ),
				$max_links
			);
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress discussion settings appear to be at their install defaults. New comments are published without moderation, pingbacks are enabled, and comment links are relatively unrestricted. Review Settings > Discussion and set a deliberate policy to reduce spam and comment abuse.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'comments_open_default' => $comments_open,
				'pings_open_default'    => $pings_open,
				'moderation_enabled'    => $moderation_on,
				'max_links'             => $max_links,
				'issues'                => $issues,
			),
		);
	}
}
