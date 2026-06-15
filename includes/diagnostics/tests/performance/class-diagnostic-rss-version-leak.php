<?php
/**
 * RSS Version Leak Diagnostic
 *
 * Checks whether the WordPress RSS/Atom feed is outputting a <generator>
 * tag that advertises the exact WordPress version number, which is separate
 * from the meta generator tag on HTML pages.
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
 * Diagnostic_Rss_Version_Leak Class
 *
 * @since 0.6095
 */
class Diagnostic_Rss_Version_Leak extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'rss-version-leak';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'WordPress Version in RSS Feed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress RSS feed is outputting a <generator> tag that reveals the exact WordPress version, independently of the HTML page generator meta tag.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Run the diagnostic check.
	 *
	 * WordPress hooks the_generator action on rss2_head, atom_head, rdf_header
	 * etc. at priority 10. Checks whether those actions are still registered OR
	 * whether the the_generator filter has been set to return an empty string.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when version is still exposed, null when healthy.
	 */
	public static function check() {
		// Perfmatters — remove_version strips the generator from feeds too.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['remove_version'] ) ) {
			return null;
		}

		// WP Rocket — remove_version_number option.
		$rocket = get_option( 'wp_rocket_settings', array() );
		if ( is_array( $rocket ) && ! empty( $rocket['remove_version'] ) ) {
			return null;
		}

		// Check if the_generator filter has been explicitly set to return empty.
		if ( has_filter( 'the_generator', '__return_empty_string' ) ) {
			return null;
		}

		// Check if the the_generator action has been removed from the RSS2 feed.
		// WordPress registers it at priority 10 in default-filters.php.
		if ( ! has_action( 'rss2_head', 'the_generator' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your WordPress RSS feed outputs a <generator>https://wordpress.org/?v=X.X.X</generator> element that exposes the exact WordPress version to anyone subscribing to your feed or fetching it programmatically. This is a separate version leak from the HTML meta generator tag and needs to be addressed independently. Automated vulnerability scanners use both signals.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 12,
			'details'      => array(
				'fix' => __( 'Add to functions.php: add_filter(\'the_generator\', \'__return_empty_string\'); — or use Perfmatters\' "Remove Version Numbers" option, which covers both HTML and RSS.', 'thisismyurl-shadow' ),
			),
		);
	}
}
