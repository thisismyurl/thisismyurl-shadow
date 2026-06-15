<?php
/**
 * Treatment: Remove Shortlink Head Tag
 *
 * Stores a Shadow by Christopher Ross option that instructs the plugin bootstrap to call
 * remove_action( 'wp_head', 'wp_shortlink_wp_head' ) on every request.
 * Shortlinks via the API and wp_get_shortlink() continue to work; only
 * the auto-injected <link rel="shortlink"> tag is removed.
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
 * Removes the auto-injected shortlink tag from <head>.
 */
class Treatment_Shortlink_Head_Tag extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'shortlink-head-tag';

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
		update_option( 'thisismyurl_shadow_remove_shortlink_head_tag', true, false );

		return array(
			'success' => true,
			'message' => __( 'Shortlink <link> tag removed from <head>. Shortlinks continue to work via the API. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring the default <head> output.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_shortlink_head_tag' );

		return array(
			'success' => true,
			'message' => __( 'Shortlink <link> tag restored to <head>.', 'thisismyurl-shadow' ),
		);
	}
}
