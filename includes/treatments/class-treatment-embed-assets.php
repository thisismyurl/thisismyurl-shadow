<?php
/**
 * Treatment: Disable WordPress Embed Assets
 *
 * Stores a Christopher Ross Shadow option that instructs the plugin bootstrap to remove
 * the oEmbed host JavaScript and associated head/footer hooks from front-end
 * output. This prevents other sites from embedding your content via iframes
 * while leaving your own ability to embed third-party content intact.
 *
 * Risk level: safe — fully reversible option toggle, no file edits.
 *
 * Bootstrap responsibilities (applied when option is true):
 *  - remove_action( 'wp_head', 'wp_oembed_add_host_js' )
 *  - remove_action( 'rest_api_init', 'wp_oembed_register_route' )
 *  - remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 )
 *  - remove_action( 'wp_head', 'wp_oembed_add_discovery_links' )
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
 * Disables WordPress outbound embed (oEmbed host) assets.
 */
class Treatment_Embed_Assets extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'embed-assets';

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
		update_option( 'thisismyurl_shadow_remove_embed_assets', true, false );

		return array(
			'success' => true,
			'message' => __( 'WordPress embed host script removed from <head>. Other sites will no longer be able to embed your pages via WordPress oEmbed. Your own ability to embed external content is unaffected. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring the default embed host output.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_embed_assets' );

		return array(
			'success' => true,
			'message' => __( 'WordPress embed host script restored to default behavior.', 'thisismyurl-shadow' ),
		);
	}
}
