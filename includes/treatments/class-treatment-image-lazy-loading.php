<?php
/**
 * Treatment: Re-enable WordPress native image lazy loading
 *
 * WordPress 5.5+ automatically adds `loading="lazy"` to `<img>` tags via the
 * `wp_lazy_loading_enabled` filter. Some themes explicitly disable this by
 * adding `add_filter('wp_lazy_loading_enabled', '__return_false')`, causing
 * all images on the page to be requested on load regardless of whether they
 * are in the viewport — increasing initial page weight and slowing First
 * Contentful Paint.
 *
 * This treatment stores a flag that tells the Christopher Ross Shadow bootstrap to re-add the
 * `wp_lazy_loading_enabled` filter at priority 999 returning `true`, overriding
 * any theme or plugin filter that had disabled it.
 *
 * Note: this treatment has no effect on WordPress < 5.5 (which does not support
 * native lazy loading). The recommendation in that case is to update WordPress.
 *
 * Undo: deletes the flag; bootstrap stops applying the override filter.
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
 * Re-enables wp_lazy_loading_enabled at priority 999 to override theme disablers.
 */
class Treatment_Image_Lazy_Loading extends Treatment_Base {

	/** @var string */
	protected static $slug = 'image-lazy-loading';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the flag so the bootstrap overrides any __return_false filter.
	 *
	 * @return array
	 */
	public static function apply(): array {
		// Inform if WordPress version is too old for native lazy loading.
		if ( version_compare( get_bloginfo( 'version' ), '5.5', '<' ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: WordPress version */
					__( 'WordPress %s does not support native lazy loading (introduced in 5.5). Update WordPress to use this treatment.', 'thisismyurl-shadow' ),
					esc_html( get_bloginfo( 'version' ) )
				),
			);
		}

		update_option( 'thisismyurl_shadow_reenable_lazy_loading', true );

		return array(
			'success' => true,
			'message' => __( 'Native image lazy loading re-enabled at high priority. The wp_lazy_loading_enabled filter will return true (priority 999), overriding any theme or plugin that disabled it. Takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the flag; bootstrap stops applying the override filter.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_reenable_lazy_loading' );

		return array(
			'success' => true,
			'message' => __( 'Lazy loading override removed. Theme or plugin filters will control wp_lazy_loading_enabled again from the next page load.', 'thisismyurl-shadow' ),
		);
	}
}
