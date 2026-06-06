<?php
/**
 * Treatment: Re-enable the WordPress large-image scaling threshold
 *
 * Since WordPress 5.3, uploaded images wider than a configurable threshold
 * (default 2560 px) are automatically scaled down and the original is stored
 * as a "-scaled" variant. This prevents 10–20 MB DSLR and smartphone RAW-
 * quality uploads from being served to visitors.
 *
 * The threshold is controlled by the `big_image_size_threshold` filter. When
 * a theme or plugin returns `false` or `0` from this filter, the safeguard is
 * disabled and full-resolution originals accumulate in the media library and
 * get served on pages — increasing bandwidth and slowing loads.
 *
 * This treatment stores a flag and target threshold (2560 px) that tells the
 * Christopher Ross Shadow bootstrap to add a `big_image_size_threshold` filter at high
 * priority (999) restoring the default behaviour. This overrides any earlier
 * filter that disabled it.
 *
 * Note: the threshold only applies to future uploads. Existing oversized
 * images in the media library are not re-processed.
 *
 * Undo: deletes the stored value; bootstrap stops applying the override filter.
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
 * Re-enables the WordPress big image size threshold at 2560 px.
 */
class Treatment_Large_Image_Threshold extends Treatment_Base {

	/** @var string */
	protected static $slug = 'large-image-threshold';

	/** Default WordPress threshold in pixels. */
	private const TARGET_THRESHOLD_PX = 2560;

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the threshold so the bootstrap re-enables the big_image_size_threshold filter.
	 *
	 * @return array
	 */
	public static function apply(): array {
		update_option( 'thisismyurl_shadow_big_image_threshold', self::TARGET_THRESHOLD_PX );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: threshold in pixels */
				__( 'Big image scaling threshold restored to %d px. Uploaded images wider than this will be automatically downscaled, reducing media library bloat and bandwidth usage. Existing oversized images are not reprocessed.', 'thisismyurl-shadow' ),
				self::TARGET_THRESHOLD_PX
			),
		);
	}

	/**
	 * Remove the stored threshold; bootstrap stops applying the override filter.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_big_image_threshold' );

		return array(
			'success' => true,
			'message' => __( 'Big image threshold override removed. WordPress will use whatever the active theme/plugins set for big_image_size_threshold.', 'thisismyurl-shadow' ),
		);
	}
}
