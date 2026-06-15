<?php
/**
 * Treatment: Disable Media Attachment Pages
 *
 * Sets the wp_attachment_pages_enabled option to 0 (WordPress 6.4+) to
 * disable individual attachment pages. These pages serve no SEO or UX
 * purpose on most sites and are flagged by SEO tools. On WordPress < 6.4
 * the redirect is managed via an option stored by Shadow by Christopher Ross.
 *
 * Risk level: safe — single option update, fully reversible.
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
 * Disables WordPress media attachment pages.
 */
class Treatment_Media_Attachment_Pages extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'media-attachment-pages';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Disable attachment pages.
	 *
	 * @return array
	 */
	public static function apply() {
		if ( version_compare( get_bloginfo( 'version' ), '6.4', '>=' ) ) {
			$previous = get_option( 'wp_attachment_pages_enabled', 1 );
			update_option( 'thisismyurl_shadow_prev_attachment_pages_enabled', $previous, false );
			update_option( 'wp_attachment_pages_enabled', 0 );

			return array(
				'success' => true,
				'message' => __( 'Media attachment pages disabled (WordPress 6.4+ native setting). Attachment URLs now redirect to the parent post or the home page.', 'thisismyurl-shadow' ),
				'details' => array( 'previous_value' => $previous, 'new_value' => 0 ),
			);
		}

		// Pre-6.4: store a Shadow by Christopher Ross option; the plugin bootstrap adds a redirect.
		update_option( 'thisismyurl_shadow_redirect_attachment_pages', true, false );

		return array(
			'success' => true,
			'message' => __( 'Media attachment pages will be redirected. Attachment URLs will redirect to the parent post. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Re-enable attachment pages.
	 *
	 * @return array
	 */
	public static function undo() {
		if ( version_compare( get_bloginfo( 'version' ), '6.4', '>=' ) ) {
			$previous = get_option( 'thisismyurl_shadow_prev_attachment_pages_enabled', 1 );
			update_option( 'wp_attachment_pages_enabled', $previous );
			delete_option( 'thisismyurl_shadow_prev_attachment_pages_enabled' );

			return array(
				'success' => true,
				'message' => sprintf(
								/* translators: %s: restored value (0 or 1). */
					__( 'Attachment pages setting restored to %s.', 'thisismyurl-shadow' ),
					$previous
				),
			);
		}

		delete_option( 'thisismyurl_shadow_redirect_attachment_pages' );

		return array(
			'success' => true,
			'message' => __( 'Attachment page redirect removed. Attachment pages are accessible again.', 'thisismyurl-shadow' ),
		);
	}
}
