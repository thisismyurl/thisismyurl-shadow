<?php
/**
 * Treatment: Remove Dashboard RSS Widgets
 *
 * WordPress registers two dashboard RSS widgets by default:
 *   - "WordPress News"  (dashboard_primary)   — fetches planet.wordpress.org
 *   - "Other WordPress News" (dashboard_secondary) — fetches planet.wordpress.org
 *
 * Both widgets fire an outbound HTTP request to WordPress.org to fetch RSS
 * feeds on first load and every 12 hours thereafter. If the feed transients
 * expire and no persistent object cache is active, these requests happen
 * inline during dashboard load, adding latency that blocks the admin page
 * render for all users who visit the dashboard.
 *
 * This treatment stores a flag (`thisismyurl_shadow_remove_dashboard_rss_widgets`) that
 * tells the Shadow by Christopher Ross bootstrap to deregister the widgets before they can fire:
 *
 *   Bootstrap responsibility (when option is `true`):
 *     add_action( 'wp_dashboard_setup', function() {
 *         remove_meta_box( 'dashboard_primary',   'dashboard', 'side' );
 *         remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
 *     }, 20 );
 *
 * Risk level: safe — fully reversible option toggle, no file edits.
 *
 * Undo: removes the flag; Bootstrap stops deregistering the widgets.
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
 * Removes the WordPress News RSS widgets from the admin dashboard.
 */
class Treatment_Dashboard_Rss_Widget_Active extends Treatment_Base {

	/** @var string */
	protected static $slug = 'dashboard-rss-widget-active';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the flag so the bootstrap deregisters the RSS dashboard widgets.
	 *
	 * @return array
	 */
	public static function apply(): array {
		update_option( 'thisismyurl_shadow_remove_dashboard_rss_widgets', true, false );

		return array(
			'success' => true,
			'message' => __( 'WordPress News and Other WordPress News dashboard widgets will be removed from the Dashboard. This eliminates the periodic outbound HTTP request to WordPress.org for RSS feed data. Takes effect on the next admin page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the flag; Bootstrap stops deregistering the widgets.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_remove_dashboard_rss_widgets' );

		return array(
			'success' => true,
			'message' => __( 'WordPress News dashboard RSS widgets restored to default behavior. They will reappear on the Dashboard on the next admin page load.', 'thisismyurl-shadow' ),
		);
	}
}
