<?php
/**
 * Cookie Consent Plugin Active Diagnostic
 *
 * Checks that a recognised cookie consent or GDPR compliance plugin is installed
 * and active, as required for sites serving EU and international visitors.
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
 * Diagnostic_Cookie_Consent_Plugin_Active Class
 *
 * Scans the active_plugins option for well-known cookie consent and GDPR
 * compliance plugins, returning a medium-severity finding when none are detected.
 *
 * @since 0.6095
 */
class Diagnostic_Cookie_Consent_Plugin_Active extends Diagnostic_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'cookie-consent-plugin-active';

	/**
	 * @var string
	 */
	protected static $title = 'Cookie Consent Plugin Active';

	/**
	 * @var string
	 */
	protected static $description = 'Checks that a recognised cookie consent or GDPR compliance plugin is installed and active, as required for sites serving EU and international visitors.';

	/**
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
	 * Run the diagnostic check.
	 *
	 * Checks the active_plugins option against a curated list of well-known
	 * cookie consent / GDPR compliance plugins. Returns null immediately when
	 * any recognised plugin is active. Returns a medium-severity finding when
	 * none are detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no consent plugin is active, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$consent_plugins = array(
			'cookie-law-info/cookie-law-info.php',
			'gdpr-cookie-consent/gdpr-cookie-consent.php',
			'cookieyes-lgtm/cookieyes.php',
			'complianz-gdpr/complianz-gpdr.php',
			'complianz-gdpr-premium/complianz-gpdr-premium.php',
			'iubenda-cookie-law-solution/iubenda_cookie_law_solution.php',
			'uk-cookie-consent/uk-cookie-consent.php',
			'webtoffee-gdpr-cookie-consent/webtoffee-gdpr-cookie-consent.php',
			'wp-gdpr-compliance/wp-gdpr-compliance.php',
			'cookie-notice/cookie-notice.php',
			'osano-consent-manager/osano-consent-manager.php',
			'real-cookie-banner/real-cookie-banner.php',
			'borlabs-cookie/borlabs-cookie.php',
		);

		foreach ( $consent_plugins as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No cookie consent or GDPR compliance plugin is active. Sites using analytics, marketing, or social tracking cookies are required under GDPR, CCPA, and similar regulations to obtain visitor consent before setting non-essential cookies. Install a plugin such as Complianz, CookieYes, or Cookie Law Info to manage consent banners.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array(
				'consent_plugin_detected' => false,
			),
		);
	}
}
