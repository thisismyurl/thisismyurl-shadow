<?php
/**
 * Update Services Diagnostic
 *
 * Checks whether the WordPress ping/update services list has been intentionally
 * configured for the site's publishing model.
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
 * Diagnostic_Update_Services_Intentional Class
 *
 * Reads the ping_sites option and the published post count to determine whether
 * a non-blogging site still has the default aggregator URL configured.
 *
 * @since 0.6095
 */
class Diagnostic_Update_Services_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'update-services-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Update Services';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress ping/update services list has been intentionally configured. For non-blog business sites that do not publish regular posts, auto-pinging blog aggregators adds no value.';

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
	protected static $impact = 'Pinging blog aggregators on every publish leaks site activity to third parties and provides no benefit to non-blogging business sites.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the ping_sites option. If empty or cleared, returns null immediately.
	 * Otherwise counts published 'post' type posts using wp_count_posts(). When
	 * fewer than three posts are published, pinging blog aggregators on every
	 * save provides no benefit and leaks site activity; returns a low-severity
	 * finding. Returns null when the site actively publishes posts.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when ping_sites is unreviewed, null when healthy.
	 */
	public static function check() {
		$ping_sites = trim( (string) get_option( 'ping_sites', '' ) );

		if ( '' === $ping_sites ) {
			// Already cleared — intentional.
			return null;
		}

		$counts = wp_count_posts( 'post' );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;

		// If the site actively publishes posts, pinging is intentional.
		if ( $published >= 3 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress is configured to ping blog aggregators (Update Services) every time content is published, but this site publishes very few posts and is unlikely to benefit from blog pings. For non-blog business sites, clear the Update Services list under Settings → Writing → Update Services to stop sending unsolicited pings.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 5,
			'details'      => array(
				'ping_sites_configured' => true,
				'published_post_count'  => $published,
			),
		);
	}
}
