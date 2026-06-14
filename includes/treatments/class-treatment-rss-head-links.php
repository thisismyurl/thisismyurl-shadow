<?php
/**
 * Treatment: Remove RSS Feed Autodiscovery Links
 *
 * Stores a Shadow by Christopher Ross option that instructs the plugin bootstrap to call
 * remove_action( 'wp_head', 'feed_links', 2 ) and
 * remove_action( 'wp_head', 'feed_links_extra', 3 ) on every request.
 * This removes the RSS autodiscovery <link> tags from <head> that
 * WordPress injects by default on every front-end page.
 *
 * Risk level: safe — fully reversible option toggle, no file edits.
 * Removing these tags does not disable the RSS feeds themselves; feed
 * URLs remain accessible to subscribers who already know them. Only the
 * head <link> advertisements are removed.
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
 * Removes WordPress RSS feed autodiscovery link tags from every page's <head>.
 */
class Treatment_Rss_Head_Links extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'rss-head-links';

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
		update_option( 'thisismyurl_shadow_remove_rss_head_links', true, false );

		return array(
			'success' => true,
			'message' => __( 'RSS feed autodiscovery link tags will no longer appear in your page <head>. Your feed URLs remain accessible — only the <head> advertisements are removed. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the toggle, restoring default RSS head link output.
	 *
	 * @return array
	 */
	public static function undo() {
		delete_option( 'thisismyurl_shadow_remove_rss_head_links' );

		return array(
			'success' => true,
			'message' => __( 'RSS feed autodiscovery links restored to <head>.', 'thisismyurl-shadow' ),
		);
	}
}
