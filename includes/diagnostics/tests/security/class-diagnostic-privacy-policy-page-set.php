<?php
/**
 * Privacy Policy Page Set Diagnostic
 *
 * Checks whether a privacy policy page has been created and designated in
 * WordPress settings, as required for GDPR and similar regulations.
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
 * Diagnostic_Privacy_Policy_Page_Set Class
 *
 * Reads the wp_page_for_privacy_policy option and confirms the assigned page
 * is published, flagging sites that have no compliant privacy policy page.
 *
 * @since 0.6095
 */
class Diagnostic_Privacy_Policy_Page_Set extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'privacy-policy-page-set';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Privacy Policy Page Set';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a privacy policy page has been created and designated in WordPress settings, as required for legal compliance under GDPR and similar regulations.';

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
	 * Confirms a privacy policy page ID is set and the page is published,
	 * returning a medium-severity finding when the requirement is not met.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no privacy policy page is set, null when healthy.
	 */
	public static function check() {
		if ( WP_Settings::has_published_privacy_policy_page() ) {
			return null;
		}

		$page_id = WP_Settings::get_privacy_policy_page_id();

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No published privacy policy page is assigned in WordPress settings. GDPR, CCPA, and most other privacy regulations require a publicly accessible privacy policy. Create and publish a privacy policy page, then assign it under Settings > Privacy.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 45,
			'details'      => array(
				'page_id'   => $page_id,
				'published' => false,
			),
		);
	}
}
