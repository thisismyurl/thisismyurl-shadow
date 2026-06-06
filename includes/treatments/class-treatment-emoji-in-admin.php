<?php
/**
 * Treatment: Disable Emoji Scripts in WordPress Admin
 *
 * WordPress loads the same emoji detection script and SVG stylesheet in the
 * admin as on the front end. On modern operating systems and browsers, emoji
 * render perfectly via native OS support; the WordPress scripts add two extra
 * HTTP requests and a small JS runtime cost to every admin page load without
 * providing any visible benefit.
 *
 * This treatment stores a flag (`thisismyurl_shadow_remove_emoji_admin`) that tells the
 * Christopher Ross Shadow bootstrap to remove the admin-specific emoji hooks:
 *
 *   remove_action( 'admin_print_scripts', 'print_emoji_detection_script' )
 *   remove_action( 'admin_print_styles',  'print_emoji_styles' )
 *
 * This is distinct from the existing `emoji-assets` treatment which covers
 * the front end. Emoji characters continue to render natively everywhere.
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
 * Removes emoji detection scripts and styles from the WordPress admin.
 */
class Treatment_Emoji_In_Admin extends Treatment_Base {

	/** @var string */
	protected static $slug = 'emoji-in-admin';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the admin emoji removal flag.
	 *
	 * Bootstrap responsibility (applied when option is `true`):
	 *   remove_action( 'admin_print_scripts', 'print_emoji_detection_script' )
	 *   remove_action( 'admin_print_styles',  'print_emoji_styles' )
	 *
	 * @return array
	 */
	public static function apply(): array {
		update_option( 'thisismyurl_shadow_remove_emoji_admin', true, false );

		return array(
			'success' => true,
			'message' => __( 'WordPress emoji detection script and SVG stylesheet will no longer load in the admin. Emoji characters continue to render natively via your OS and browser. Takes effect on the next admin page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the flag, restoring the default admin emoji asset injection.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_remove_emoji_admin' );

		return array(
			'success' => true,
			'message' => __( 'WordPress admin emoji assets restored to default behavior.', 'thisismyurl-shadow' ),
		);
	}
}
