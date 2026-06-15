<?php
/**
 * Site Language Diagnostic
 *
 * Checks whether the WordPress site language has been explicitly set to match
 * the business audience, rather than left at the installer default of en_US.
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
 * Diagnostic_Site_Language_Intentional Class
 *
 * Reads the WPLANG option and get_locale() to determine whether the site
 * language has been deliberately configured or left at the installer default.
 *
 * @since 0.6095
 */
class Diagnostic_Site_Language_Intentional extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'site-language-intentional';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Site Language';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress site language has been explicitly set to match the business audience, rather than left at the server default or left as the installer default of en_US.';

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
	protected static $impact = 'A mismatched site language produces incorrect date formats, broken translations, and confusing content for local visitors.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the WPLANG option and get_locale(). When the locale resolves to
	 * 'en_US' (the installer default), cross-checks the site timezone against a
	 * list of US/English-primary timezone prefixes. If the timezone is outside
	 * the expected English-primary zones, the site is likely targeting a non-
	 * English audience but has not set its language — returns a low-severity
	 * finding. Returns null when a non-default locale is configured, or when
	 * en_US is appropriate given the detected timezone.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when language may be misconfigured, null when healthy.
	 */
	public static function check() {
		$locale   = get_locale();
		$wplang   = get_option( 'WPLANG', '' );

		// A non-empty, non-en_US WPLANG means the admin deliberately set a locale.
		if ( '' !== $wplang && 'en_US' !== $wplang ) {
			return null;
		}

		// Locale is en_US (either set or defaulted). Check if the timezone
		// suggests the site is not US/English-primary.
		$timezone = get_option( 'timezone_string', '' );
		if ( '' === $timezone ) {
			// UTC offset only — no region signal to use, so we can't flag confidently.
			return null;
		}

		// Timezone prefixes that are typical for predominantly English-speaking regions.
		$english_primary_prefixes = array(
			'America/',
			'Pacific/Auckland',
			'Pacific/Honolulu',
			'Australia/',
			'Pacific/Auckland',
			'Europe/London',
			'UTC',
		);

		foreach ( $english_primary_prefixes as $prefix ) {
			if ( str_starts_with( $timezone, $prefix ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: current locale, 2: timezone string */
				__( 'The site language is set to %1$s (the installer default) but the timezone is %2$s, which suggests a non-English-primary audience. Review the language setting under Settings → General → Site Language to confirm it matches your visitors.', 'thisismyurl-shadow' ),
				$locale,
				$timezone
			),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'locale'   => $locale,
				'timezone' => $timezone,
			),
		);
	}
}
