<?php
/**
 * Site Charset UTF-8 Diagnostic
 *
 * Checks whether the WordPress blog_charset option is set to UTF-8. Sites
 * migrated from legacy hosting sometimes carry an ISO-8859-1 charset that
 * causes character encoding errors in content, feeds, and API responses.
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
 * Diagnostic_Site_Charset_Utf8 Class
 *
 * Reads the blog_charset WordPress option and flags when it is set to anything
 * other than UTF-8 (case-insensitive).
 *
 * @since 0.6095
 */
class Diagnostic_Site_Charset_Utf8 extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'site-charset-utf8';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Site Charset UTF-8';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress blog_charset option is set to UTF-8. Sites migrated from legacy hosting sometimes carry an ISO-8859-1 or other charset that causes character encoding errors in page content, RSS feeds, and REST API responses.';

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
	protected static $severity = 'medium';

	/**
	 * Estimated minutes to resolve.
	 *
	 * @var int
	 */
	protected static $time_to_fix_minutes = 10;

	/**
	 * Business impact statement.
	 *
	 * @var string
	 */
	protected static $impact = 'A non-UTF-8 charset produces garbled special characters (mojibake) in content, feeds, and API consumers, eroding visitor trust and breaking third-party integrations.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the blog_charset option, normalises it to uppercase, and returns null
	 * when it equals 'UTF-8'. Returns a medium-severity finding with the current
	 * charset value when any other encoding is detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when charset is not UTF-8, null when healthy.
	 */
	public static function check() {
		$charset = strtoupper( trim( (string) get_option( 'blog_charset', 'UTF-8' ) ) );

		if ( 'UTF-8' === $charset ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: current charset value */
				__( 'The site charset is set to "%s" instead of UTF-8. A non-UTF-8 charset produces garbled special characters (mojibake) in page content, RSS feeds, and REST API responses, and can cause data loss during migrations. Update the charset to UTF-8 under Settings → Reading (or via wp-config.php) after confirming the database collation is also utf8mb4.', 'thisismyurl-shadow' ),
				$charset
			),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array(
				'current_charset' => $charset,
			),
		);
	}
}
