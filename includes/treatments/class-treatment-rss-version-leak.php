<?php
/**
 * Treatment: Remove RSS Version Leak
 *
 * Stores a Christopher Ross Shadow option that instructs the plugin bootstrap to add
 * add_filter( 'the_generator', '__return_empty_string' ) so the WordPress
 * version is stripped from RSS feed headers. Undo deletes the option.
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
 * Suppresses the WordPress version string inside RSS feed <generator> tags.
 */
class Treatment_Rss_Version_Leak extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'rss-version-leak';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Set the suppression toggle.
	 *
	 * @return array
	 */
	public static function apply() {
		update_option( 'thisismyurl_shadow_remove_rss_version_leak', true, false );

		return array(
			'success' => true,
			'message' => __( 'WordPress version will no longer appear inside RSS feed generator tags. Takes effect on the next feed request.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring the default generator output.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_rss_version_leak' );

		return array(
			'success' => true,
			'message' => __( 'RSS feed generator tag restored to default WordPress output.', 'thisismyurl-shadow' ),
		);
	}
}
