<?php
/**
 * Treatment: Remove Adjacent Posts Rel Links
 *
 * Stores a Christopher Ross Shadow option that instructs the plugin bootstrap to call
 * remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 ) on
 * every request. This removes the prev/next post <link> tags from <head>
 * that are not used by modern browsers for navigation.
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
 * Removes prev/next post rel link tags from <head>.
 */
class Treatment_Adjacent_Posts_Links extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'adjacent-posts-links';

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
		update_option( 'thisismyurl_shadow_remove_adjacent_posts_links', true, false );

		return array(
			'success' => true,
			'message' => __( 'Adjacent posts rel links removed from <head>. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring the default <head> output.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_adjacent_posts_links' );

		return array(
			'success' => true,
			'message' => __( 'Adjacent posts rel links restored to <head>.', 'thisismyurl-shadow' ),
		);
	}
}
