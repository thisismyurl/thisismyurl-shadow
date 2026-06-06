<?php
/**
 * Treatment: Remove oEmbed Discovery Links
 *
 * Stores a Christopher Ross Shadow option that instructs the plugin bootstrap to call
 * remove_action( 'wp_head', 'wp_oembed_add_discovery_links' ) and
 * remove_action( 'wp_head', 'wp_oembed_add_host_js' ) on every request.
 * oEmbed embedding by third-party sites continues to work; only the
 * advertising/discovery tags are removed.
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
 * Removes oEmbed discovery links and host JS from <head>.
 */
class Treatment_Oembed_Discovery_Links extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'oembed-discovery-links';

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
		update_option( 'thisismyurl_shadow_remove_oembed_discovery_links', true, false );

		return array(
			'success' => true,
			'message' => __( 'oEmbed discovery links removed from <head>. Content embedding on your site is unaffected. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring the default <head> output.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_oembed_discovery_links' );

		return array(
			'success' => true,
			'message' => __( 'oEmbed discovery links restored to <head>.', 'thisismyurl-shadow' ),
		);
	}
}
