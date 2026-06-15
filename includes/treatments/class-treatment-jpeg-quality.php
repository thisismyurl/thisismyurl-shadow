<?php
/**
 * Treatment: Optimise WordPress JPEG compression quality
 *
 * WordPress applies a `jpeg_quality` filter when generating image thumbnails
 * during upload. The default was 82 through WordPress 4.4, then raised to 90
 * in some configurations. At 90 or above, JPEG files are 20–50% larger than
 * necessary with no perceptible visual difference for web use.
 *
 * The diagnostic flags values outside the 60–85 working range. This treatment
 * stores a target quality of 82 and tells the Shadow by Christopher Ross bootstrap to apply a
 * `jpeg_quality` filter returning that value.
 *
 * Note: quality changes only affect images processed after installation.
 * Existing media library thumbnails are not re-generated automatically — use
 * a tool such as "Regenerate Thumbnails" to reprocess existing images.
 *
 * Undo: deletes the stored quality value; the bootstrap stops applying the
 * filter and WordPress falls back to its default or any existing filter.
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
 * Sets WordPress JPEG compression quality to 82 via a filter.
 */
class Treatment_Jpeg_Quality extends Treatment_Base {

	/** @var string */
	protected static $slug = 'jpeg-quality';

	/** Recommended quality value: good visual result with ~30% smaller files. */
	private const TARGET_QUALITY = 82;

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the target quality value so the bootstrap applies the jpeg_quality filter.
	 *
	 * @return array
	 */
	public static function apply(): array {
		update_option( 'thisismyurl_shadow_jpeg_quality', self::TARGET_QUALITY );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: target JPEG quality */
				__( 'JPEG quality filter set to %d. New image uploads and regenerated thumbnails will use this quality level. Existing thumbnails are not affected — use a thumbnail regeneration tool to reprocess existing media.', 'thisismyurl-shadow' ),
				self::TARGET_QUALITY
			),
		);
	}

	/**
	 * Remove the stored quality option; bootstrap stops filtering jpeg_quality.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_jpeg_quality' );

		return array(
			'success' => true,
			'message' => __( 'JPEG quality filter removed. WordPress will use its default quality (or any other active filter) for future uploads.', 'thisismyurl-shadow' ),
		);
	}
}
