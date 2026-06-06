<?php
/**
 * JPEG Quality Configured Diagnostic
 *
 * Checks the effective JPEG quality level applied by WordPress during image
 * processing, flagging values that are too high (wasteful) or too low (blurry).
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_WP_Settings_Helper as WP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JPEG Quality Configured Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Jpeg_Quality extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'jpeg-quality';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'JPEG Quality';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress JPEG image quality setting is within the optimal 60–85 range that balances visual quality with manageable file sizes.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Applies the wp_editor_set_quality filter to read the effective JPEG quality
	 * and flags values outside the recommended 75–85 range.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when quality is out of range, null when healthy.
	 */
	public static function check() {
		$quality = WP_Settings::get_jpeg_quality();

		// 60–85 is the sensible working range — good quality with manageable file sizes.
		if ( $quality >= 60 && $quality <= 85 ) {
			return null;
		}

		if ( $quality > 85 ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %d: current JPEG quality */
					__( 'JPEG compression quality is set to %d/100, which is high. At this level, JPEG files are substantially larger than necessary. Reducing quality to 75–82 typically produces images that are visually indistinguishable while cutting file sizes by 20–50%%.', 'thisismyurl-shadow' ),
					$quality
				),
				'severity'     => 'low',
				'threat_level' => 20,
				'details'      => array(
					'current_quality'    => $quality,
					'recommended_range'  => '75–82',
				),
			);
		}

		// Quality < 60 — too low, visible artefacts.
		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: current JPEG quality */
				__( 'JPEG compression quality is set to %d/100, which is very low. Images compressed at this level will show visible blocking and colour artefacts. Increase quality to at least 70 for an acceptable visual result.', 'thisismyurl-shadow' ),
				$quality
			),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'current_quality'   => $quality,
				'recommended_range' => '75–82',
			),
		);
	}
}
