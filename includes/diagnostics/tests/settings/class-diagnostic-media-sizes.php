<?php
/**
 * Media Sizes Diagnostic
 *
 * Checks whether WordPress image size settings (thumbnail, medium, large) have
 * been customised from the factory defaults. Sites left on defaults may
 * generate incorrectly-sized image variants on every upload.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Media_Sizes Class
 *
 * Uses the WP_Settings helper to read thumbnail, medium, and large image size
 * options. Returns a low-severity finding when all three are still at the
 * WordPress installation defaults (150×150, 300×300, 1024×1024).
 *
 * @since 0.6095
 */
class Diagnostic_Media_Sizes extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'media-sizes';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Media Sizes';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress media size settings have been customized from factory defaults, which may otherwise generate unnecessarily large or poorly-sized images.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the thumbnail, medium, and large size dimensions via the WP_Settings
	 * helper. Returns null when any dimension has been changed from the WordPress
	 * defaults. Returns a low-severity finding when all three are still at the
	 * factory default values (150×150, 300×300, 1024×1024).
	 *
	 * @since  0.6095
	 * @return array|null Finding array when sizes are default, null when customised.
	 */
	public static function check() {
		$thumb  = WP_Settings::get_thumbnail_size();
		$medium = WP_Settings::get_medium_size();
		$large  = WP_Settings::get_large_size();

		$wp_defaults = (
			150 === $thumb['width'] && 150 === $thumb['height'] &&
			300 === $medium['width'] && 300 === $medium['height'] &&
			1024 === $large['width'] && 1024 === $large['height']
		);

		if ( ! $wp_defaults ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your image size settings (thumbnail, medium, large) are all still at the WordPress installation defaults. These generic sizes may not match your theme layouts, causing unnecessary image files to be generated on upload. Review and customise them for your design.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'thumbnail' => $thumb,
				'medium'    => $medium,
				'large'     => $large,
			),
		);
	}
}
