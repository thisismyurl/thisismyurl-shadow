<?php
/**
 * Date Time Format Diagnostic
 *
 * Checks whether the date and time display formats match the convention expected
 * by the site's locale, preventing mismatched date formatting for non-US sites.
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
 * Diagnostic_Date_Time_Format_Intentional Class
 *
 * Reads the date_format option and the site locale, flagging when the default
 * US-style format ('F j, Y') is in use on a non-US locale site.
 *
 * @since 0.6095
 */
class Diagnostic_Date_Time_Format_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'date-time-format-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Date Time Format';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the date and time display formats match the convention expected by the site\'s locale. A UK or Australian business showing US-style dates (e.g. "January 5, 2025") looks unprofessional to local visitors.';

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
	protected static $impact = 'Mismatched date formats create confusion for local site visitors and reduce the professionalism of published content.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads date_format and get_locale(). Returns null immediately when the
	 * locale is en_US (the WordPress default format is appropriate) or when
	 * the date format has been changed from the default. When the locale is
	 * non-US and the format is still 'F j, Y', returns a low-severity finding
	 * suggesting a format review.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when format may be locale-mismatched, null when healthy.
	 */
	public static function check() {
		$locale      = get_locale();
		$date_format = get_option( 'date_format', 'F j, Y' );

		// Default US format is appropriate for en_US locale.
		if ( str_starts_with( $locale, 'en_US' ) ) {
			return null;
		}

		// If the format has been changed from the default, assume intentional.
		if ( 'F j, Y' !== $date_format ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: locale, 2: date format string */
				__( 'The site language is %1$s but the date format is still the US default ("%2$s"). Dates formatted as "January 5, 2025" look out-of-place for non-US visitors. Review and adjust the format under Settings → General → Date Format.', 'thisismyurl-shadow' ),
				$locale,
				$date_format
			),
			'severity'     => 'low',
			'threat_level' => 5,
			'details'      => array(
				'locale'      => $locale,
				'date_format' => $date_format,
			),
		);
	}
}
