<?php
/**
 * Primary Navigation Assigned Diagnostic
 *
 * Checks whether the primary/main navigation menu location registered by
 * the active theme has a menu assigned to it.
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
 * Diagnostic_Primary_Navigation_Assigned Class
 *
 * @since 0.6095
 */
class Diagnostic_Primary_Navigation_Assigned extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'primary-navigation-assigned';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Primary Navigation Assigned';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the primary or main navigation menu location registered by the active theme has a menu assigned, ensuring visitors can navigate the site.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'design';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads all nav menu locations registered by the active theme. Checks
	 * common primary-location keys first (primary, main, header, etc.), then
	 * falls back to the first registered location. Calls has_nav_menu() to
	 * determine whether a menu is assigned. Returns null when assigned, or a
	 * medium-severity finding when no menu is assigned to the primary location.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when primary navigation is unassigned, null when healthy.
	 */
	public static function check() {
		// Get all registered navigation menu locations for the active theme.
		$locations = get_registered_nav_menus();

		if ( empty( $locations ) ) {
			return null; // Theme does not register nav menu locations — cannot check.
		}

		// Check if a primary/main nav location has a menu assigned.
		$primary_keys = array( 'primary', 'main', 'header', 'top', 'main-menu', 'header-menu', 'primary-menu' );

		$primary_location     = null;
		$primary_location_key = null;

		foreach ( $primary_keys as $key ) {
			if ( isset( $locations[ $key ] ) ) {
				$primary_location     = $locations[ $key ];
				$primary_location_key = $key;
				break;
			}
		}

		if ( null === $primary_location_key ) {
			// Fall back to the first registered location.
			$primary_location_key = array_key_first( $locations );
			$primary_location     = $locations[ $primary_location_key ];
		}

		if ( has_nav_menu( $primary_location_key ) ) {
			return null; // Primary navigation is assigned.
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: navigation location label */
				__( 'No menu has been assigned to the "%s" navigation location. Without a primary navigation menu, visitors cannot easily navigate the site, which increases bounce rates and harms usability. Create a menu and assign it under Appearance → Menus.', 'thisismyurl-shadow' ),
				$primary_location
			),
			'severity'     => 'medium',
			'threat_level' => 30,
			'details'      => array(
				'location_key'   => $primary_location_key,
				'location_label' => $primary_location,
				'menu_assigned'  => false,
			),
		);
	}
}
