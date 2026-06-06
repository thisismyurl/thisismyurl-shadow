<?php
/**
 * Site Icon Configured Diagnostic
 *
 * Checks whether a site icon (favicon) has been configured in WordPress,
 * which appears in browser tabs, bookmarks, and search engine results.
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
 * Diagnostic_Site_Icon Class
 *
 * Reads the site_icon WordPress option and returns a low-severity finding
 * when the option is empty, indicating no favicon has been uploaded.
 *
 * @since 0.6095
 */
class Diagnostic_Site_Icon extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'site-icon';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Site Icon';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a site icon (favicon) has been configured in WordPress, which appears in browser tabs, bookmarks, and search engine results.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Reads the site_icon option, which stores the attachment ID of the uploaded
	 * favicon. Returns null when an icon has been set. Returns a low-severity
	 * finding when the option is 0 or absent, prompting the user to set a site
	 * icon via Appearance > Customize > Site Identity.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no site icon is set, null when healthy.
	 */
	public static function check() {
		$site_icon_id = (int) get_option( 'site_icon', 0 );
		if ( $site_icon_id > 0 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No site icon (favicon) has been set. The site icon appears in browser tabs, bookmarks, mobile home screens, and Google Search results. Set one under Appearance > Customize > Site Identity.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'site_icon_id' => 0,
			),
		);
	}
}
