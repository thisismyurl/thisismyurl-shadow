<?php
/**
 * Terms of Service Page Published Diagnostic
 *
 * A Terms of Service (or Terms and Conditions) page is a legal requirement
 * for nearly any site that accepts user registrations, purchases, or form
 * submissions. GDPR Article 13 requires disclosure of how data is processed,
 * most payment processors (Stripe, PayPal) mandate a terms link in checkout
 * flows, and app store policies require one for any software distribution.
 * Without a published terms page the site has no enforceable user agreement.
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
 * Diagnostic_Terms_Of_Service_Page Class
 *
 * Scans all published pages for slugs and titles that match common Terms of
 * Service naming conventions. Returns null as soon as a match is found.
 *
 * @since 0.6095
 */
class Diagnostic_Terms_Of_Service_Page extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'terms-of-service-page';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Terms of Service Page Published';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks that a published Terms of Service or Terms and Conditions page exists. A missing terms page leaves the site without a legally enforceable user agreement and may violate payment processor and privacy regulation requirements.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'design';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Slug fragments and title substrings associated with terms pages.
	 *
	 * @var string[]
	 */
	private const TERMS_KEYWORDS = array(
		'terms',
		'tos',
		'legal',
		'user-agreement',
		'conditions',
	);

	/**
	 * Run the diagnostic check.
	 *
	 * Fetches all published pages and checks whether any of them contain
	 * a recognised terms keyword in either the post slug or post title.
	 * The check is intentionally broad to allow for custom naming conventions
	 * (e.g. "Legal Notice", "User Agreement") without false negatives.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no terms page is found, null when present.
	 */
	public static function check() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'all',
				'no_found_rows'  => true,
			)
		);

		foreach ( (array) $pages as $page ) {
			$slug  = strtolower( (string) $page->post_name );
			$title = strtolower( (string) $page->post_title );

			foreach ( self::TERMS_KEYWORDS as $keyword ) {
				if ( str_contains( $slug, $keyword ) || str_contains( $title, $keyword ) ) {
					return null;
				}
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No published Terms of Service or Terms and Conditions page was found. A terms page is required by GDPR, most payment processors (Stripe, PayPal), and app store policies for any site accepting registrations, purchases, or user-generated content. Without one, the site has no enforceable user agreement and the operator may face legal liability.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 50,
			'details'      => array(
				'fix' => __( 'Create and publish a page titled "Terms of Service", "Terms and Conditions", or similar. Use a legal template appropriate to your jurisdiction, reviewed by a qualified professional. Link to it from your site footer and any sign-up or checkout flows.', 'thisismyurl-shadow' ),
			),
		);
	}
}
