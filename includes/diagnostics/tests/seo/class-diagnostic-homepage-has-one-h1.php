<?php
/**
 * Homepage Has a Single H1 Diagnostic
 *
 * Checks that the homepage contains exactly one H1 heading. Missing or
 * multiple H1 tags confuse search engines about the primary topic of the page.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Request_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Homepage_Has_One_H1 Class
 *
 * Fetches the homepage HTML and counts H1 elements to verify there is
 * exactly one, flagging pages with zero or multiple H1 headings.
 *
 * @since 0.6095
 */
class Diagnostic_Homepage_Has_One_H1 extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'homepage-has-one-h1';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Homepage Has a Single H1';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks that the homepage contains exactly one H1 heading. Missing or multiple H1 tags confuse search engines about the primary topic of the page.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'seo';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Fetches the homepage HTML via the guarded diagnostic request helper and
	 * counts H1 elements,
	 * returning a finding when zero or more than one H1 is found.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when H1 count is not exactly one, null when healthy.
	 */
	public static function check() {
		$home_url = home_url( '/' );
		$result = Diagnostic_Request_Helper::get_result( $home_url, array(
			'timeout'    => 7,
			'user-agent' => 'Christopher Ross Shadow-Diagnostic/1.0',
		) );

		if ( empty( $result['success'] ) || empty( $result['response'] ) || ! is_array( $result['response'] ) ) {
			return null; // Cannot test — skip to avoid false positives.
		}

		$response = $result['response'];
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return null;
		}

		preg_match_all( '/<h1[\s>]/i', $body, $matches );
		$h1_count = count( $matches[0] );

		if ( 1 === $h1_count ) {
			return null;
		}

		if ( 0 === $h1_count ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'The homepage has no H1 heading. Search engines use the H1 to understand the primary topic of a page. Add a single descriptive H1 heading to the homepage that aligns with the target keyword for the page.', 'thisismyurl-shadow' ),
				'severity'     => 'medium',
				'threat_level' => 40,
				'details'      => array(
					'h1_count'    => 0,
					'checked_url' => $home_url,
					'fix'         => __( 'Add exactly one H1 heading to your homepage content or theme template.', 'thisismyurl-shadow' ),
				),
			);
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of H1 tags found */
				__( 'The homepage has %d H1 headings. Multiple H1 tags dilute the primary topic signal for search engines. Review the page structure and ensure only one H1 is used per page.', 'thisismyurl-shadow' ),
				$h1_count
			),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'h1_count'    => $h1_count,
				'checked_url' => $home_url,
				'fix'         => __( 'Review your page template and blocks to ensure only one H1 element appears on the homepage.', 'thisismyurl-shadow' ),
			),
		);
	}
}
