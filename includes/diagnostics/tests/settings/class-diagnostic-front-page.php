<?php
/**
 * Front Page Configured Diagnostic
 *
 * Checks whether WordPress is set to show a static front page and that the
 * assigned page is published and accessible.
 *
 * @package    Christopher Ross Shadow
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
 * Diagnostic_Front_Page Class
 *
 * Reads the show_on_front and page_on_front WordPress options. When a static
 * front page is selected but no valid published page is assigned, returns a
 * medium-severity finding.
 *
 * @since 0.6095
 */
class Diagnostic_Front_Page extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'front-page';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Front Page';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is set to show a static front page and that the assigned page is published and accessible.';

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
	 * Uses WP_Settings::get_front_page_display() to determine the reading setting.
	 * When set to 'latest_posts' the check passes. When set to 'page', validates
	 * that the assigned page ID exists and has 'publish' status, returning a
	 * medium-severity finding when the page is missing or unpublished.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when front page is misconfigured, null when healthy.
	 */
	public static function check() {
		$display = WP_Settings::get_front_page_display();

		// Showing latest posts is a valid, intentional choice (blogs, news sites).
		if ( 'latest_posts' === $display ) {
			return null;
		}

		// Configured to show a static page — verify the page is assigned and published.
		$front_id = WP_Settings::get_front_page_id();
		if ( $front_id > 0 ) {
			$page = get_post( $front_id );
			if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
				return null;
			}
		}

		$issue = $front_id > 0
			? sprintf(
				/* translators: %d: page ID */
				__( 'Front page is set to display a static page but post ID %d is not published.', 'thisismyurl-shadow' ),
				$front_id
			)
			: __( 'Front page is configured to show a static page but no page has been selected.', 'thisismyurl-shadow' );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your reading settings say the front page should show a static page, but the selected page is either missing or not published. Visitors will see an empty or unexpected front page.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'issue'            => $issue,
				'show_on_front'    => 'page',
				'page_on_front_id' => $front_id,
			),
		);
	}
}
