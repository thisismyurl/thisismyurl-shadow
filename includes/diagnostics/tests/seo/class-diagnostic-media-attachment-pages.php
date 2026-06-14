<?php
/**
 * Attachment Pages Diagnostic
 *
 * Checks whether WordPress is generating publicly accessible attachment pages
 * for media files, which create low-value indexed URLs that dilute SEO authority.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Media_Attachment_Pages Class
 *
 * Checks the WordPress attachment-page option (6.4+) and SEO plugin settings
 * (Yoast/Rank Math) to determine whether thin attachment pages are enabled.
 *
 * @since 0.6095
 */
class Diagnostic_Media_Attachment_Pages extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'media-attachment-pages';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Attachment Pages';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is generating publicly accessible attachment pages for media files, which create low-value indexed URLs that can dilute SEO authority.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'seo';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * On WordPress 6.4+ reads the wp_attachment_pages_enabled option; on older
	 * versions checks whether Yoast or Rank Math is redirecting attachment URLs,
	 * flagging sites where thin attachment pages are publicly accessible.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when attachment pages are accessible, null when healthy.
	 */
	public static function check() {
		global $wp_version;

		// WordPress 6.4+ stores attachment page setting as an option (0 = disabled, 1 = enabled).
		if ( version_compare( $wp_version, '6.4', '>=' ) ) {
			$attachment_pages_enabled = (int) get_option( 'wp_attachment_pages_enabled', 0 );
			if ( 1 === $attachment_pages_enabled ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => __( 'Media attachment pages are enabled. These are thin-content pages automatically created for every uploaded image or file, and they add no SEO value. Disable them under Settings → Media, or let your SEO plugin redirect them to the parent post.', 'thisismyurl-shadow' ),
					'severity'     => 'medium',
					'threat_level' => 35,
					'details'      => array( 'attachment_pages_enabled' => true, 'wp_version' => $wp_version ),
				);
			}
			return null;
		}

		// WordPress < 6.4: attachment pages are always enabled; check if an SEO plugin is redirecting them.
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$has_yoast      = in_array( 'wordpress-seo/wp-seo.php', $active_plugins, true )
		               || in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active_plugins, true );

		if ( $has_yoast ) {
			$wpseo = get_option( 'wpseo', array() );
			if ( ! empty( $wpseo['redirectattachments'] ) ) {
				return null; // Yoast is redirecting attachment pages to media file.
			}
		}

		$has_rankmath = in_array( 'seo-by-rank-math/rank-math.php', $active_plugins, true )
		             || in_array( 'seo-by-rank-math-pro/rank-math-pro.php', $active_plugins, true );
		if ( $has_rankmath ) {
			// Rank Math redirects attachment pages by default when active.
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Media attachment pages are active and not being redirected. These thin-content pages are created automatically for every uploaded file and can dilute SEO authority. Install an SEO plugin such as Yoast SEO or Rank Math and enable attachment page redirection, or upgrade to WordPress 6.4+ which disables them by default.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array( 'attachment_pages_enabled' => true, 'wp_version' => $wp_version ),
		);
	}
}
