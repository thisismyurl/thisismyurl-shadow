<?php
/**
 * Treatment: Disable WordPress Emoji Assets
 *
 * Stores a Christopher Ross Shadow option that instructs the plugin bootstrap to dequeue
 * and remove all WordPress emoji-related scripts and styles from the front
 * end and admin. Emoji characters continue to render natively via the
 * operating system or browser; only the WordPress-injected detection script,
 * SVG stylesheet, and DNS prefetch tag are removed.
 *
 * Risk level: safe — fully reversible option toggle, no file edits.
 *
 * Bootstrap responsibilities (applied when option is true):
 *  - remove_action( 'wp_head', 'print_emoji_detection_script', 7 )
 *  - remove_action( 'admin_print_scripts', 'print_emoji_detection_script' )
 *  - remove_action( 'wp_print_styles', 'print_emoji_styles' )
 *  - remove_action( 'admin_print_styles', 'print_emoji_styles' )
 *  - remove_filter( 'the_content_feed', 'wp_staticize_emoji' )
 *  - remove_filter( 'comment_text_rss', 'wp_staticize_emoji' )
 *  - remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' )
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
 * Disables all WordPress emoji asset injection.
 */
class Treatment_Emoji_Assets extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'emoji-assets';

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
		update_option( 'thisismyurl_shadow_remove_emoji_assets', true, false );

		return array(
			'success' => true,
			'message' => __( 'WordPress emoji detection script and SVG stylesheet will no longer load. Emoji characters render natively via your OS/browser. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring the default emoji asset injection.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_emoji_assets' );

		return array(
			'success' => true,
			'message' => __( 'WordPress emoji assets restored to default behavior.', 'thisismyurl-shadow' ),
		);
	}
}
