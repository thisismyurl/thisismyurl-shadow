<?php
/**
 * Timezone Configured Diagnostic
 *
 * Checks whether the WordPress timezone is set to a named region timezone
 * rather than the default UTC offset (0), which affects scheduled tasks,
 * date displays, and event-plugin output.
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
 * Diagnostic_Timezone Class
 *
 * Uses the WP_Settings helper to read the timezone_string and gmt_offset
 * options and flags when the site is still on the UTC+0 default.
 *
 * @since 0.6095
 */
class Diagnostic_Timezone extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'timezone';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Timezone';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress timezone is set to a named region timezone rather than a generic UTC offset, which affects scheduled tasks and date displays.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Reads the timezone_string and gmt_offset WordPress options via the
	 * WP_Settings helper. Returns null (healthy) when a named timezone is set
	 * and valid, or when a deliberate non-zero UTC offset is configured.
	 * Returns a low-severity finding when the timezone is still the UTC default.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when timezone is UTC default, null when healthy.
	 */
	public static function check() {
		$tz = WP_Settings::get_timezone_data();

		// Named timezone set and valid — healthy.
		if ( $tz['is_named'] && $tz['is_valid'] ) {
			return null;
		}

		// Numeric UTC offset explicitly set (not zero) — intentional enough.
		if ( ! $tz['is_named'] && 0.0 !== $tz['gmt_offset'] ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your site timezone is set to UTC (the WordPress default). Dates, scheduled posts, and event plugins will show incorrect times for your audience. Set a named timezone matching your business location.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'timezone_string' => $tz['timezone_string'],
				'gmt_offset'      => $tz['gmt_offset'],
			),
		);
	}
}
