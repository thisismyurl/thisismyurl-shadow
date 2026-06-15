<?php
/**
 * Media Year Month Folders Enabled Diagnostic
 *
 * Checks whether WordPress is organising media uploads into year/month
 * subdirectories. When disabled, all uploaded files land in a single flat
 * uploads/ folder, creating filesystem performance issues and making manual
 * file management impractical as the media library grows.
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
 * Diagnostic_Media_Year_Month_Folders_Enabled Class
 *
 * Reads the uploads_use_yearmonth_folders WordPress option and returns a
 * low-severity finding when the option is falsy (year/month subdirectories
 * are disabled).
 *
 * @since 0.6095
 */
class Diagnostic_Media_Year_Month_Folders_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'media-year-month-folders-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Media Year Month Folders Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is organising media uploads into year/month subdirectories. When this is disabled, every uploaded file lands in a single flat uploads/ folder, which creates filesystem performance issues and makes manual file management impractical as the library grows.';

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
	protected static $confidence = 'low';

	/**
	 * Severity of the finding.
	 *
	 * @var string
	 */
	protected static $severity = 'low';

	/**
	 * Estimated minutes to resolve.
	 *
	 * @var int
	 */
	protected static $time_to_fix_minutes = 5;

	/**
	 * Business impact statement.
	 *
	 * @var string
	 */
	protected static $impact = 'A flat uploads directory slows filesystem operations and makes it nearly impossible for the site owner to manage, audit, or clean up media files manually.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the uploads_use_yearmonth_folders option. Returns null when the
	 * option is truthy (year/month organisation is enabled). Returns a low-
	 * severity finding when the option is falsy, advising the user to enable
	 * the setting under Settings > Media.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when year/month folders are disabled, null when enabled.
	 */
	public static function check() {
		$enabled = (bool) get_option( 'uploads_use_yearmonth_folders', 1 );

		if ( $enabled ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress is uploading all media files into a single flat uploads/ directory. As your media library grows this makes filesystem operations slower, increases directory listing times, and makes it impractical to manage files manually. Enable year/month subdirectories under Settings → Media.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'uploads_use_yearmonth_folders' => false,
			),
		);
	}
}
