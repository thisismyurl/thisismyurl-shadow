<?php
/**
 * Comments Auto Close Old Posts Diagnostic
 *
 * Checks whether WordPress is configured to automatically close comments on
 * posts older than a set number of days, reducing the spam attack surface.
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
 * Diagnostic_Comments_Auto_Close_Old_Posts Class
 *
 * Reads the close_comments_for_old_posts and close_comments_days_old options
 * and flags sites where auto-close is disabled while comments are open globally.
 *
 * @since 0.6095
 */
class Diagnostic_Comments_Auto_Close_Old_Posts extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'comments-auto-close-old-posts';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Comments Auto Close Old Posts';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is configured to automatically close comments on posts older than a set number of days. Leaving comments permanently open on all posts is an ever-growing spam surface.';

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
	protected static $confidence = 'low';

	/**
	 * Severity of the finding.
	 *
	 * @var string
	 */
	protected static $severity = 'low';

	/**
	 * Estimated minutes to resolve.
	 *
	 * @var int
	 */
	protected static $time_to_fix_minutes = 5;

	/**
	 * Business impact statement.
	 *
	 * @var string
	 */
	protected static $impact = 'Permanently open comments on old posts continuously expand the spam attack surface and increase moderation workload without a commensurate engagement benefit.';

	/**
	 * Run the diagnostic check.
	 *
	 * Returns null immediately when global comments are disabled
	 * (default_comment_status !== 'open'). Reads close_comments_for_old_posts;
	 * when disabled (0), returns a low-severity finding. When enabled, reads
	 * close_comments_days_old and returns a low-severity finding when the
	 * threshold exceeds 180 days.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when auto-close is misconfigured, null when healthy.
	 */
	public static function check() {
		// If comments are globally disabled, nothing to check.
		if ( 'open' !== get_option( 'default_comment_status', 'open' ) ) {
			return null;
		}

		$auto_close = get_option( 'close_comments_for_old_posts', '0' );

		if ( '0' === (string) $auto_close || ! $auto_close ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'Comments are enabled and auto-close for old posts is disabled. All posts remain permanently open to comments, continuously expanding the spam attack surface. Enable automatic comment closing under Settings → Discussion → "Automatically close comments on posts older than X days".', 'thisismyurl-shadow' ),
				'severity'     => 'low',
				'threat_level' => 20,
				'details'      => array(
					'close_comments_for_old_posts' => false,
					'close_comments_days_old'      => null,
				),
			);
		}

		$days = (int) get_option( 'close_comments_days_old', 14 );
		if ( $days > 180 ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %d: number of days */
					__( 'Comments auto-close is enabled but set to %d days, which is longer than recommended. Consider reducing the threshold to 60-90 days to limit spam exposure on older posts.', 'thisismyurl-shadow' ),
					$days
				),
				'severity'     => 'low',
				'threat_level' => 10,
				'details'      => array(
					'close_comments_for_old_posts' => true,
					'close_comments_days_old'      => $days,
				),
			);
		}

		return null;
	}
}
