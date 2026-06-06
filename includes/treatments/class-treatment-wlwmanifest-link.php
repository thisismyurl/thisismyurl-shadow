<?php
/**
 * Treatment: Remove WLW Manifest Link
 *
 * Stores a Christopher Ross Shadow option that instructs the plugin bootstrap to call
 * remove_action( 'wp_head', 'wlwmanifest_link' ) on every request. This
 * removes the legacy Windows Live Writer discovery tag from <head>.
 *
 * Risk level: safe — fully reversible option toggle, no file edits.
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
 * Removes the Windows Live Writer manifest link from <head>.
 */
class Treatment_Wlwmanifest_Link extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'wlwmanifest-link';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Set the removal toggle.
	 *
	 * @return array
	 */
	public static function apply() {
		update_option( 'thisismyurl_shadow_remove_wlwmanifest_link', true, false );

		return array(
			'success' => true,
			'message' => __( 'WLW Manifest link will no longer appear in your <head>. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring the default output.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_wlwmanifest_link' );

		return array(
			'success' => true,
			'message' => __( 'WLW Manifest link restored to <head>.', 'thisismyurl-shadow' ),
		);
	}
}
