<?php
/**
 * Legal Pages Linked in Footer Diagnostic
 *
 * Checks whether the published privacy policy and other legal pages are linked
 * somewhere in the site footer menus for compliance and visitor trust.
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
 * Diagnostic_Legal_Pages_Linked_Footer Class
 *
 * Verifies that the privacy policy page is linked in at least one registered
 * navigation menu, returning a medium-severity finding when it is not.
 *
 * @since 0.6095
 */
class Diagnostic_Legal_Pages_Linked_Footer extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'legal-pages-linked-footer';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Legal Pages Linked in Footer';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the published privacy policy and other legal pages are linked somewhere in the site footer for compliance and visitor trust.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Uses WP_Settings::has_published_privacy_policy_page() to confirm a valid
	 * page exists. If no menus are registered, returns null (unverifiable).
	 * Otherwise iterates all registered nav menus and their items, returning null
	 * when the privacy policy page ID is found in any menu. Returns a
	 * medium-severity finding when the page exists but is not linked anywhere.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when privacy policy is not in any menu, null when healthy.
	 */
	public static function check() {
		// Verify there is a published privacy policy page to look for.
		if ( ! WP_Settings::has_published_privacy_policy_page() ) {
			// No privacy policy page exists at all — covered by privacy-policy-page-set diagnostic.
			return null;
		}

		$privacy_page_id = WP_Settings::get_privacy_policy_page_id();

		// Check every registered nav menu for a link to the privacy policy page.
		$menus = wp_get_nav_menus();
		if ( empty( $menus ) || ! is_array( $menus ) ) {
			// No menus registered — can't verify.
			return null;
		}

		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
			if ( empty( $items ) || ! is_array( $items ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( 'post_type' === $item->type && (int) $item->object_id === $privacy_page_id ) {
					return null;
				}
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'A privacy policy page exists but is not linked in any of your registered navigation menus. Legal pages (Privacy Policy, Terms of Service) should be accessible from the footer of every page to meet GDPR, CCPA, and general compliance requirements.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array(
				'privacy_page_id'     => $privacy_page_id,
				'menus_checked'       => count( $menus ),
				'found_in_nav_menu'   => false,
			),
		);
	}
}
