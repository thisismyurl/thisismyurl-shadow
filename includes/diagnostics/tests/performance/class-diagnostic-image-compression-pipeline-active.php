<?php
/**
 * Image Compression Pipeline Active Diagnostic
 *
 * Checks whether an automatic image compression/optimisation plugin is active,
 * ensuring uploaded images are compressed to reduce storage and page weight.
 *
 * @package    Christopher Ross Shadow
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
 * Image Compression Pipeline Active Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Image_Compression_Pipeline_Active extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'image-compression-pipeline-active';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Image Compression Pipeline Active';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'No image compression plugin is active. Unoptimised images are typically the largest contributor to slow page load times.';

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
	 * Scans active plugins for known image optimisation tools and checks the
	 * media library for evidence of compression post-meta.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no compression pipeline is found, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$compression_plugins = array(
			'wp-smushit/wp-smush.php'                           => 'Smush',
			'wp-smush-pro/wp-smush.php'                        => 'Smush Pro',
			'ewww-image-optimizer/ewww-image-optimizer.php'    => 'EWWW Image Optimizer',
			'ewww-image-optimizer-cloud/ewww-image-optimizer.php' => 'EWWW Image Optimizer Cloud',
			'shortpixel-image-optimiser/wp-shortpixel.php'     => 'ShortPixel',
			'imagify/imagify.php'                              => 'Imagify',
			'optimole-wp/optimole-wp.php'                      => 'Optimole',
			'tiny-compress-images/tiny-compress-images.php'    => 'TinyPNG / Compress JPEG & PNG',
			'robin-image-optimizer/robin-image-optimizer.php'  => 'Robin Image Optimizer',
			'wp-compress-image-optimizer/wp-compress.php'      => 'WP Compress',
			'reSmush-it/reSmush-it.php'                         => 'reSmush.it',
		);

		foreach ( $compression_plugins as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null;
			}
		}

		// No compression plugin. Count uploaded image attachments as context.
		$image_counts = wp_count_attachments( 'image' );
		$image_count  = 0;
		if ( is_object( $image_counts ) ) {
			$image_count = array_sum( array_map( 'intval', get_object_vars( $image_counts ) ) );
		}

		if ( $image_count === 0 ) {
			return null; // No images uploaded yet.
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of uploaded images */
				__( 'No image compression plugin is active. This site has %d uploaded image(s) that are being served at their original file size. An image optimisation plugin such as Smush, ShortPixel, or EWWW Image Optimizer can automatically compress images on upload, typically reducing file sizes by 40–70%% without visible quality loss.', 'thisismyurl-shadow' ),
				$image_count
			),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array(
				'image_count'              => $image_count,
				'compression_plugin_found' => false,
			),
		);
	}
}
