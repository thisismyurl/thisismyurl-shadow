<?php
/**
 * Treatment: Remove Pingback Endpoint Disclosure
 *
 * Stores a Christopher Ross Shadow option that instructs the plugin bootstrap to:
 *  - remove_action( 'wp_head', 'pingback_url' )
 *    Suppresses the <link rel="pingback"> tag from every page's <head>.
 *  - remove_filter( 'wp_headers', 'wp_headers_pingback' )
 *    Suppresses the X-Pingback: HTTP response header.
 *
 * Neither action disables xmlrpc.php itself — they only stop WordPress from
 * advertising its location in page markup and HTTP headers. Pingback
 * functionality (if any posts still have pings open) continues to work for
 * clients that already know the endpoint URL.
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
 * Removes the pingback <link> head tag and X-Pingback HTTP response header.
 */
class Treatment_Pingback_Head_Link extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'pingback-head-link';

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
		update_option( 'thisismyurl_shadow_remove_pingback_head_link', true, false );

		return array(
			'success' => true,
			'message' => __( 'The pingback <link> head tag and X-Pingback HTTP header will no longer be output. Your xmlrpc.php endpoint is unaffected. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring default pingback endpoint output.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_pingback_head_link' );

		return array(
			'success' => true,
			'message' => __( 'Pingback head link and X-Pingback header restored to default WordPress output.', 'thisismyurl-shadow' ),
		);
	}
}
