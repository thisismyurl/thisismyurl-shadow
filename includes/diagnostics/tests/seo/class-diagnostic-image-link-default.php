<?php
/**
 * Image Link Default Diagnostic
 *
 * Checks whether WordPress is configured to link inserted images to
 * attachment pages by default, which creates thin URLs that can harm SEO.
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
 * Diagnostic_Image_Link_Default Class
 *
 * Reads the image_default_link_type option and flags configurations where
 * images are linked to attachment pages or raw file URLs by default.
 *
 * @since 0.6095
 */
class Diagnostic_Image_Link_Default extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'image-link-default';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Image Link Default';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is configured to link inserted images to attachment pages by default, which creates thin URLs that can harm SEO.';

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
	 * Reads the image_default_link_type option and returns a low-severity
	 * finding when images are linked to attachment pages ('post') or raw
	 * file URLs ('file') by default.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when default link type is non-optimal, null when healthy.
	 */
	public static function check() {
		$link_type = get_option( 'image_default_link_type', 'none' );

		if ( 'post' === $link_type ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'The default image insertion setting links images to their attachment page. Attachment pages are thin-content pages with no SEO value. Change the default to "None" under Settings → Media so new images are inserted without a link, or update old content manually.', 'thisismyurl-shadow' ),
				'severity'     => 'low',
				'threat_level' => 20,
				'details'      => array( 'image_default_link_type' => 'post' ),
			);
		}

		if ( 'file' === $link_type ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'The default image insertion setting links images directly to the media file URL. This can take visitors away from your content to a raw image with no navigation or context. Change the default to "None" under Settings → Media.', 'thisismyurl-shadow' ),
				'severity'     => 'low',
				'threat_level' => 15,
				'details'      => array( 'image_default_link_type' => 'file' ),
			);
		}

		return null;
	}
}
