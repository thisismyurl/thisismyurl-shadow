<?php
/**
 * Posts Per Page Optimized Diagnostic
 *
 * Checks whether the "Blog pages show at most" setting is within a sensible
 * range. Very high values load excessive content on a single page, degrading
 * performance; very low values bury content and hurt crawlability.
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
 * Diagnostic_Posts_Per_Page_Optimized Class
 *
 * Reads the posts_per_page WordPress option and returns a low-severity finding
 * when the value is outside the acceptable range of 3–20 posts per page.
 *
 * @since 0.6095
 */
class Diagnostic_Posts_Per_Page_Optimized extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'posts-per-page-optimized';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Posts Per Page Optimized';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the "Blog pages show at most" setting is within a sensible range. Very high values load excessive content on a single page, slowing performance; very low values bury content and hurt crawlability.';

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
	protected static $impact = 'Extreme posts-per-page values either degrade page load time for visitors or prevent search engines from discovering older content.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the posts_per_page WordPress option. Returns null when the value is
	 * between 3 and 20 inclusive. Returns a low-severity finding when the value
	 * is above 20 (performance risk) or below 3 (UX and SEO risk), including the
	 * actual configured value and a recommended range in the details.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when posts-per-page is out of range, null when healthy.
	 */
	public static function check() {
		$ppp = (int) get_option( 'posts_per_page', 10 );

		if ( $ppp >= 3 && $ppp <= 20 ) {
			return null;
		}

		if ( $ppp > 20 ) {
			$description = sprintf(
				/* translators: %d: posts per page setting */
				__( 'Your site is set to display %d posts per page. Loading this many posts at once increases page weight, server memory usage, and time to first byte. A value between 6 and 12 is recommended for most small business sites.', 'thisismyurl-shadow' ),
				$ppp
			);
		} else {
			$description = sprintf(
				/* translators: %d: posts per page setting */
				__( 'Your site is set to display only %d post(s) per page. This restricts how much content visitors and search engines see on listing pages, reducing crawlability and user engagement. A value between 6 and 12 is recommended for most sites.', 'thisismyurl-shadow' ),
				$ppp
			);
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => $description,
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'posts_per_page'   => $ppp,
				'recommended_range' => '3–20',
			),
		);
	}
}
