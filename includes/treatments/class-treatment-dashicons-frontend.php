<?php
/**
 * Treatment: Remove Dashicons for non-logged-in visitors
 *
 * The WordPress admin icon font (Dashicons) is registered globally and loads
 * for every visitor by default. However, Dashicons are only needed by users
 * who see the admin toolbar (i.e. logged-in users). For public visitors who
 * never see the toolbar, loading Dashicons is wasted CSS and font data on
 * every page request.
 *
 * This treatment stores a flag that tells the Shadow by Christopher Ross bootstrap to dequeue
 * and deregister dashicons on the wp_enqueue_scripts hook for non-authenticated
 * visitors. It is skipped if the active theme's stylesheet explicitly depends
 * on dashicons, since removing it would break the theme.
 *
 * Undo: deletes the flag; bootstrap stops deregistering dashicons.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes Dashicons from the frontend for non-logged-in users.
 */
class Treatment_Dashicons_Frontend extends Treatment_Base {

	/** @var string */
	protected static $slug = 'dashicons-frontend';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the flag so the bootstrap dequeues dashicons for logged-out visitors.
	 *
	 * @return array
	 */
	public static function apply(): array {
		// Guard: if the active theme stylesheet depends on dashicons, do not remove.
		$style_deps = wp_styles()->registered;

		if ( isset( $style_deps[ get_stylesheet() ] ) ) {
			$stylesheet_deps = (array) $style_deps[ get_stylesheet() ]->deps;
			if ( in_array( 'dashicons', $stylesheet_deps, true ) ) {
				return array(
					'success' => false,
					'message' => __( 'The active theme stylesheet depends on Dashicons. Removing it would break the theme\'s appearance. Enable theme support for icons as standalone SVG assets before removing Dashicons.', 'thisismyurl-shadow' ),
				);
			}
		}

		update_option( 'thisismyurl_shadow_dequeue_dashicons_frontend', true );

		return array(
			'success' => true,
			'message' => __( 'Dashicons will be dequeued for non-logged-in visitors from the next page load. Logged-in users who require the admin toolbar are unaffected.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the flag; bootstrap stops dequeuing dashicons for public visitors.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_dequeue_dashicons_frontend' );

		return array(
			'success' => true,
			'message' => __( 'Dashicons frontend removal disabled. WordPress will include Dashicons for all visitors again from the next page load.', 'thisismyurl-shadow' ),
		);
	}
}
