<?php
/**
 * Site URLs Correctly Diagnostic
 *
 * Checks whether the WordPress Address and Site URL are both using HTTPS and
 * are consistent with each other to avoid redirect loops or mixed content.
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
 * Diagnostic_Site_Urls_Correctly Class
 *
 * Validates that the siteurl and home options both use HTTPS and share the same
 * hostname, reporting a high-severity finding with a list of specific issues.
 *
 * @since 0.6095
 */
class Diagnostic_Site_Urls_Correctly extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'site-urls-correctly';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Site URLs Correctly';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress Address and Site URL are both using HTTPS and are consistent with each other to avoid redirect loops or mixed content.';

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
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Uses WP_Settings helpers to verify that both the WordPress Address (siteurl)
	 * and Site Address (home) use HTTPS, and that their hostnames match. Collects
	 * all detected issues into an array. Returns null when no issues are found,
	 * otherwise returns a high-severity finding listing each problem.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when URL issues are detected, null when healthy.
	 */
	public static function check() {
		$wp_address   = WP_Settings::get_wp_address();
		$home_address = WP_Settings::get_home_address();
		$issues       = array();

		if ( ! WP_Settings::is_site_url_https() ) {
			$issues[] = sprintf(
				/* translators: %s: WordPress Address URL */
				__( 'WordPress Address (URL) "%s" does not use HTTPS.', 'thisismyurl-shadow' ),
				$wp_address
			);
		}

		if ( ! WP_Settings::is_home_url_https() ) {
			$issues[] = sprintf(
				/* translators: %s: Site Address URL */
				__( 'Site Address (URL) "%s" does not use HTTPS.', 'thisismyurl-shadow' ),
				$home_address
			);
		}

		// Check that both URLs resolve to the same host to avoid split-brain configs.
		$wp_host   = wp_parse_url( $wp_address, PHP_URL_HOST );
		$home_host = wp_parse_url( $home_address, PHP_URL_HOST );
		if ( $wp_host && $home_host && $wp_host !== $home_host ) {
			$issues[] = sprintf(
				/* translators: %1$s: WP host, %2$s: home host */
				__( 'WordPress Address host (%1$s) and Site Address host (%2$s) do not match.', 'thisismyurl-shadow' ),
				$wp_host,
				$home_host
			);
		}

		if ( empty( $issues ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'One or more WordPress URL settings are misconfigured. Using HTTP instead of HTTPS exposes your admin credentials and visitor data. Mismatched host names can break redirects and canonical tags.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 75,
			'details'      => array(
				'issues'        => $issues,
				'wordpress_url' => $wp_address,
				'site_url'      => $home_address,
			),
		);
	}
}
