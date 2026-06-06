<?php
/**
 * HTTPS Enabled Diagnostic
 *
 * Verifies that both the WordPress Address and Home URL are configured
 * to use HTTPS, ensuring all site traffic is encrypted in transit.
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
 * Diagnostic_Https_Enabled Class
 *
 * Checks whether both the WordPress Address (siteurl) and Home URL (home)
 * are configured to use HTTPS.
 *
 * @since 0.6095
 */
class Diagnostic_Https_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'https-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'HTTPS Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether both the WordPress Address and Home URL are configured to use HTTPS, ensuring all site traffic is encrypted in transit.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

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
	 * Reads the siteurl and home options to confirm both begin with "https://",
	 * returning a critical finding when either URL uses plain HTTP.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when HTTPS is not enabled, null when healthy.
	 */
	public static function check() {
		if ( WP_Settings::is_site_url_https() && WP_Settings::is_home_url_https() ) {
			return null;
		}

		$issues = array();
		if ( ! WP_Settings::is_site_url_https() ) {
			$issues[] = sprintf(
				/* translators: %s: WordPress Address URL */
				__( 'WordPress Address (siteurl) is "%s" — not using HTTPS.', 'thisismyurl-shadow' ),
				WP_Settings::get_wp_address()
			);
		}
		if ( ! WP_Settings::is_home_url_https() ) {
			$issues[] = sprintf(
				/* translators: %s: Site Address URL */
				__( 'Site Address (home) is "%s" — not using HTTPS.', 'thisismyurl-shadow' ),
				WP_Settings::get_home_address()
			);
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your site is not configured to serve pages over HTTPS. All data exchanged between your visitors and the server — including login credentials, contact form submissions, and payment details — is transmitted unencrypted. Install an SSL certificate and update both URL settings to https://.', 'thisismyurl-shadow' ),
			'severity'     => 'critical',
			'threat_level' => 90,
			'details'      => array(
				'issues'        => $issues,
				'site_url'      => WP_Settings::get_wp_address(),
				'home_url'      => WP_Settings::get_home_address(),
			),
		);
	}
}
