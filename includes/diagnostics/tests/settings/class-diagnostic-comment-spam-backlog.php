<?php
/**
 * Comment Spam Backlog Managed Diagnostic
 *
 * Checks that the spam comment queue is not excessively large. A large backlog
 * wastes database space and signals that spam filtering is not working.
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
 * Diagnostic_Comment_Spam_Backlog Class
 *
 * Queries the WordPress comments table for the count of spam-status comments
 * and returns a severity-scaled finding when the backlog exceeds safe thresholds.
 *
 * @since 0.6095
 */
class Diagnostic_Comment_Spam_Backlog extends Diagnostic_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'comment-spam-backlog';

	/**
	 * @var string
	 */
	protected static $title = 'Comment Spam Backlog Managed';

	/**
	 * @var string
	 */
	protected static $description = 'Checks that the spam comment queue is not excessively large. A large backlog wastes database space and signals that spam filtering is not working.';

	/**
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
	 * Counts spam comments using get_comments() with status 'spam'. Returns null
	 * when the count is below 50. Returns a low-severity finding for 50-499 spam
	 * comments, and a medium-severity finding for 500 or more.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when spam backlog is excessive, null when healthy.
	 */
	public static function check() {
		$spam_count = (int) get_comments( array(
			'status' => 'spam',
			'count'  => true,
		) );

		if ( $spam_count < 50 ) {
			return null;
		}

		$severity     = $spam_count >= 500 ? 'medium' : 'low';
		$threat_level = $spam_count >= 500 ? 30 : 15;

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of spam comments */
				_n(
					'There is %d spam comment sitting in the spam queue. Accumulated spam wastes database space and can slow comment queries. Empty the spam queue under Comments → Spam, and ensure a spam filtering plugin such as Akismet is active.',
					'There are %d spam comments sitting in the spam queue. Accumulated spam wastes database space and can slow comment queries. Empty the spam queue under Comments → Spam, and ensure a spam filtering plugin such as Akismet is active.',
					$spam_count,
					'thisismyurl-shadow'
				),
				$spam_count
			),
			'severity'     => $severity,
			'threat_level' => $threat_level,
			'details'      => array(
				'spam_comment_count' => $spam_count,
			),
		);
	}
}
