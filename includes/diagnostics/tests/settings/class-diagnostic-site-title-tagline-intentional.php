<?php
/**
 * Site Title And Tagline Diagnostic
 *
 * Checks whether the site title and tagline have been updated from the WordPress
 * default placeholder values that appear in browser tabs and search results.
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
 * Diagnostic_Site_Title_Tagline_Intentional Class
 *
 * Uses WP_Settings helpers to detect whether blogname or blogdescription
 * contain default placeholder text, returning a low-severity finding.
 *
 * @since 0.6095
 */
class Diagnostic_Site_Title_Tagline_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'site-title-tagline-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Site Title And Tagline';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the site title and tagline have been updated from the WordPress default placeholder values that appear in browser tabs and search results.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * Calls WP_Settings::is_default_site_title() and is_default_tagline() to
	 * determine whether either value is still a WordPress installer default.
	 * Returns null when both have been customised. When one or both are defaults,
	 * collects the specific issue strings and returns a low-severity finding.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when default values are detected, null when healthy.
	 */
	public static function check() {
		$title_default   = WP_Settings::is_default_site_title();
		$tagline_default = WP_Settings::is_default_tagline();

		if ( ! $title_default && ! $tagline_default ) {
			return null;
		}

		$issues = array();
		if ( $title_default ) {
			$issues[] = sprintf(
				/* translators: %s: current site title */
				__( 'Site title is "%s" (a default or empty value).', 'thisismyurl-shadow' ),
				WP_Settings::get_site_title()
			);
		}
		if ( $tagline_default ) {
			$issues[] = sprintf(
				/* translators: %s: current tagline */
				__( 'Tagline is "%s" (a default or empty value).', 'thisismyurl-shadow' ),
				WP_Settings::get_tagline()
			);
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your site title or tagline still appears to be a WordPress default or empty. These values appear in browser tabs, search results, and social media previews — set them intentionally to reflect your brand.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'issues'      => $issues,
				'site_title'  => WP_Settings::get_site_title(),
				'tagline'     => WP_Settings::get_tagline(),
			),
		);
	}
}
